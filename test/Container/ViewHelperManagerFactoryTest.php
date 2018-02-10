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
use Zend\Http\PhpEnvironment\Request;
use Zend\Mvc\Application;
use Zend\Mvc\Container\ViewHelperManagerFactory;
use Zend\Mvc\MvcEvent;
use Zend\Router\RouteMatch;
use Zend\Router\RouteStackInterface;
use Zend\ServiceManager\ServiceManager;
use Zend\View\Helper;
use Zend\View\HelperPluginManager;
use ZendTest\Mvc\ContainerTrait;

use function array_unshift;
use function is_callable;

/**
 * @covers \Zend\Mvc\Container\ViewHelperManagerFactory
 */
class ViewHelperManagerFactoryTest extends TestCase
{
    use ContainerTrait;

    /**
     * @var ObjectProphecy|ContainerInterface
     */
    private $container;

    /**
     * @var ViewHelperManagerFactory
     */
    private $factory;

    public function setUp()
    {
        $this->services = new ServiceManager();
        $this->container = $this->mockContainerInterface();
        $this->factory = new ViewHelperManagerFactory();
    }

    public function testInjectsContainerIntoViewHelperManager()
    {
        $container = $this->container->reveal();
        $pluginManager = $this->factory->__invoke($container, HelperPluginManager::class);
        $pluginManager->setFactory('Foo', function ($injectedContainer) use ($container) {
            $this->assertSame($container, $injectedContainer);
            return $this->prophesize(Helper\HelperInterface::class)->reveal();
        });
        $pluginManager->get('Foo');
    }

    public function testPullsViewHelpersConfigFromConfigService()
    {
        $this->injectServiceInContainer($this->container, 'config', [
            'view_helpers' => [
                'factories' => [
                    'Foo' => 'FooFactory',
                ]
            ]
        ]);
        $pluginManager = $this->factory->__invoke($this->container->reveal(), HelperPluginManager::class);
        $this->assertTrue($pluginManager->has('Foo'));
    }

    public function testUsesProvidedOptionsInsteadOfConfigFromContainer()
    {
        $this->injectServiceInContainer($this->container, 'config', [
            'view_helpers' => [
                'factories' => [
                    'Foo' => 'FooFactory',
                ]
            ]
        ]);
        $pluginManager = $this->factory->__invoke(
            $this->container->reveal(),
            HelperPluginManager::class,
            ['factories' => ['Bar' => 'BarFactory']]
        );
        $this->assertFalse($pluginManager->has('Foo'));
        $this->assertTrue($pluginManager->has('Bar'));
    }

    public function testHelperMethodPullsViewHelpersConfigFromMainConfig()
    {
        $config = [
            'factories' => [
                'Foo' => 'FooFactory',
            ],
        ];
        $this->injectServiceInContainer($this->container, 'config', [
            'view_helpers' => $config,
        ]);

        $obtainedConfig = $this->factory::getViewHelpersConfig($this->container->reveal());
        $this->assertEquals($config, $obtainedConfig);
    }

    public function testHelperMethodReturnsEmptyArrayOnMissingViewHelpersConfig()
    {
        $this->injectServiceInContainer($this->container, 'config', []);

        $obtainedConfig = $this->factory::getViewHelpersConfig($this->container->reveal());
        $this->assertEmpty($obtainedConfig);
    }

    public function testHelperMethodReturnsEmptyArrayOnMissingConfigService()
    {
        $this->assertFalse($this->container->reveal()->has('config'));

        $obtainedConfig = $this->factory::getViewHelpersConfig($this->container->reveal());
        $this->assertEmpty($obtainedConfig);
    }

    /**
     * @return array
     */
    public function emptyConfiguration()
    {
        return [
            'no-config'                => [[]],
            'view-manager-config-only' => [['view_manager' => []]],
            'empty-doctype-config'     => [['view_manager' => ['doctype' => null]]],
        ];
    }

    /**
     * @dataProvider emptyConfiguration
     * @param  array $config
     * @return void
     */
    public function testDoctypeFactoryDoesNotRaiseErrorOnMissingConfiguration($config)
    {
        $this->services->setService('config', $config);
        $manager = $this->factory->__invoke($this->services, 'doctype');
        $this->assertInstanceof(HelperPluginManager::class, $manager);
        $doctype = $manager->get('doctype');
        $this->assertInstanceof(Helper\Doctype::class, $doctype);
    }

    public function urlHelperNames()
    {
        return [
            ['url'],
            ['Url'],
            [Helper\Url::class],
            ['zendviewhelperurl'],
        ];
    }

    /**
     * @group 71
     * @dataProvider urlHelperNames
     */
    public function testUrlHelperFactoryCanBeInvokedViaShortNameOrFullClassName($name)
    {
        $routeMatch = $this->prophesize(RouteMatch::class)->reveal();
        $mvcEvent = $this->prophesize(MvcEvent::class);
        $mvcEvent->getRouteMatch()->willReturn($routeMatch);

        $application = $this->prophesize(Application::class);
        $application->getMvcEvent()->willReturn($mvcEvent->reveal());

        $router = $this->prophesize(RouteStackInterface::class)->reveal();

        $this->services->setService('HttpRouter', $router);
        $this->services->setService('Router', $router);
        $this->services->setService('Application', $application->reveal());
        $this->services->setService('config', []);

        $manager = $this->factory->__invoke($this->services, HelperPluginManager::class);
        $helper = $manager->get($name);

        $this->assertAttributeSame($routeMatch, 'routeMatch', $helper, 'Route match was not injected');
        $this->assertAttributeSame($router, 'router', $helper, 'Router was not injected');
    }

    public function basePathConfiguration()
    {
        $names = ['basepath', 'basePath', 'BasePath', Helper\BasePath::class, 'zendviewhelperbasepath'];

        $configurations = [
            'hard-coded' => [[
                'config' => [
                    'view_manager' => [
                        'base_path' => '/foo/baz',
                    ],
                ],
            ], '/foo/baz'],

            'request-base' => [[
                'config' => [], // fails creating plugin manager without this
                'Request' => function () {
                    $request = $this->prophesize(Request::class);
                    $request->getBasePath()->willReturn('/foo/bat');
                    return $request->reveal();
                },
            ], '/foo/bat'],
        ];

        foreach ($names as $name) {
            foreach ($configurations as $testcase => $arguments) {
                array_unshift($arguments, $name);
                $testcase .= '-' . $name;
                yield $testcase => $arguments;
            }
        }
    }

    /**
     * @group 71
     * @dataProvider basePathConfiguration
     */
    public function testBasePathHelperFactoryCanBeInvokedViaShortNameOrFullClassName($name, array $services, $expected)
    {
        foreach ($services as $key => $value) {
            if (is_callable($value)) {
                $this->services->setFactory($key, $value);
                continue;
            }

            $this->services->setService($key, $value);
        }

        $plugins = $this->factory->__invoke($this->services, HelperPluginManager::class);
        $helper = $plugins->get($name);
        $this->assertInstanceof(Helper\BasePath::class, $helper);
        $this->assertEquals($expected, $helper());
    }

    public function doctypeHelperNames()
    {
        return [
            ['doctype'],
            ['Doctype'],
            [Helper\Doctype::class],
            ['zendviewhelperdoctype'],
        ];
    }

    /**
     * @group 71
     * @dataProvider doctypeHelperNames
     */
    public function testDoctypeHelperFactoryCanBeInvokedViaShortNameOrFullClassName($name)
    {
        $this->services->setService('config', [
            'view_manager' => [
                'doctype' => Helper\Doctype::HTML5,
            ],
        ]);

        $plugins = $this->factory->__invoke($this->services, HelperPluginManager::class);
        $helper = $plugins->get($name);
        $this->assertInstanceof(Helper\Doctype::class, $helper);
        $this->assertEquals('<!DOCTYPE html>', (string) $helper);
    }
}
