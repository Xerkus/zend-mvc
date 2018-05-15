<?php
/**
 * @link      http://github.com/zendframework/zend-mvc for the canonical source repository
 * @copyright Copyright (c) 2005-2018 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-mvc/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Zend\Mvc\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Zend\EventManager\EventInterface as Event;
use Zend\EventManager\EventManagerAwareInterface;
use Zend\EventManager\EventManagerInterface;
use Zend\Mvc\Exception\DomainException;
use Zend\Mvc\InjectApplicationEventInterface;
use Zend\Mvc\MvcEvent;
use Zend\View\Model\ModelInterface;

use function array_values;
use function get_class;
use function lcfirst;
use function str_replace;
use function strrpos;
use function strstr;
use function substr;
use function ucwords;

/**
 * Abstract controller
 */
abstract class AbstractController implements
    ControllerInterface,
    EventManagerAwareInterface,
    InjectApplicationEventInterface
{
    /**
     * @var MvcEvent
     */
    protected $event;

    /**
     * @var EventManagerInterface
     */
    protected $events;

    /**
     * @var null|string|string[]
     */
    protected $eventIdentifier;

    /**
     * Response factory
     *
     * @var callable
     */
    protected $responseFactory;

    public function __construct(EventManagerInterface $events, callable $responseFactory)
    {
        $this->setEventManager($events);
        // Ensures type safety of the composed factory
        $this->responseFactory = function () use ($responseFactory) : ResponseInterface {
            return $responseFactory();
        };
    }

    /**
     * Execute the request
     *
     * @return ResponseInterface|ModelInterface|array|null
     */
    abstract public function onDispatch(MvcEvent $e);

    /**
     * Dispatch a request
     *
     * @events dispatch
     * @return ResponseInterface|ModelInterface|array|null
     */
    public function dispatch(ServerRequestInterface $request, ResponseInterface $responsePrototype = null)
    {
        $e = $this->getEvent();
        $e->setName(MvcEvent::EVENT_DISPATCH);
        $e->setRequest($request);
        $e->setResponse($responsePrototype ?? $this->getResponse());
        $e->setTarget($this);

        $result = $this->getEventManager()->triggerEventUntil(function ($test) {
            return ($test instanceof ResponseInterface);
        }, $e);

        if ($result->stopped()) {
            return $result->last();
        }

        return $e->getResult();
    }

    /**
     * Convenience method for getting request object. Fetches request from event
     */
    public function getRequest() : ServerRequestInterface
    {
        $request = $this->getEvent()->getRequest();
        if (! $request) {
            throw new DomainException('No request. Request might not be available before dispatch');
        }
        return $request;
    }

    /**
     * Convenience method for getting response object. Fetches response from
     * event or creates new if none available
     */
    public function getResponse() : ResponseInterface
    {
        return $this->getEvent()->getResponse() ?? ($this->responseFactory)();
    }

    /**
     * Convenience method. Sets response into event.
     */
    public function setResponse(ResponseInterface $response) : void
    {
        $this->getEvent()->setResponse($response);
    }

    /**
     * Set the event manager instance used by this context
     */
    public function setEventManager(EventManagerInterface $events) : void
    {
        $className = get_class($this);

        $identifiers = [
            __CLASS__,
            $className,
        ];

        $rightmostNsPos = strrpos($className, '\\');
        if ($rightmostNsPos) {
            $identifiers[] = strstr($className, '\\', true); // top namespace
            $identifiers[] = substr($className, 0, $rightmostNsPos); // full namespace
        }

        $events->setIdentifiers(array_merge(
            $identifiers,
            array_values(class_implements($className)),
            (array) $this->eventIdentifier
        ));

        $this->events = $events;
        $this->attachDefaultListeners();
    }

    /**
     * Retrieve the event manager
     */
    public function getEventManager() : EventManagerInterface
    {
        return $this->events;
    }

    /**
     * Set an event to use during dispatch
     *
     * By default, will re-cast to MvcEvent if another event type is provided.
     */
    public function setEvent(Event $e) : void
    {
        if (! $e instanceof MvcEvent) {
            $eventParams = $e->getParams();
            $e = new MvcEvent();
            $e->setParams($eventParams);
            unset($eventParams);
        }
        $this->event = $e;
    }

    /**
     * Get the attached event
     *
     * Will create a new MvcEvent if none provided.
     */
    public function getEvent() : MvcEvent
    {
        if (! $this->event) {
            $this->setEvent(new MvcEvent());
        }

        return $this->event;
    }

    /**
     * Register the default events for this controller
     */
    protected function attachDefaultListeners() : void
    {
        $events = $this->getEventManager();
        $events->attach(MvcEvent::EVENT_DISPATCH, [$this, 'onDispatch']);
    }

    /**
     * Transform an "action" token into a method name
     *
     * @param  string $action
     * @return string
     */
    public static function getMethodFromAction(string $action) : string
    {
        $method  = str_replace(['.', '-', '_'], ' ', $action);
        $method  = ucwords($method);
        $method  = str_replace(' ', '', $method);
        $method  = lcfirst($method);
        $method .= 'Action';

        return $method;
    }
}
