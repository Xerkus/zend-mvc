<?php
/**
 * @link      http://github.com/zendframework/zend-mvc for the canonical source repository
 * @copyright Copyright (c) 2005-2018 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-mvc/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace ZendTest\Mvc\Application;

use ReflectionProperty;
use Zend\Diactoros\Response\EmitterInterface;
use Zend\Mvc\Application;
use Zend\Mvc\Controller\ControllerManager;
use Zend\Mvc\Container\ServiceManagerConfig;
use Zend\Mvc\Container\ServiceListenerFactory;
use Zend\Router;
use Zend\ServiceManager\ServiceManager;
use Zend\Stdlib\ArrayUtils;
use ZendTest\Mvc\TestAsset;

trait PathControllerTrait
{
    public function prepareApplication()
    {
        $config = [
            'router' => [
                'routes' => [
                    'path' => [
                        'type' => Router\Route\Literal::class,
                        'options' => [
                            'route' => '/path',
                            'defaults' => [
                                'controller' => 'path',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $serviceListener = new ServiceListenerFactory();
        $r = new ReflectionProperty($serviceListener, 'defaultServiceConfig');
        $r->setAccessible(true);
        $serviceConfig = $r->getValue($serviceListener);

        $serviceConfig = ArrayUtils::merge(
            $serviceConfig,
            (new Router\ConfigProvider())->getDependencyConfig()
        );

        $serviceConfig = ArrayUtils::merge(
            $serviceConfig,
            [
                'aliases' => [
                    'ControllerLoader'  => ControllerManager::class,
                    'ControllerManager' => ControllerManager::class,
                ],
                'factories' => [
                    ControllerManager::class => function ($services) {
                        return new ControllerManager($services, ['factories' => [
                            'path' => function () {
                                return new TestAsset\PathController();
                            },
                        ]]);
                    },
                    'Router' => function ($services) {
                        return $services->get('HttpRouter');
                    },
                    EmitterInterface::class => function () {
                        $emitter = $this->prophesize(EmitterInterface::class);
                        return $emitter->reveal();
                    },
                ],
                'invokables' => [
                    'ViewManager'          => TestAsset\MockViewManager::class,
                    'BootstrapListener'    => TestAsset\StubBootstrapListener::class,
                ],
                'services' => [
                    'config' => $config,
                    'ApplicationConfig' => [
                        'modules'                 => [
                            'Zend\Router',
                        ],
                        'module_listener_options' => [
                            'config_cache_enabled' => false,
                            'cache_dir'            => 'data/cache',
                            'module_paths'         => [],
                        ],
                    ],
                ],
            ]
        );
        $services = new ServiceManager();
        (new ServiceManagerConfig($serviceConfig))->configureServiceManager($services);
        $application = $services->get('Application');

        $application->bootstrap();
        return $application;
    }
}
