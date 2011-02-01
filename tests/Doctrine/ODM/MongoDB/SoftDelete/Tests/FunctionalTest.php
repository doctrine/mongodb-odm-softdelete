<?php

namespace Doctrine\ODM\MongoDB\SoftDelete\Tests;

use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\ODM\MongoDB\Configuration as ODMConfiguration;
use Doctrine\ODM\MongoDB\SoftDelete\UnitOfWork;
use Doctrine\ODM\MongoDB\SoftDelete\Configuration;
use Doctrine\ODM\MongoDB\SoftDelete\SoftDeleteManager;
use Doctrine\ODM\MongoDB\SoftDelete\SoftDeleteable;
use Doctrine\ODM\MongoDB\SoftDelete\Events;
use Doctrine\ODM\MongoDB\SoftDelete\Event\LifecycleEventArgs;
use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\ODM\MongoDB\Mapping\Driver\AnnotationDriver;
use Doctrine\Common\EventManager;
use Doctrine\MongoDB\Connection;
use PHPUnit_Framework_TestCase;

class FunctionalTest extends PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $this->sdm = $this->getTestSoftDeleteManager();
        $this->dm = $this->sdm->getDocumentManager();
        $this->dm->getDocumentCollection(__NAMESPACE__.'\Seller')->drop();
    }

    public function testDelete()
    {
        $softDeleteable = $this->getTestSoftDeleteable('jwage');
        $this->dm->persist($softDeleteable);
        $this->dm->flush();

        $this->sdm->delete($softDeleteable);
        $this->sdm->flush();

        $this->assertTrue($softDeleteable->isDeleted());
        $this->assertInstanceOf('DateTime', $softDeleteable->getDeletedAt());

        $check = $this->dm->getDocumentCollection(get_class($softDeleteable))->findOne();
        $this->assertTrue(isset($check['deletedAt']));
        $this->assertInstanceOf('MongoDate', $check['deletedAt']);
    }

    public function testDeleteMultiple()
    {
        $softDeleteable1 = $this->getTestSoftDeleteable('jwage1');
        $softDeleteable2 = $this->getTestSoftDeleteable('jwage2');
        $this->dm->persist($softDeleteable1);
        $this->dm->persist($softDeleteable2);
        $this->dm->flush();

        $this->sdm->delete($softDeleteable1);
        $this->sdm->delete($softDeleteable2);
        $this->sdm->flush();

        $this->assertTrue($softDeleteable1->isDeleted());
        $this->assertInstanceOf('DateTime', $softDeleteable1->getDeletedAt());
        $this->assertTrue($softDeleteable2->isDeleted());
        $this->assertInstanceOf('DateTime', $softDeleteable2->getDeletedAt());

        $check1 = $this->dm->getDocumentCollection(get_class($softDeleteable1))->findOne();
        $this->assertTrue(isset($check1['deletedAt']));
        $this->assertInstanceOf('MongoDate', $check1['deletedAt']);

        $check2 = $this->dm->getDocumentCollection(get_class($softDeleteable2))->findOne();
        $this->assertTrue(isset($check2['deletedAt']));
        $this->assertInstanceOf('MongoDate', $check2['deletedAt']);

        $this->sdm->restore($softDeleteable1);
        $this->sdm->restore($softDeleteable2);
        $this->sdm->flush();

        $check1 = $this->dm->getDocumentCollection(get_class($softDeleteable1))->findOne();
        $this->assertFalse(isset($check1['deletedAt']));

        $check2 = $this->dm->getDocumentCollection(get_class($softDeleteable2))->findOne();
        $this->assertFalse(isset($check2['deletedAt']));
    }

    public function testRestore()
    {
        $softDeleteable = $this->getTestSoftDeleteable('jwage');
        $this->dm->persist($softDeleteable);
        $this->dm->flush();

        $this->sdm->delete($softDeleteable);
        $this->sdm->flush();

        $check = $this->dm->getDocumentCollection(get_class($softDeleteable))->findOne();
        $this->assertTrue(isset($check['deletedAt']));
        $this->assertInstanceOf('MongoDate', $check['deletedAt']);

        $this->sdm->restore($softDeleteable);
        $this->sdm->flush();

        $this->assertFalse($softDeleteable->isDeleted());
        $this->assertNull($softDeleteable->getDeletedAt());

        $check = $this->dm->getDocumentCollection(get_class($softDeleteable))->findOne();
        $this->assertFalse(isset($check['deletedAt']));
    }

    public function testCreateQueryBuilder()
    {
        $softDeleteable = $this->getTestSoftDeleteable('jwage');
        $this->dm->persist($softDeleteable);
        $this->dm->flush();

        $check = $this->sdm->createQueryBuilder(get_class($softDeleteable))
            ->getQuery()
            ->getSingleResult();
        $this->assertNotNull($check);

        $this->sdm->delete($softDeleteable);
        $this->sdm->flush();

        $check = $this->sdm->createQueryBuilder(get_class($softDeleteable))
            ->getQuery()
            ->getSingleResult();
        $this->assertNull($check);

        $check = $this->sdm->createDeletedQueryBuilder(get_class($softDeleteable))
            ->getQuery()
            ->getSingleResult();
        $this->assertNotNull($check);
        $this->assertTrue($check->isDeleted());
        $this->assertInstanceOf('DateTime', $check->getDeletedAt());
    }

    public function testEvents()
    {
        $eventManager = $this->sdm->getEventManager();
        $eventSubscriber = new TestEventSubscriber();
        $eventManager->addEventSubscriber($eventSubscriber);

        $softDeleteable = $this->getTestSoftDeleteable('jwage');
        $this->dm->persist($softDeleteable);
        $this->dm->flush();

        $this->sdm->delete($softDeleteable);
        $this->sdm->flush();

        $this->assertEquals(array('preSoftDelete', 'postSoftDelete'), $eventSubscriber->called);

        $eventSubscriber->called = array();

        $this->sdm->restore($softDeleteable);
        $this->sdm->flush();

        $this->assertEquals(array('preSoftDeleteRestore', 'postSoftDeleteRestore'), $eventSubscriber->called);
    }

    private function getTestDocumentManager()
    {
        $configuration = new ODMConfiguration();
        $configuration->setHydratorDir(__DIR__);
        $configuration->setHydratorNamespace('TestHydrator');
        $configuration->setProxyDir(__DIR__);
        $configuration->setProxyNamespace('TestProxy');

        $reader = new AnnotationReader();
        $reader->setDefaultAnnotationNamespace('Doctrine\ODM\MongoDB\Mapping\\');
        $annotationDriver = new AnnotationDriver($reader, __DIR__ . '/Documents');
        $configuration->setMetadataDriverImpl($annotationDriver);

        $conn = new Connection(null, array(), $configuration);
        return DocumentManager::create($conn, $configuration);
    }

    public function getTestSoftDeleteable($name)
    {
        return new Seller($name);
    }

    public function getTestConfiguration()
    {
        return new Configuration();
    }

    public function getTestUnitOfWork(DocumentManager $dm, Configuration $configuration)
    {
        return new UnitOfWork($dm, $configuration);
    }

    public function getTestEventManager()
    {
        return new EventManager();
    }

    private function getTestSoftDeleteManager()
    {
        $dm = $this->getTestDocumentManager();
        $configuration = $this->getTestConfiguration();
        $unitOfWork = $this->getTestUnitOfWork($dm, $configuration);
        $eventManager = $this->getTestEventManager();
        return new SoftDeleteManager($dm, $configuration, $unitOfWork, $eventManager);
    }
}

class TestEventSubscriber implements \Doctrine\Common\EventSubscriber
{
    public $called = array();

    public function preSoftDelete(LifecycleEventArgs $args)
    {
        $this->called[] = 'preSoftDelete';
    }

    public function postSoftDelete(LifecycleEventArgs $args)
    {
        $this->called[] = 'postSoftDelete';
    }

    public function preSoftDeleteRestore(LifecycleEventArgs $args)
    {
        $this->called[] = 'preSoftDeleteRestore';
    }

    public function postSoftDeleteRestore(LifecycleEventArgs $args)
    {
        $this->called[] = 'postSoftDeleteRestore';
    }

    public function getSubscribedEvents()
    {
        return array(
            Events::preSoftDelete,
            Events::postSoftDelete,
            Events::preSoftDeleteRestore,
            Events::postSoftDeleteRestore
        );
    }
}

/** @Document */
class Seller implements SoftDeleteable
{
    /** @Id */
    private $id;

    /** @Date @Index */
    private $deletedAt;

    /** @String */
    private $name;

    public function __construct($name)
    {
        $this->name = $name;
    }

    public function getName()
    {
        return $this->name;
    }

    public function getDeletedAt()
    {
        return $this->deletedAt;
    }

    public function isDeleted()
    {
        return $this->deletedAt !== null ? true : false;
    }
}