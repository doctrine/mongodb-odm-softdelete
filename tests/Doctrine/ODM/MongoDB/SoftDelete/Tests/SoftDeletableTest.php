<?php

namespace Doctrine\ODM\MongoDB\SoftDelete\Tests;

use Doctrine\ODM\MongoDB\SoftDelete\Configuration;
use PHPUnit_Framework_TestCase;
use DateTime;

class SoftDeletableTest extends PHPUnit_Framework_TestCase
{
    public function testSoftDeleteable()
    {
        $date = new DateTime();

        $mockSoftDeleteable = $this->getMockSoftDeleteable();
        $mockSoftDeleteable->expects($this->once())
            ->method('getDeletedAt')
            ->will($this->returnValue($date));

        $mockSoftDeleteable->expects($this->once())
            ->method('isDeleted')
            ->will($this->returnValue(true));

        $this->assertSame($date, $mockSoftDeleteable->getDeletedAt());
        $this->assertTrue($mockSoftDeleteable->isDeleted());
    }

    private function getMockSoftDeleteable()
    {
        return $this->getMockBuilder('Doctrine\ODM\MongoDB\SoftDelete\SoftDeleteable')
            ->disableOriginalConstructor()
            ->getMock();
    }
}