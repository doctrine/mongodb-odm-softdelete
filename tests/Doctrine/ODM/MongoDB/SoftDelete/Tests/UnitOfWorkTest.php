<?php

namespace Doctrine\ODM\MongoDB\SoftDelete\Tests;

use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\ODM\MongoDB\SoftDelete\UnitOfWork;
use Doctrine\ODM\MongoDB\SoftDelete\Configuration;
use PHPUnit_Framework_TestCase;

class UnitOfWorkTest extends PHPUnit_Framework_TestCase
{
    public function testConstructor()
    {
        $dm = $this->getMockDocumentManager();
        $configuration = $this->getMockConfiguration();
        $uow = $this->getTestUnitOfWork($dm, $configuration);
        $this->assertSame($dm, $uow->getDocumentManager());
    }

    public function testDelete()
    {
        $mockSoftDeleteable = $this->getMockSoftDeletable();
        $mockSoftDeleteable->expects($this->once())
            ->method('isDeleted')
            ->will($this->returnValue(false));

        $dm = $this->getMockDocumentManager();
        $configuration = $this->getMockConfiguration();
        $uow = $this->getTestUnitOfWork($dm, $configuration);
        $uow->delete($mockSoftDeleteable);
        $this->assertTrue($uow->isScheduledForDelete($mockSoftDeleteable));
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testDeleteThrowsExceptionIfAlreadyDeleted()
    {
        $mockSoftDeleteable = $this->getMockSoftDeletable();
        $mockSoftDeleteable->expects($this->once())
            ->method('isDeleted')
            ->will($this->returnValue(true));

        $dm = $this->getMockDocumentManager();
        $configuration = $this->getMockConfiguration();
        $uow = $this->getTestUnitOfWork($dm, $configuration);
        $uow->delete($mockSoftDeleteable);
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testDeleteThrowsExceptionIfAlreadyScheduledForDelete()
    {
        $mockSoftDeleteable = $this->getMockSoftDeletable();
        $mockSoftDeleteable->expects($this->any())
            ->method('isDeleted')
            ->will($this->returnValue(false));

        $dm = $this->getMockDocumentManager();
        $configuration = $this->getMockConfiguration();
        $uow = $this->getTestUnitOfWork($dm, $configuration);
        $uow->delete($mockSoftDeleteable);
        $uow->delete($mockSoftDeleteable);
    }

    public function testRestore()
    {
        $mockSoftDeleteable = $this->getMockSoftDeletable();
        $mockSoftDeleteable->expects($this->once())
            ->method('isDeleted')
            ->will($this->returnValue(true));

        $dm = $this->getMockDocumentManager();
        $configuration = $this->getMockConfiguration();
        $uow = $this->getTestUnitOfWork($dm, $configuration);
        $uow->restore($mockSoftDeleteable);
        $this->assertTrue($uow->isScheduledForRestore($mockSoftDeleteable));
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testRestoreThrowsExceptionIfNotDeleted()
    {
        $mockSoftDeleteable = $this->getMockSoftDeletable();
        $mockSoftDeleteable->expects($this->once())
            ->method('isDeleted')
            ->will($this->returnValue(false));

        $dm = $this->getMockDocumentManager();
        $configuration = $this->getMockConfiguration();
        $uow = $this->getTestUnitOfWork($dm, $configuration);
        $uow->restore($mockSoftDeleteable);
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testRestoreThrowsExceptionIfAlreadyScheduledForRestore()
    {
        $mockSoftDeleteable = $this->getMockSoftDeletable();
        $mockSoftDeleteable->expects($this->any())
            ->method('isDeleted')
            ->will($this->returnValue(false));

        $dm = $this->getMockDocumentManager();
        $configuration = $this->getMockConfiguration();
        $uow = $this->getTestUnitOfWork($dm, $configuration);
        $uow->restore($mockSoftDeleteable);
        $uow->restore($mockSoftDeleteable);
    }

    private function getTestUnitOfWork(DocumentManager $dm, Configuration $configuration)
    {
        return new UnitOfWork($dm, $configuration);
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
}