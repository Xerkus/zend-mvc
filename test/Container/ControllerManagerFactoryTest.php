<?php
/**
 * @link      http://github.com/zendframework/zend-mvc for the canonical source repository
 * @copyright Copyright (c) 2005-2018 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-mvc/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace ZendTest\Mvc\Container;

use Interop\Container\ContainerInterface;
use PHPUnit\Framework\TestCase;
use Prophecy\Prophecy\ObjectProphecy;
use Zend\Mvc\Container\ControllerManagerFactory;
use Zend\Mvc\Controller\ControllerInterface;
use Zend\Mvc\Controller\ControllerManager;
use ZendTest\Mvc\ContainerTrait;

/**
 * @covers \Zend\Mvc\Container\ControllerManagerFactory
 */
class ControllerManagerFactoryTest extends TestCase
{
    use ContainerTrait;

    /**
     * @var ObjectProphecy|ContainerInterface
     */
    private $container;

    /**
     * @var ControllerManagerFactory
     */
    private $factory;

    public function setUp()
    {
        $this->container = $this->mockContainerInterface();
        $this->factory = new ControllerManagerFactory();
    }

    public function testInjectsContainerIntoControllerManager()
    {
        $container = $this->container->reveal();
        $controllerManager = $this->factory->__invoke($container, ControllerManager::class);
        $controllerManager->setFactory('Foo', function ($injectedContainer) use ($container) {
            $this->assertSame($container, $injectedContainer);
            return $this->prophesize(ControllerInterface::class)->reveal();
        });
        $controllerManager->get('Foo');
    }

    public function testPullsControllersConfigFromConfigService()
    {
        $this->injectServiceInContainer($this->container, 'config', [
            'controllers' => [
                'factories' => [
                    'Foo' => 'FooFactory',
                ]
            ]
        ]);
        $controllerManager = $this->factory->__invoke($this->container->reveal(), ControllerManager::class);
        $this->assertTrue($controllerManager->has('Foo'));
    }

    public function testUsesProvidedOptionsInsteadOfConfigFromContainer()
    {
        $this->injectServiceInContainer($this->container, 'config', [
            'controllers' => [
                'factories' => [
                    'Foo' => 'FooFactory',
                ]
            ]
        ]);
        $controllerManager = $this->factory->__invoke(
            $this->container->reveal(),
            ControllerManager::class,
            ['factories' => ['Bar' => 'BarFactory']]
        );
        $this->assertFalse($controllerManager->has('Foo'));
        $this->assertTrue($controllerManager->has('Bar'));
    }

    public function testHelperMethodPullsControllerConfigFromMainConfig()
    {
        $config = [
            'factories' => [
                'Foo' => 'FooFactory',
            ],
        ];
        $this->injectServiceInContainer($this->container, 'config', [
            'controllers' => $config,
        ]);

        $obtainedConfig = $this->factory::getControllersConfig($this->container->reveal());
        $this->assertEquals($config, $obtainedConfig);
    }

    public function testHelperMethodReturnsEmptyArrayOnMissingControllersConfig()
    {
        $this->injectServiceInContainer($this->container, 'config', []);

        $obtainedConfig = $this->factory::getControllersConfig($this->container->reveal());
        $this->assertEmpty($obtainedConfig);
    }

    public function testHelperMethodReturnsEmptyArrayOnMissingConfigService()
    {
        $this->assertFalse($this->container->reveal()->has('config'));

        $obtainedConfig = $this->factory::getControllersConfig($this->container->reveal());
        $this->assertEmpty($obtainedConfig);
    }
}
