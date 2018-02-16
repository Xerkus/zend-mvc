<?php
/**
 * @link      http://github.com/zendframework/zend-mvc for the canonical source repository
 * @copyright Copyright (c) 2005-2018 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-mvc/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Zend\Mvc;

use Zend\EventManager\AbstractListenerAggregate;
use Zend\EventManager\EventManagerInterface;
use Zend\Router\Router;
use Zend\Router\RouteResult;

class RouteListener extends AbstractListenerAggregate
{
    /**
     * @var Router
     */
    private $router;

    public function __construct(Router $router)
    {
        $this->router = $router;
    }

    /**
     * Attach to an event manager
     *
     * @param  int $priority
     */
    public function attach(EventManagerInterface $events, $priority = 1) : void
    {
        $this->listeners[] = $events->attach(MvcEvent::EVENT_ROUTE, [$this, 'onRoute']);
    }

    /**
     * Listen to the "route" event and attempt to route the request
     *
     * Injects RouteResult into request attributes
     */
    public function onRoute(MvcEvent $event) : void
    {
        $request    = $event->getRequest();
        $routeResult = $this->router->match($request);
        if ($routeResult->isSuccess()) {
            foreach ($routeResult->getMatchedParams() as $param => $value) {
                $request = $request->withAttribute($param, $value);
            }
        }

        $event->setRequest($request->withAttribute(RouteResult::class, $routeResult));
    }
}
