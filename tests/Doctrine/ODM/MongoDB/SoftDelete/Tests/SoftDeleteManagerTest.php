<?php

namespace Doctrine\ODM\MongoDB\SoftDelete\Tests;

use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\ODM\MongoDB\SoftDelete\SoftDeleteManager;
use Doctrine\ODM\MongoDB\SoftDelete\Configuration;
use Doctrine\Common\EventManager;
use PHPUnit_Framework_TestCase;

class SoftDeleteManagerTest extends PHPUnit_Framework_TestCase
{
    private $dm;
    private $configuration;
    private $eventManager;
    private $softDeleteable;
    private $sdm;

    protected function setUp()
    {
        $this->dm = $this->getMockDocumentManager();
        $this->configuration = $this->getMockConfiguration();
        $this->eventManager = $this->getMockEventManager();
        $this->softDeleteable = $this->getMockSoftDeletable();
        $this->sdm = new SoftDeleteManager($this->dm, $this->configuration, $this->eventManager);
    }

    public function testConstructor()
    {
        $this->assertSame($this->dm, $this->sdm->getDocumentManager());
        $this->assertSame($this->configuration, $this->sdm->getConfiguration());
        $this->assertSame($this->eventManager, $this->sdm->getEventManager());
    }

    public function testDelete()
    {
        $this->sdm->delete($this->softDeleteable);
        $this->assertTrue($this->sdm->isScheduledForDelete($this->softDeleteable));

        $deletes = $this->sdm->getDocumentDeletes();
        $this->sdm->delete($this->softDeleteable);
        $this->assertEquals($deletes, $this->sdm->getDocumentDeletes(), 'Delete of already scheduled document does nothing');
    }

    public function testRestore()
    {
        $this->sdm->restore($this->softDeleteable);
        $this->assertTrue($this->sdm->isScheduledForRestore($this->softDeleteable));

        $restores = $this->sdm->getDocumentRestores();
        $this->sdm->restore($this->softDeleteable);
        $this->assertEquals($restores, $this->sdm->getDocumentRestores(), 'Restore of already scheduled document does nothing');
    }

    private function getMockConfiguration()
    {
        return $this->getMock('Doctrine\ODM\MongoDB\SoftDelete\Configuration');
    }

    private function getMockDocumentManager()
    {
        return $this->getMockBuilder('Doctrine\ODM\MongoDB\DocumentManager')
            ->disableOriginalConstructor()
            ->getMock();
    }

    private function getMockSoftDeletable()
    {
        return $this->getMock('Doctrine\ODM\MongoDB\SoftDelete\SoftDeleteable');
    }

    private function getMockEventManager()
    {
        return $this->getMockBuilder('Doctrine\Common\EventManager')
            ->disableOriginalConstructor()
            ->getMock();
    }
}