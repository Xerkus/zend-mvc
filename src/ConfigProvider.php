<?php
/**
 * @link      http://github.com/zendframework/zend-mvc for the canonical source repository
 * @copyright Copyright (c) 2005-2017 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-mvc/blob/master/LICENSE.md New BSD License
 */
declare(strict_types=1);

namespace Zend\Mvc;

use Zend\EventManager\EventManager;
use Zend\EventManager\EventManagerInterface;
use Zend\EventManager\SharedEventManager;
use Zend\EventManager\SharedEventManagerInterface;
use Zend\Mvc\Container\ApplicationFactory;
use Zend\Mvc\Container\ControllerManagerFactory;
use Zend\Mvc\Container\ControllerPluginManagerFactory;
use Zend\Mvc\Container\DefaultRenderingStrategyFactory;
use Zend\Mvc\Container\DispatchListenerFactory;
use Zend\Mvc\Container\EventManagerFactory;
use Zend\Mvc\Container\ExceptionStrategyFactory;
use Zend\Mvc\Container\HttpMethodListenerFactory;
use Zend\Mvc\Container\HttpViewManagerFactory;
use Zend\Mvc\Container\InjectTemplateListenerFactory;
use Zend\Mvc\Container\PaginatorPluginManagerFactory;
use Zend\Mvc\Container\RequestFactory;
use Zend\Mvc\Container\ResponseFactory;
use Zend\Mvc\Container\RouteNotFoundStrategyFactory;
use Zend\Mvc\Container\SendResponseListenerFactory;
use Zend\Mvc\Container\SharedEventManagerFactory;
use Zend\Mvc\Container\ViewFactory;
use Zend\Mvc\Container\ViewFeedStrategyFactory;
use Zend\Mvc\Container\ViewHelperManagerFactory;
use Zend\Mvc\Container\ViewJsonStrategyFactory;
use Zend\Mvc\Container\ViewManagerFactory;
use Zend\Mvc\Container\ViewPhpRendererFactory;
use Zend\Mvc\Container\ViewPhpRendererStrategyFactory;
use Zend\Mvc\Container\ViewPrefixPathStackResolverFactory;
use Zend\Mvc\Container\ViewResolverFactory;
use Zend\Mvc\Container\ViewTemplateMapResolverFactory;
use Zend\Mvc\Container\ViewTemplatePathStackFactory;
use Zend\Mvc\Controller\PluginManager;
use Zend\Mvc\View\Http\DefaultRenderingStrategy;
use Zend\Mvc\View\Http\InjectTemplateListener;
use Zend\ServiceManager\Factory\InvokableFactory;
use Zend\View\Renderer\FeedRenderer;
use Zend\View\Renderer\JsonRenderer;
use Zend\View\Renderer\PhpRenderer;
use Zend\View\Renderer\RendererInterface;
use Zend\View\Resolver\AggregateResolver;
use Zend\View\Resolver\ResolverInterface;
use Zend\View\Resolver\TemplateMapResolver;
use Zend\View\Resolver\TemplatePathStack;
use Zend\View\Strategy\PhpRendererStrategy;
use Zend\View\View;

class ConfigProvider
{
    public function __invoke() : array
    {
        return [
            'dependencies' => $this->getDependencyConfig(),
        ];
    }

    public function getDependencyConfig() : array
    {
        return [
            'aliases' => [
                'application' => 'Application',
                'EventManagerInterface' => EventManager::class,
                'HttpDefaultRenderingStrategy' => DefaultRenderingStrategy::class,
                'MiddlewareListener' => MiddlewareListener::class,
                'request' => 'Request',
                'response' => 'Response',
                'RouteListener' => RouteListener::class,
                'SendResponseListener' => SendResponseListener::class,
                'SharedEventManagerInterface' => 'SharedEventManager',
                'View' => View::class,
                'ViewFeedRenderer' => FeedRenderer::class,
                'ViewJsonRenderer' => JsonRenderer::class,
                'ViewPhpRenderer' => PhpRenderer::class,
                'ViewPhpRendererStrategy' => PhpRendererStrategy::class,
                'ViewRenderer' => PhpRenderer::class,
                AggregateResolver::class => 'ViewResolver',
                EventManagerInterface::class => 'EventManager',
                InjectTemplateListener::class => 'InjectTemplateListener',
                PluginManager::class => 'ControllerPluginManager',
                RendererInterface::class => PhpRenderer::class,
                ResolverInterface::class => 'ViewResolver',
                SharedEventManager::class => 'SharedEventManager',
                SharedEventManagerInterface::class => 'SharedEventManager',
                TemplateMapResolver::class => 'ViewTemplateMapResolver',
                TemplatePathStack::class => 'ViewTemplatePathStack',
            ],
            'factories' => [
                'Application' => ApplicationFactory::class,
                'ControllerManager' => ControllerManagerFactory::class,
                'ControllerPluginManager' => ControllerPluginManagerFactory::class,
                'DispatchListener' => DispatchListenerFactory::class,
                'EventManager' => EventManagerFactory::class,
                'HttpExceptionStrategy' => ExceptionStrategyFactory::class,
                'HttpMethodListener' => HttpMethodListenerFactory::class,
                'HttpRouteNotFoundStrategy' => RouteNotFoundStrategyFactory::class,
                'HttpViewManager' => HttpViewManagerFactory::class,
                'InjectTemplateListener' => InjectTemplateListenerFactory::class,
                'PaginatorPluginManager' => PaginatorPluginManagerFactory::class,
                'Request' => RequestFactory::class,
                'Response' => ResponseFactory::class,
                'SharedEventManager' => SharedEventManagerFactory::class,
                'ViewFeedStrategy' => ViewFeedStrategyFactory::class,
                'ViewHelperManager' => ViewHelperManagerFactory::class,
                'ViewJsonStrategy' => ViewJsonStrategyFactory::class,
                'ViewManager' => ViewManagerFactory::class,
                'ViewPrefixPathStackResolver' => ViewPrefixPathStackResolverFactory::class,
                'ViewResolver' => ViewResolverFactory::class,
                'ViewTemplateMapResolver' => ViewTemplateMapResolverFactory::class,
                'ViewTemplatePathStack' => ViewTemplatePathStackFactory::class,
                DefaultRenderingStrategy::class => DefaultRenderingStrategyFactory::class,
                FeedRenderer::class => InvokableFactory::class,
                JsonRenderer::class => InvokableFactory::class,
                MiddlewareListener::class => InvokableFactory::class,
                PhpRenderer::class => ViewPhpRendererFactory::class,
                PhpRendererStrategy::class => ViewPhpRendererStrategyFactory::class,
                RouteListener::class => InvokableFactory::class,
                SendResponseListener::class => SendResponseListenerFactory::class,
                View::class => ViewFactory::class,
            ],
            'shared' => [
                'EventManager' => false,
            ],
        ];
    }
}
