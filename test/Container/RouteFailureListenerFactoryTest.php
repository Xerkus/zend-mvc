<?php
/**
 * @link      http://github.com/zendframework/zend-mvc for the canonical source repository
 * @copyright Copyright (c) 2005-2018 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-mvc/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace ZendTest\Mvc\Container;

use Zend\Mvc\Container\RouteFailureListenerFactory;
use PHPUnit\Framework\TestCase;
use Zend\Mvc\RouteFailureListener;
use ZendTest\Mvc\ContainerTrait;

/**
 * @covers \Zend\Mvc\Container\RouteFailureListenerFactory
 */
class RouteFailureListenerFactoryTest extends TestCase
{
    use ContainerTrait;

    public function testCreateListener()
    {
        $container = $this->mockContainerInterface();

        $factory = new RouteFailureListenerFactory();
        $listener = $factory->__invoke($container->reveal(), RouteFailureListener::class);
        $this->assertInstanceOf(RouteFailureListener::class, $listener);
    }
}
