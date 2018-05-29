<?php
/**
 * @link      http://github.com/zendframework/zend-mvc for the canonical source repository
 * @copyright Copyright (c) 2005-2018 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-mvc/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace ZendTest\Mvc\Container;

use Interop\Container\ContainerInterface;
use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;
use ReflectionProperty;
use Zend\EventManager\EventManager;
use Zend\EventManager\EventManagerInterface;
use Zend\EventManager\ListenerAggregateInterface;
use Zend\Mvc\Application;
use Zend\Mvc\ApplicationListenerAggregate;
use Zend\Mvc\Container\ApplicationFactory;
use PHPUnit\Framework\TestCase;
use ZendTest\Mvc\ContainerTrait;

/**
 * @covers \Zend\Mvc\Container\ApplicationFactory
 */
class ApplicationFactoryTest extends TestCase
{
    use ContainerTrait;

    /**
     * @var ObjectProphecy|ContainerInterface
     */
    private $container;

    /**
     * @var ApplicationFactory
     */
    private $factory;

    /**
     * @var EventManagerInterface
     */
    private $eventManager;

    public function setUp()
    {
        $this->container = $this->mockContainerInterface();

        $this->eventManager = new EventManager();
        $this->injectServiceInContainer($this->container, 'EventManager', $this->eventManager);

        $aggregate = new ApplicationListenerAggregate($this->container->reveal());
        $r = new ReflectionProperty($aggregate, 'defaultListeners');
        $r->setAccessible(true);
        $defaultListeners = $r->getValue($aggregate);
        foreach ($defaultListeners as $defaultListener) {
            $listener = $this->prophesize(ListenerAggregateInterface::class);
            $this->injectServiceInContainer($this->container, $defaultListener, $listener->reveal());
        }

        $this->factory = new ApplicationFactory();
    }

    public function testInjectsContainerIntoApplication()
    {
        $app = $this->factory->__invoke($this->container->reveal(), Application::class);

        $this->assertSame($this->container->reveal(), $app->getContainer());
    }

    public function testInjectsEventManagerIntoApplication()
    {
        $app = $this->factory->__invoke($this->container->reveal(), Application::class);

        $this->assertSame($this->eventManager, $app->getEventManager());
    }

    public function testAttachesDefaultListeners()
    {
        $aggregate = new ApplicationListenerAggregate($this->container->reveal());
        $r = new ReflectionProperty($aggregate, 'defaultListeners');
        $r->setAccessible(true);
        $defaultListeners = $r->getValue($aggregate);

        foreach ($defaultListeners as $defaultListener) {
            $listener = $this->prophesize(ListenerAggregateInterface::class);
            $listener->attach(Argument::type(EventManagerInterface::class))->shouldBeCalled();
            $this->injectServiceInContainer($this->container, $defaultListener, $listener->reveal());
        }
        $this->factory->__invoke($this->container->reveal(), Application::class);
    }

    public function testAttachesExtraListenersFromConfig()
    {
        $this->injectServiceInContainer($this->container, 'config', [
            Application::class => [
                'listeners' => ['extraListener'],
            ],
        ]);
        $listener = $this->prophesize(ListenerAggregateInterface::class);
        $listener->attach(Argument::type(EventManagerInterface::class))->shouldBeCalled();
        $this->injectServiceInContainer($this->container, 'extraListener', $listener->reveal());

        $this->factory->__invoke($this->container->reveal(), Application::class);
    }
}
