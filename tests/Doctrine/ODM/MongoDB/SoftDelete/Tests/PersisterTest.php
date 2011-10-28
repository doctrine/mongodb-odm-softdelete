<?php

namespace Doctrine\ODM\MongoDB\SoftDelete\Tests;

use PHPUnit_Framework_TestCase;
use Doctrine\ODM\MongoDB\SoftDelete\Persister;
use Doctrine\ODM\MongoDB\SoftDelete\Configuration;
use Doctrine\ODM\MongoDB\Mapping\ClassMetadata;
use Doctrine\ODM\MongoDB\Persisters\DocumentPersister;
use Doctrine\MongoDB\Collection;
use MongoDate;

class PersisterTest extends PHPUnit_Framework_TestCase
{
    public function testConstructor()
    {
        $class = $this->getMockClassMetadata();
        $collection = $this->getMockCollection();
        $configuration = $this->getMockConfiguration();
        $documentPersister = $this->getMockDocumentPersister();
        $persister = $this->getTestPersister($configuration, $class, $collection, $documentPersister);
        $this->assertSame($class, $persister->getClass());
        $this->assertSame($collection, $persister->getCollection());
    }

    public function testAddDelete()
    {
        $class = $this->getMockClassMetadata();
        $collection = $this->getMockCollection();
        $configuration = $this->getMockConfiguration();
        $documentPersister = $this->getMockDocumentPersister();
        $persister = $this->getTestPersister($configuration, $class, $collection, $documentPersister);
        $mockSoftDeleteable = $this->getMockSoftDeletable();
        $persister->addDelete($mockSoftDeleteable);

        $expects = array(spl_object_hash($mockSoftDeleteable) => $mockSoftDeleteable);
        $this->assertEquals($expects, $persister->getDeletes());
    }

    public function testAddRestore()
    {
        $class = $this->getMockClassMetadata();
        $collection = $this->getMockCollection();
        $configuration = $this->getMockConfiguration();
        $documentPersister = $this->getMockDocumentPersister();
        $persister = $this->getTestPersister($configuration, $class, $collection, $documentPersister);
        $mockSoftDeleteable = $this->getMockSoftDeletable();
        $persister->addRestore($mockSoftDeleteable);

        $expects = array(spl_object_hash($mockSoftDeleteable) => $mockSoftDeleteable);
        $this->assertEquals($expects, $persister->getRestores());
    }

    public function testExecuteDeletes()
    {
        $mockSoftDeleteable = $this->getMockSoftDeletable();

        $class = $this->getMockClassMetadata();
        $class->expects($this->once())
            ->method('getIdentifierObject')
            ->with($mockSoftDeleteable)
            ->will($this->returnValue(1));

        $date = new MongoDate();

        $query = array('_id' => array('$in' => array(1)));
        $collection = $this->getMockCollection();
        $collection->expects($this->once())
            ->method('update')
            ->with(
                $query,
                array('$set' => array(
                    'deletedAt' => $date
                )),
                array(
                    'multiple' => true,
                    'fsync' => true
                )
            );

        $configuration = $this->getMockConfiguration();
        $configuration->expects($this->once())
            ->method('getDeletedFieldName')
            ->will($this->returnValue('deletedAt'));

        $documentPersister = $this->getMockDocumentPersister();
        $documentPersister->expects($this->once())
            ->method('prepareQuery')
            ->will($this->returnValue($query));
        $persister = $this->getTestPersister($configuration, $class, $collection, $documentPersister);
        $persister->addDelete($mockSoftDeleteable);
        $persister->executeDeletes($date);
    }

    public function testExecuteRestores()
    {
        $mockSoftDeleteable = $this->getMockSoftDeletable();

        $class = $this->getMockClassMetadata();
        $class->expects($this->once())
            ->method('getIdentifierObject')
            ->with($mockSoftDeleteable)
            ->will($this->returnValue(1));

        $query = array('_id' => array('$in' => array(1)));
        $collection = $this->getMockCollection();
        $collection->expects($this->once())
            ->method('update')
            ->with(
                $query,
                array('$unset' => array(
                    'deletedAt' => true
                )),
                array(
                    'multiple' => true,
                    'fsync' => true
                )
            );

        $configuration = $this->getMockConfiguration();
        $configuration->expects($this->once())
            ->method('getDeletedFieldName')
            ->will($this->returnValue('deletedAt'));

        $documentPersister = $this->getMockDocumentPersister();
        $documentPersister->expects($this->once())
            ->method('prepareQuery')
            ->will($this->returnValue($query));
        $persister = $this->getTestPersister($configuration, $class, $collection, $documentPersister);
        $persister->addRestore($mockSoftDeleteable);
        $persister->executeRestores();
    }

    private function getMockSoftDeletable()
    {
        return $this->getMockBuilder('Doctrine\ODM\MongoDB\SoftDelete\SoftDeleteable')
            ->disableOriginalConstructor()
            ->getMock();
    }

    private function getMockClassMetadata()
    {
        return $this->getMockBuilder('Doctrine\ODM\MongoDB\Mapping\ClassMetadata')
            ->disableOriginalConstructor()
            ->getMock();
    }

    private function getMockCollection()
    {
        return $this->getMockBuilder('Doctrine\MongoDB\Collection')
            ->disableOriginalConstructor()
            ->getMock();
    }

    private function getMockConfiguration()
    {
        return $this->getMockBuilder('Doctrine\ODM\MongoDB\SoftDelete\Configuration')
            ->disableOriginalConstructor()
            ->getMock();
    }

    private function getMockDocumentPersister()
    {
        return $this->getMockBuilder('Doctrine\ODM\MongoDB\Persisters\DocumentPersister')
            ->disableOriginalConstructor()
            ->getMock();
    }

    private function getTestPersister(Configuration $configuration, ClassMetadata $class, Collection $collection, DocumentPersister $persister)
    {
        return new Persister($configuration, $class, $collection, $persister);
    }
}