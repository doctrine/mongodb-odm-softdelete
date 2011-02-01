<?php

namespace Doctrine\ODM\MongoDB\SoftDelete\Tests;

use PHPUnit_Framework_TestCase;
use Doctrine\ODM\MongoDB\SoftDelete\Configuration;

class ConfigurationTest extends PHPUnit_Framework_TestCase
{
    public function testDefaultDeletedFieldName()
    {
        $configuration = new Configuration();
        $this->assertEquals('deletedAt', $configuration->getDeletedFieldName());
    }

    public function testSetDeletedFieldName()
    {
        $configuration = new Configuration();
        $configuration->setDeletedFieldName('test');
        $this->assertEquals('test', $configuration->getDeletedFieldName());
    }
}