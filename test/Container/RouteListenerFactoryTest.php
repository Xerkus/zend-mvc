<?php
/**
 * @link      http://github.com/zendframework/zend-mvc for the canonical source repository
 * @copyright Copyright (c) 2005-2018 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-mvc/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace ZendTest\Mvc\Container;

use Interop\Container\ContainerInterface;
use Prophecy\Prophecy\ObjectProphecy;
use ReflectionProperty;
use Zend\Mvc\Container\RouteListenerFactory;
use PHPUnit\Framework\TestCase;
use Zend\Mvc\RouteListener;
use Zend\Router\RouteStackInterface;
use ZendTest\Mvc\ContainerTrait;

/**
 * @covers \Zend\Mvc\Container\RouteListenerFactory
 */
class RouteListenerFactoryTest extends TestCase
{
    use ContainerTrait;

    /**
     * @var ObjectProphecy|ContainerInterface
     */
    private $container;

    /**
     * @var RouteListenerFactory
     */
    private $factory;

    /**
     * @var ObjectProphecy|RouteStackInterface
     */
    private $router;

    public function setUp()
    {
        $this->router = $this->prophesize(RouteStackInterface::class);
        $this->container = $this->mockContainerInterface();
        $this->injectServiceInContainer($this->container, 'Zend\Mvc\Router', $this->router->reveal());

        $this->factory = new RouteListenerFactory();
    }

    public function testInjectsRouter()
    {
        $routeListener = $this->factory->__invoke($this->container->reveal(), RouteListener::class);

        $r = new ReflectionProperty($routeListener, 'router');
        $r->setAccessible(true);
        $this->assertSame($this->router->reveal(), $r->getValue($routeListener));
    }
}
