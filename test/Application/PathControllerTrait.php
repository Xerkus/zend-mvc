<?php
/**
 * @link      http://github.com/zendframework/zend-mvc for the canonical source repository
 * @copyright Copyright (c) 2005-2018 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-mvc/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace ZendTest\Mvc\Application;

use Zend\Http\PhpEnvironment\Request;
use Zend\Http\PhpEnvironment\Response;
use Zend\Mvc\ConfigProvider;
use Zend\Mvc\Controller\ControllerManager;
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
                        'type' => Router\Http\Literal::class,
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

        $serviceConfig = ArrayUtils::merge(
            (new ConfigProvider())->getDependencyConfig(),
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
                ],
                'invokables' => [
                    'Request'              => Request::class,
                    'Response'             => Response::class,
                    'ViewManager'          => TestAsset\MockViewManager::class,
                    'BootstrapListener'    => TestAsset\StubBootstrapListener::class,
                ],
                'services' => [
                    'config' => $config,
                ],
            ]
        );
        $services = new ServiceManager($serviceConfig);
        $application = $services->get('Application');

        $request = $services->get('Request');
        $request->setUri('http://example.local/path');

        $application->bootstrap();
        return $application;
    }
}
