<?php

namespace Doctrine\ODM\MongoDB\SoftDelete\Tests;

use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\ODM\MongoDB\SoftDelete\SoftDeleteManager;
use Doctrine\ODM\MongoDB\SoftDelete\Configuration;
use Doctrine\Common\EventManager;
use PHPUnit_Framework_TestCase;

class SoftDeleteManagerTest extends PHPUnit_Framework_TestCase
{
    public function testConstructor()
    {
        $dm = $this->getMockDocumentManager();
        $configuration = $this->getMockConfiguration();
        $eventManager = $this->getMockEventManager();
        $uow = $this->getTestSoftDeleteManager($dm, $configuration, $eventManager);
        $this->assertSame($dm, $uow->getDocumentManager());
    }

    public function testDelete()
    {
        $mockSoftDeleteable = $this->getMockSoftDeletable();
        $dm = $this->getMockDocumentManager();
        $configuration = $this->getMockConfiguration();
        $eventManager = $this->getMockEventManager();
        $uow = $this->getTestSoftDeleteManager($dm, $configuration, $eventManager);
        $uow->delete($mockSoftDeleteable);
        $this->assertTrue($uow->isScheduledForDelete($mockSoftDeleteable));
    }

    public function testRestore()
    {
        $mockSoftDeleteable = $this->getMockSoftDeletable();
        $dm = $this->getMockDocumentManager();
        $configuration = $this->getMockConfiguration();
        $eventManager = $this->getMockEventManager();
        $uow = $this->getTestSoftDeleteManager($dm, $configuration, $eventManager);
        $uow->restore($mockSoftDeleteable);
        $this->assertTrue($uow->isScheduledForRestore($mockSoftDeleteable));
    }

    private function getTestSoftDeleteManager(DocumentManager $dm, Configuration $configuration, EventManager $eventManager)
    {
        return new SoftDeleteManager($dm, $configuration, $eventManager);
    }

    private function getMockConfiguration()
    {
        return $this->getMockBuilder('Doctrine\ODM\MongoDB\SoftDelete\Configuration')
            ->disableOriginalConstructor()
            ->getMock();
    }

    private function getMockDocumentManager()
    {
        return $this->getMockBuilder('Doctrine\ODM\MongoDB\DocumentManager')
            ->disableOriginalConstructor()
            ->getMock();
    }

    private function getMockSoftDeletable()
    {
        return $this->getMockBuilder('Doctrine\ODM\MongoDB\SoftDelete\SoftDeleteable')
            ->disableOriginalConstructor()
            ->getMock();
    }

    private function getMockEventManager()
    {
        return $this->getMockBuilder('Doctrine\Common\EventManager')
            ->disableOriginalConstructor()
            ->getMock();
    }
}