<?php
/**
 * @link      http://github.com/zendframework/zend-mvc for the canonical source repository
 * @copyright Copyright (c) 2005-2018 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-mvc/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace ZendTest\Mvc\Container;

use Zend\EventManager\SharedEventManager;
use Zend\Mvc\Container\SharedEventManagerFactory;
use PHPUnit\Framework\TestCase;
use ZendTest\Mvc\ContainerTrait;

/**
 * @covers \Zend\Mvc\Container\SharedEventManagerFactory
 */
class SharedEventManagerFactoryTest extends TestCase
{
    use ContainerTrait;

    public function testCreatesSharedEventManager()
    {
        $container = $this->mockContainerInterface();
        $factory = new SharedEventManagerFactory();
        $sem = $factory->__invoke($container->reveal(), SharedEventManager::class);
        $this->assertInstanceOf(SharedEventManager::class, $sem);
    }
}
