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
use Zend\Router\RouteResult;

class RouteFailureListener extends AbstractListenerAggregate
{
    /**
     * Attach to Application EventManager on route event
     *
     * @param int $priority
     */
    public function attach(EventManagerInterface $events, $priority = 1) : void
    {
        $events->attach(MvcEvent::EVENT_ROUTE, [$this, 'onRoute'], -10000);
    }

    /**
     * Listen to the "route" event at low priority and intercept failed routing
     *
     * Inspect RouteResult in request attributes and triggers dispatch.error if
     * it is missing or is failure.
     */
    public function onRoute(MvcEvent $e)
    {
        $routeResult = $e->getRequest()->getAttribute(RouteResult::class);

        if ($routeResult instanceof RouteResult && $routeResult->isSuccess()) {
            return null;
        }

        if ($routeResult instanceof RouteResult) {
            $e->setParam(RouteResult::class, $routeResult);
        }

        $e->setName(MvcEvent::EVENT_DISPATCH_ERROR);
        $e->setError(Application::ERROR_ROUTER_NO_MATCH);

        $app = $e->getApplication();
        $results = $app->getEventManager()->triggerEvent($e);
        if (! empty($results)) {
            return $results->last();
        }

        // @TODO what should happen here?
    }
}
