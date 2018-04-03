<?php
/**
 * @link      http://github.com/zendframework/zend-mvc for the canonical source repository
 * @copyright Copyright (c) 2005-2018 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-mvc/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace ZendTest\Mvc\Container;

use Psr\Http\Message\UriInterface;
use Zend\Diactoros\Uri;
use Zend\Mvc\Container\RouterFactory;
use PHPUnit\Framework\TestCase;
use Zend\Router\Route\Literal;
use Zend\Router\RouteConfigFactory;
use Zend\Router\RoutePluginManager;
use Zend\Router\TreeRouteStack;
use Zend\ServiceManager\ServiceManager;
use ZendTest\Mvc\ContainerTrait;

/**
 * @covers \Zend\Mvc\Container\RouterFactory
 */
class RouterFactoryTest extends TestCase
{
    use ContainerTrait;

    public function testCreatesAndConfiguresRouterFromConfig()
    {
        $container = $this->mockContainerInterface();

        $config = [
            'router' => [
                'routes' => [
                    'foo' => [
                        'type' => Literal::class,
                        'options' => [
                            'route' => '/',
                        ],
                    ],
                ],
            ],
        ];
        $this->injectServiceInContainer($container, 'config', $config);

        $configFactory = new RouteConfigFactory(new RoutePluginManager(new ServiceManager()));
        $this->injectServiceInContainer($container, RouteConfigFactory::class, $configFactory);

        $uriFactory = function (string $uri = null) {
            return new Uri($uri);
        };
        $this->injectServiceInContainer($container, UriInterface::class, $uriFactory);

        $factory = new RouterFactory();

        $router = $factory->__invoke($container->reveal(), 'Zend\Mvc\Router');
        $this->assertInstanceOf(TreeRouteStack::class, $router->getRouteStack());
        $this->assertInstanceOf(Literal::class, $router->getRouteStack()->getRoute('foo'));
    }
}
