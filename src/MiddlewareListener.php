<?php
/**
 * @link      http://github.com/zendframework/zend-mvc for the canonical source repository
 * @copyright Copyright (c) 2005-2018 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-mvc/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Zend\Mvc;

use Interop\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;
use Zend\EventManager\AbstractListenerAggregate;
use Zend\EventManager\EventManagerInterface;
use Zend\Mvc\Exception\InvalidMiddlewareException;
use Zend\Mvc\Controller\MiddlewareController;
use Zend\Psr7Bridge\Psr7Response;
use Zend\Stratigility\Middleware\RequestHandlerMiddleware;
use Zend\Stratigility\MiddlewarePipe;

class MiddlewareListener extends AbstractListenerAggregate
{
    /**
     * Attach listeners to an event manager
     *
     * @param EventManagerInterface $events
     * @param int $priority
     */
    public function attach(EventManagerInterface $events, $priority = 1) : void
    {
        $this->listeners[] = $events->attach(MvcEvent::EVENT_DISPATCH, [$this, 'onDispatch'], 1);
    }

    /**
     * Listen to the "dispatch" event
     *
     * @return mixed
     */
    public function onDispatch(MvcEvent $event)
    {
        if (null !== $event->getResult()) {
            return;
        }

        $routeMatch = $event->getRouteMatch();
        if (! $routeMatch) {
            return;
        }
        $middleware = $routeMatch->getParam('middleware', false);
        if (false === $middleware) {
            return;
        }

        $request = $event->getRequest();
        $application = $event->getApplication();
        /*
         * @var $response \Zend\Http\Response
         */
        $response = $application->getResponse();
        $container = $application->getServiceManager();

        try {
            $pipe = $this->createPipeFromSpec(
                $container,
                is_array($middleware) ? $middleware : [$middleware]
            );
        } catch (InvalidMiddlewareException $invalidMiddlewareException) {
            $return = $this->marshalInvalidMiddleware(
                Application::ERROR_MIDDLEWARE_CANNOT_DISPATCH,
                $invalidMiddlewareException->toMiddlewareName(),
                $event,
                $application,
                $invalidMiddlewareException
            );
            $event->setResult($return);
            return $return;
        }

        $return = null;
        try {
            $return = (new MiddlewareController(
                $pipe,
                $application->getServiceManager()->get('EventManager'),
                $event
            ))->dispatch($request, $response);
        } catch (Throwable $ex) {
            $event->setName(MvcEvent::EVENT_DISPATCH_ERROR);
            $event->setError(Application::ERROR_EXCEPTION);
            $event->setParam('exception', $ex);

            $events = $application->getEventManager();
            $results = $events->triggerEvent($event);
            $return = $results->last();
            if (! $return) {
                $return = $event->getResult();
            }
        }

        $event->setError('');

        if (! $return instanceof ResponseInterface) {
            $event->setResult($return);
            return $return;
        }
        $response = Psr7Response::toZend($return);
        $event->setResult($response);
        return $response;
    }

    /**
     * Create a middleware pipe from the array spec given.
     *
     * @throws InvalidMiddlewareException
     */
    private function createPipeFromSpec(ContainerInterface $container, array $middlewaresToBePiped) : MiddlewarePipe
    {
        $pipe = new MiddlewarePipe();
        foreach ($middlewaresToBePiped as $middlewareToBePiped) {
            if (null === $middlewareToBePiped) {
                throw InvalidMiddlewareException::fromNull();
            }

            if (is_string($middlewareToBePiped) && $container->has($middlewareToBePiped)) {
                $middlewareToBePiped = $container->get($middlewareToBePiped);
            }

            if ($middlewareToBePiped instanceof MiddlewareInterface) {
                $pipe->pipe($middlewareToBePiped);
                continue;
            }
            if ($middlewareToBePiped instanceof RequestHandlerInterface) {
                $middlewareToBePiped = new RequestHandlerMiddleware($middlewareToBePiped);
                $pipe->pipe($middlewareToBePiped);
                continue;
            }
            /*
             * callable and auto-invokable is not allowed for security reasons, as middleware parameter
             * could potentially come from matched route parameter and middleware
             * is fetched from main container.
             */

            $middlewareName = is_string($middlewareToBePiped)
                ? $middlewareToBePiped
                : (is_object($middlewareToBePiped) ? get_class($middlewareToBePiped) : gettype($middlewareToBePiped));

            throw InvalidMiddlewareException::fromMiddlewareName($middlewareName);
        }
        return $pipe;
    }

    /**
     * Marshal a middleware not callable exception event
     *
     * @return mixed
     */
    protected function marshalInvalidMiddleware(
        string $type,
        string $middlewareName,
        MvcEvent $event,
        ApplicationInterface $application,
        \Exception $exception = null
    ) {

        $event->setName(MvcEvent::EVENT_DISPATCH_ERROR);
        $event->setError($type);
        $event->setController($middlewareName);
        $event->setControllerClass('Middleware not callable: ' . $middlewareName);
        if ($exception !== null) {
            $event->setParam('exception', $exception);
        }

        $events = $application->getEventManager();
        $results = $events->triggerEvent($event);
        $return = $results->last();
        if (! $return) {
            $return = $event->getResult();
        }
        return $return;
    }
}
