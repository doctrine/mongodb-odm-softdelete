<?php

require_once __DIR__ . '/../lib/vendor/doctrine-common/lib/Doctrine/Common/ClassLoader.php';

use Doctrine\Common\ClassLoader;

$classLoader = new ClassLoader('Doctrine\ODM\MongoDB\SoftDelete\Tests', __DIR__);
$classLoader->register();

$classLoader = new ClassLoader('Doctrine\ODM\MongoDB\SoftDelete', __DIR__ . '/../lib');
$classLoader->register();

$classLoader = new ClassLoader('Doctrine\ODM\MongoDB', __DIR__ . '/../lib/vendor/doctrine-mongodb-odm/lib');
$classLoader->register();

$classLoader = new ClassLoader('Doctrine\MongoDB', __DIR__ . '/../lib/vendor/doctrine-mongodb/lib');
$classLoader->register();

$classLoader = new ClassLoader('Doctrine\Common', __DIR__ . '/../lib/vendor/doctrine-common/lib');
$classLoader->register();