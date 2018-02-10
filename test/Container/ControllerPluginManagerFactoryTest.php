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
use Zend\Mvc\Container\ControllerPluginManagerFactory;
use Zend\Mvc\Controller\Plugin\PluginInterface;
use Zend\Mvc\Controller\PluginManager;
use ZendTest\Mvc\ContainerTrait;

/**
 * @covers \Zend\Mvc\Container\ControllerPluginManagerFactory
 */
class ControllerPluginManagerFactoryTest extends TestCase
{
    use ContainerTrait;

    /**
     * @var ObjectProphecy|ContainerInterface
     */
    private $container;

    /**
     * @var ControllerPluginManagerFactory
     */
    private $factory;

    public function setUp()
    {
        $this->container = $this->mockContainerInterface();
        $this->factory = new ControllerPluginManagerFactory();
    }

    public function testInjectsContainerIntoControllerPluginManager()
    {
        $container = $this->container->reveal();
        $pluginManager = $this->factory->__invoke($container, PluginManager::class);
        $pluginManager->setFactory('Foo', function ($injectedContainer) use ($container) {
            $this->assertSame($container, $injectedContainer);
            return $this->prophesize(PluginInterface::class)->reveal();
        });
        $pluginManager->get('Foo');
    }

    public function testPullsControllerPluginsConfigFromConfigService()
    {
        $this->injectServiceInContainer($this->container, 'config', [
            'controller_plugins' => [
                'factories' => [
                    'Foo' => 'FooFactory',
                ]
            ]
        ]);
        $pluginManager = $this->factory->__invoke($this->container->reveal(), PluginManager::class);
        $this->assertTrue($pluginManager->has('Foo'));
    }

    public function testUsesProvidedOptionsInsteadOfConfigFromContainer()
    {
        $this->injectServiceInContainer($this->container, 'config', [
            'controller_plugins' => [
                'factories' => [
                    'Foo' => 'FooFactory',
                ]
            ]
        ]);
        $pluginManager = $this->factory->__invoke(
            $this->container->reveal(),
            PluginManager::class,
            ['factories' => ['Bar' => 'BarFactory']]
        );
        $this->assertFalse($pluginManager->has('Foo'));
        $this->assertTrue($pluginManager->has('Bar'));
    }

    public function testHelperMethodPullsControllerPluginsConfigFromMainConfig()
    {
        $config = [
            'factories' => [
                'Foo' => 'FooFactory',
            ],
        ];
        $this->injectServiceInContainer($this->container, 'config', [
            'controller_plugins' => $config,
        ]);

        $obtainedConfig = $this->factory::getControllerPluginsConfig($this->container->reveal());
        $this->assertEquals($config, $obtainedConfig);
    }

    public function testHelperMethodReturnsEmptyArrayOnMissingControllerPluginsConfig()
    {
        $this->injectServiceInContainer($this->container, 'config', []);

        $obtainedConfig = $this->factory::getControllerPluginsConfig($this->container->reveal());
        $this->assertEmpty($obtainedConfig);
    }

    public function testHelperMethodReturnsEmptyArrayOnMissingConfigService()
    {
        $this->assertFalse($this->container->reveal()->has('config'));

        $obtainedConfig = $this->factory::getControllerPluginsConfig($this->container->reveal());
        $this->assertEmpty($obtainedConfig);
    }
}
