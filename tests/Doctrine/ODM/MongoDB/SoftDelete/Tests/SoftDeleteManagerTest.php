<?php

namespace Doctrine\ODM\MongoDB\SoftDelete\Tests;

use PHPUnit_Framework_TestCase;
use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\ODM\MongoDB\SoftDelete\SoftDeleteManager;
use Doctrine\ODM\MongoDB\SoftDelete\Configuration;
use Doctrine\ODM\MongoDB\SoftDelete\UnitOfWork;

class SoftDeleteManagerTest extends PHPUnit_Framework_TestCase
{
    public function testConstructor()
    {
        $dm = $this->getMockDocumentManager();
        $configuration = $this->getConfiguration();
        $uow = $this->getMockUnitOfWork();
        $sdm = $this->getTestSoftDeleteManager($dm, $configuration, $uow);
        $this->assertSame($dm, $sdm->getDocumentManager());
    }

    public function testCreateQueryBuilder()
    {
        $mockQb = $this->getMockQb();

        $dm = $this->getMockDocumentManager();
        $dm->expects($this->once())
            ->method('createQueryBuilder')
            ->will($this->returnValue($mockQb));

        $mockQb->expects($this->once())
            ->method('field')
            ->with('deletedAt')
            ->will($this->returnValue($mockQb));

        $mockQb->expects($this->once())
            ->method('exists')
            ->with(false)
            ->will($this->returnValue($mockQb));

        $configuration = $this->getConfiguration();
        $uow = $this->getMockUnitOfWork();
        $sdm = $this->getTestSoftDeleteManager($dm, $configuration, $uow);

        $qb = $sdm->createQueryBuilder();
        $this->assertSame($mockQb, $qb);
    }

    public function testCreateDeletedQueryBuilder()
    {
        $mockQb = $this->getMockQb();

        $dm = $this->getMockDocumentManager();
        $dm->expects($this->once())
            ->method('createQueryBuilder')
            ->will($this->returnValue($mockQb));

        $mockQb->expects($this->once())
            ->method('field')
            ->with('deletedAt')
            ->will($this->returnValue($mockQb));

        $mockQb->expects($this->once())
            ->method('exists')
            ->with(true)
            ->will($this->returnValue($mockQb));

        $configuration = $this->getConfiguration();
        $uow = $this->getMockUnitOfWork();
        $sdm = $this->getTestSoftDeleteManager($dm, $configuration, $uow);

        $qb = $sdm->createDeletedQueryBuilder();
        $this->assertSame($mockQb, $qb);
    }

    public function testDelete()
    {
        $dm = $this->getMockDocumentManager();
        $configuration = $this->getConfiguration();
        $uow = $this->getMockUnitOfWork();

        $mockSoftDeleteable = $this->getMockSoftDeletable();

        $uow->expects($this->once())
            ->method('delete')
            ->with($mockSoftDeleteable);

        $sdm = $this->getTestSoftDeleteManager($dm, $configuration, $uow);

        $sdm->delete($mockSoftDeleteable);
    }

    public function testRestore()
    {
        $dm = $this->getMockDocumentManager();
        $configuration = $this->getConfiguration();
        $uow = $this->getMockUnitOfWork();

        $mockSoftDeleteable = $this->getMockSoftDeletable();

        $uow->expects($this->once())
            ->method('restore')
            ->with($mockSoftDeleteable);

        $sdm = $this->getTestSoftDeleteManager($dm, $configuration, $uow);

        $sdm->restore($mockSoftDeleteable);
    }

    public function testFlush()
    {
        $dm = $this->getMockDocumentManager();
        $configuration = $this->getConfiguration();
        $uow = $this->getMockUnitOfWork();

        $mockSoftDeleteable = $this->getMockSoftDeletable();

        $uow->expects($this->once())
            ->method('commit');

        $sdm = $this->getTestSoftDeleteManager($dm, $configuration, $uow);

        $sdm->flush();
    }

    public function testClear()
    {
        $dm = $this->getMockDocumentManager();
        $configuration = $this->getConfiguration();
        $uow = $this->getMockUnitOfWork();

        $mockSoftDeleteable = $this->getMockSoftDeletable();

        $uow->expects($this->once())
            ->method('clear');

        $sdm = $this->getTestSoftDeleteManager($dm, $configuration, $uow);

        $sdm->clear();
    }

    private function getTestSoftDeleteManager(DocumentManager $dm, Configuration $configuration, UnitOfWork $uow)
    {
        return new SoftDeleteManager($dm, $configuration, $uow);
    }

    private function getMockSoftDeletable()
    {
        return $this->getMockBuilder('Doctrine\ODM\MongoDB\SoftDelete\SoftDeleteable')
            ->disableOriginalConstructor()
            ->getMock();
    }

    private function getMockDocumentManager()
    {
        return $this->getMockBuilder('Doctrine\ODM\MongoDB\DocumentManager')
            ->disableOriginalConstructor()
            ->getMock();
    }

    private function getMockQb()
    {
        return $this->getMockBuilder('Doctrine\ODM\MongoDB\Query\Builder')
            ->disableOriginalConstructor()
            ->getMock();
    }

    private function getConfiguration()
    {
        return new Configuration();
    }

    private function getMockUnitOfWork()
    {
        return $this->getMockBuilder('Doctrine\ODM\MongoDB\SoftDelete\UnitOfWork')
            ->disableOriginalConstructor()
            ->getMock();
    }
}