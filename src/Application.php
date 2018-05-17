<?php
/**
 * @link      http://github.com/zendframework/zend-mvc for the canonical source repository
 * @copyright Copyright (c) 2005-2018 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-mvc/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Zend\Mvc;

use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Zend\EventManager\EventManagerInterface;
use Zend\EventManager\ListenerAggregateInterface;
use Zend\Mvc\Exception\DomainException;
use Zend\Mvc\View\Http\ViewManager;

use function array_merge;
use function array_unique;
use function get_class;
use function gettype;
use function is_object;
use function is_string;

use const SORT_REGULAR;

/**
 * Main application class for invoking applications
 *
 * Expects the user will provide a configured ServiceManager, configured with
 * the following services:
 *
 * - EventManager
 * - Request
 * - Response
 * - RouteListener
 * - DispatchListener
 * - ViewManager
 *
 * The most common workflow is:
 * <code>
 * $services = new Zend\ServiceManager\ServiceManager($servicesConfig);
 * $app      = new Application($appConfig, $services);
 * $app->bootstrap();
 * $response = $app->run();
 * $response->send();
 * </code>
 *
 * bootstrap() opts in to the default route, dispatch, and view listeners,
 * sets up the MvcEvent, and triggers the bootstrap event. This can be omitted
 * if you wish to setup your own listeners and/or workflow; alternately, you
 * can simply extend the class to override such behavior.
 */
class Application implements ApplicationInterface
{
    const ERROR_CONTROLLER_CANNOT_DISPATCH = 'error-controller-cannot-dispatch';
    const ERROR_CONTROLLER_NOT_FOUND       = 'error-controller-not-found';
    const ERROR_CONTROLLER_INVALID         = 'error-controller-invalid';
    const ERROR_EXCEPTION                  = 'error-exception';
    const ERROR_ROUTER_NO_MATCH            = 'error-router-no-match';

    /**
     * Default application event listeners
     *
     * @var array
     */
    protected $defaultListeners = [
        RouteListener::class,
        RouteFailureListener::class,
        DispatchListener::class,
        HttpMethodListener::class,
        ViewManager::class,
    ];

    /**
     * Extra application event listeners
     *
     * @var array
     */
    private $extraListeners = [];

    /**
     * @var MvcEvent
     */
    protected $event;

    /**
     * @var EventManagerInterface
     */
    protected $events;

    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * If application was bootstrapped
     *
     * @var bool
     */
    private $bootstrapped = false;

    /**
     * Constructor
     */
    public function __construct(
        ContainerInterface $container,
        EventManagerInterface $events,
        array $extraListeners = []
    ) {
        $this->container = $container;
        $this->setEventManager($events);
        $this->extraListeners = $extraListeners;
        $this->event = new MvcEvent();
        $this->event->setApplication($this);
    }

    /**
     * Bootstrap the application
     *
     * Defines and binds the MvcEvent, and passes it the request, response, and
     * router. Attaches the ViewManager as a listener. Triggers the bootstrap
     * event.
     *
     */
    public function bootstrap() : void
    {
        if ($this->bootstrapped) {
            return;
        }
        $events = $this->events;

        // Setup default listeners
        $listeners = array_unique(array_merge($this->defaultListeners, $this->extraListeners), SORT_REGULAR);

        foreach ($listeners as $listener) {
            if (is_string($listener)) {
                $listener = $this->container->get($listener);
            }
            if (! $listener instanceof ListenerAggregateInterface) {
                throw new DomainException(sprintf(
                    'Invalid listener provided. Expected %s, got %s',
                    ListenerAggregateInterface::class,
                    is_object($listener) ? get_class($listener) : gettype($listener)
                ));
            }
            $listener->attach($events);
        }

        // Setup MVC Event
        $event = $this->event;
        $event->setTarget($this);
        $event->setName(MvcEvent::EVENT_BOOTSTRAP);

        // Trigger bootstrap events
        $events->triggerEvent($event);

        $this->bootstrapped = true;
    }

    /**
     * Retrieve the service manager
     */
    public function getContainer() : ContainerInterface
    {
        return $this->container;
    }

    /**
     * Get the MVC event instance
     *
     * @return MvcEvent
     */
    public function getMvcEvent() : MvcEvent
    {
        return $this->event;
    }

    /**
     * Set the event manager instance
     */
    private function setEventManager(EventManagerInterface $eventManager) : void
    {
        $eventManager->setIdentifiers([
            __CLASS__,
            get_class($this),
        ]);
        $this->events = $eventManager;
    }

    /**
     * Retrieve the event manager
     */
    public function getEventManager() : EventManagerInterface
    {
        return $this->events;
    }

    /**
     * Runs the mvc application
     *
     * @triggers route(MvcEvent)
     *           Routes the request, and sets the RouteResult object in the request attributes.
     * @triggers dispatch(MvcEvent)
     *           Dispatches a request, using the discovered RouteResult and
     *           provided request.
     * @triggers dispatch.error(MvcEvent)
     *           On errors (controller not found, action not supported, etc.),
     *           populates the event with information about the error type,
     *           discovered controller, and controller class (if known).
     *           Typically, a handler should return a populated Response object
     *           that can be returned immediately.
     */
    public function handle(ServerRequestInterface $request) : ResponseInterface
    {
        if (! $this->bootstrapped) {
            $this->bootstrap();
        }
        $events = $this->events;
        $event  = $this->event;
        $origEvent = clone $this->event;

        // reset event @TODO improve multiple requests handling
        $event->setRequest($request);
        $event->setResponse(null);

        // Define callback used to determine whether or not to short-circuit
        $shortCircuit = function ($r) use ($event) {
            if ($r instanceof ResponseInterface) {
                return true;
            }
            if ($event->getError()) {
                return true;
            }
            return false;
        };

        // Trigger route event
        $event->setName(MvcEvent::EVENT_ROUTE);
        $event->stopPropagation(false); // Clear before triggering
        $result = $events->triggerEventUntil($shortCircuit, $event);
        if ($result->stopped()) {
            $response = $result->last();
            if ($response instanceof ResponseInterface) {
                $event->setResponse($response);
                $response = $this->completeRequest($event);
                $this->event = $origEvent;
                return $response;
            }
        }

        if ($event->getError()) {
            $this->render($event);
            $response = $this->completeRequest($event);
            $this->event = $origEvent;
            return $response;
        }

        // Trigger dispatch event
        $event->setName(MvcEvent::EVENT_DISPATCH);
        $event->stopPropagation(false); // Clear before triggering
        $result = $events->triggerEventUntil($shortCircuit, $event);

        // Complete response
        $response = $result->last();
        if ($response instanceof ResponseInterface) {
            $event->setResponse($response);
            $response = $this->completeRequest($event);
            $this->event = $origEvent;
            return $response;
        }

        $this->render($event);
        $response = $this->completeRequest($event);
        $this->event = $origEvent;
        return $response;
    }

    /**
     * Triggers "render" event
     */
    private function render(MvcEvent $event) : void
    {
        $events = $this->events;
        $event->setTarget($this);

        $event->setName(MvcEvent::EVENT_RENDER);
        $event->stopPropagation(false); // Clear before triggering
        $events->triggerEvent($event);
    }

    /**
     * Complete the request
     *
     * Triggers "finish" event, and returns response from event object.
     */
    private function completeRequest(MvcEvent $event) : ResponseInterface
    {
        $events = $this->events;
        $event->setTarget($this);

        $event->setName(MvcEvent::EVENT_FINISH);
        $event->stopPropagation(false); // Clear before triggering
        $events->triggerEvent($event);

        $response = $event->getResponse();
        if (null === $response) {
            throw new DomainException('Application failed to produce a response');
        }
        return $response;
    }
}
