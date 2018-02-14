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
use Zend\EventManager\EventManager;
use Zend\EventManager\EventManagerInterface;
use Zend\Mvc\Application;
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

    public function testInjectsExtraListenersFromConfig()
    {
        $listeners = ['Foo', 'Bar'];
        $this->injectServiceInContainer($this->container, 'config', [
            Application::class => [
                'listeners' => $listeners,
            ],
        ]);
        $app = $this->factory->__invoke($this->container->reveal(), Application::class);

        $r = new ReflectionProperty($app, 'extraListeners');
        $r->setAccessible(true);
        $this->assertEquals($listeners, $r->getValue($app));
    }
}
