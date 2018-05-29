<?php
/**
 * @link      http://github.com/zendframework/zend-mvc for the canonical source repository
 * @copyright Copyright (c) 2005-2018 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-mvc/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace ZendTest\Mvc;

use Psr\Container\ContainerInterface;
use ReflectionProperty;
use stdClass;
use Zend\EventManager\EventManager;
use Zend\EventManager\EventManagerInterface;
use Zend\EventManager\ListenerAggregateInterface;
use Zend\EventManager\SharedEventManager;
use Zend\Mvc\ApplicationListenerAggregate;
use PHPUnit\Framework\TestCase;
use Zend\Mvc\DispatchListener;
use Zend\Mvc\Exception\DomainException;
use Zend\Mvc\HttpMethodListener;
use Zend\Mvc\RouteFailureListener;
use Zend\Mvc\RouteListener;
use Zend\Mvc\View\Http\ViewManager;

/**
 * @covers \Zend\Mvc\ApplicationListenerAggregate
 */
class ApplicationListenerAggregateTest extends TestCase
{
    use ContainerTrait;

    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * @var EventManagerInterface
     */
    private $events;

    /**
     * @var array
     */
    private $listeners = [];

    /**
     * @var ApplicationListenerAggregate
     */
    private $aggregate;

    protected function setUp()
    {
        $this->container = $this->mockContainerInterface();
        $this->events = new EventManager(new SharedEventManager());

        $route = $this->prophesize(RouteListener::class);
        $this->injectServiceInContainer($this->container, RouteListener::class, $route->reveal());
        $this->listeners[RouteListener::class] = $route;

        $routeFailure = $this->prophesize(RouteFailureListener::class);
        $this->injectServiceInContainer($this->container, RouteFailureListener::class, $routeFailure->reveal());
        $this->listeners[RouteFailureListener::class] = $routeFailure;

        $dispatch = $this->prophesize(DispatchListener::class);
        $this->injectServiceInContainer($this->container, DispatchListener::class, $dispatch->reveal());
        $this->listeners[DispatchListener::class] = $dispatch;

        $viewManager = $this->prophesize(ViewManager::class);
        $this->injectServiceInContainer($this->container, ViewManager::class, $viewManager->reveal());
        $this->listeners[ViewManager::class] = $viewManager;

        $httpMethod = $this->prophesize(HttpMethodListener::class);
        $this->injectServiceInContainer($this->container, HttpMethodListener::class, $httpMethod->reveal());
        $this->listeners[HttpMethodListener::class] = $httpMethod;

        $this->aggregate  = new ApplicationListenerAggregate($this->container->reveal(), []);
    }

    public function testFetchesFromContainerAndAttachesDefaultListeners()
    {
        $r = new ReflectionProperty($this->aggregate, 'defaultListeners');
        $r->setAccessible(true);
        $defaultListeners = $r->getValue($this->aggregate);

        foreach ($defaultListeners as $defaultListener) {
            $this->listeners[$defaultListener]->attach($this->events)->shouldBeCalled();
        }

        $this->aggregate->attach($this->events);
    }

    public function testAttachesExtraListeners()
    {
        $custom = $this->prophesize(ListenerAggregateInterface::class);
        $custom->attach($this->events)->shouldBeCalled();

        $aggregate = new ApplicationListenerAggregate(
            $this->container->reveal(),
            [$custom->reveal()]
        );

        $aggregate->attach($this->events);
    }

    public function testExtraListenersSpecifiedAsStringsArePulledFromContainer()
    {
        $custom = $this->prophesize(ListenerAggregateInterface::class);
        $custom->attach($this->events)->shouldBeCalled();

        $this->injectServiceInContainer($this->container, 'customListener', $custom->reveal());

        $aggregate = new ApplicationListenerAggregate(
            $this->container->reveal(),
            ['customListener']
        );

        $aggregate->attach($this->events);
    }

    public function testExtraListenersAttachedInAdditionToDefault()
    {
        $r = new ReflectionProperty($this->aggregate, 'defaultListeners');
        $r->setAccessible(true);
        $defaultListeners = $r->getValue($this->aggregate);

        foreach ($defaultListeners as $defaultListener) {
            $this->listeners[$defaultListener]->attach($this->events)->shouldBeCalled();
        }

        $custom = $this->prophesize(ListenerAggregateInterface::class);
        $custom->attach($this->events)->shouldBeCalled();


        $aggregate = new ApplicationListenerAggregate(
            $this->container->reveal(),
            [$custom->reveal()]
        );

        $aggregate->attach($this->events);
    }

    public function testThrowsOnInvalidListener()
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Invalid listener aggregate provided');

        $aggregate = new ApplicationListenerAggregate(
            $this->container->reveal(),
            [new stdClass()]
        );

        $aggregate->attach($this->events);
    }

    public function testDetach()
    {
        $custom = $this->prophesize(ListenerAggregateInterface::class);

        $aggregate = new ApplicationListenerAggregate(
            $this->container->reveal(),
            [$custom->reveal()]
        );
        $aggregate->attach($this->events);

        $r = new ReflectionProperty($this->aggregate, 'defaultListeners');
        $r->setAccessible(true);
        $defaultListeners = $r->getValue($this->aggregate);

        foreach ($defaultListeners as $defaultListener) {
            $this->listeners[$defaultListener]->detach($this->events)->shouldBeCalled();
        }
        $custom->detach($this->events)->shouldBeCalled();

        $aggregate->detach($this->events);
    }
}
