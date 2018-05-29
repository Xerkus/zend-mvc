<?php
/**
 * @link      http://github.com/zendframework/zend-mvc for the canonical source repository
 * @copyright Copyright (c) 2005-2018 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-mvc/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Zend\Mvc;

use Psr\Container\ContainerInterface;
use Zend\EventManager\EventManagerInterface;
use Zend\EventManager\ListenerAggregateInterface;
use Zend\Mvc\Exception\DomainException;
use Zend\Mvc\View\Http\ViewManager;

use const SORT_REGULAR;

use function array_merge;
use function array_unique;
use function gettype;
use function get_class;
use function is_object;
use function is_string;
use function sprintf;

class ApplicationListenerAggregate implements ListenerAggregateInterface
{
    /**
     * @var ContainerInterface
     */
    private $container;

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
     * @var array
     */
    private $extraListeners;

    /**
     * @var ListenerAggregateInterface[]
     */
    private $listenersToDetach = [];

    public function __construct(ContainerInterface $container, array $extraListeners = [])
    {
        $this->container = $container;
        $this->extraListeners = $extraListeners;
    }

    /**
     * Attach one or more listeners
     *
     * Implementors may add an optional $priority argument; the EventManager
     * implementation will pass this to the aggregate.
     *
     * @param int $priority
     */
    public function attach(EventManagerInterface $events, $priority = 1) : void
    {
        // Setup default listeners
        $listeners = array_unique(array_merge(
            $this->defaultListeners,
            $this->extraListeners
        ), SORT_REGULAR);

        foreach ($listeners as $listener) {
            if (is_string($listener)) {
                $listener = $this->container->get($listener);
            }
            if (! $listener instanceof ListenerAggregateInterface) {
                throw new DomainException(sprintf(
                    'Invalid listener aggregate provided. Expected %s, got %s',
                    ListenerAggregateInterface::class,
                    is_object($listener) ? get_class($listener) : gettype($listener)
                ));
            }
            $listener->attach($events);
            $this->listenersToDetach[] = $listener;
        }
    }

    /**
     * Detach all previously attached listeners
     */
    public function detach(EventManagerInterface $events) : void
    {
        foreach ($this->listenersToDetach as $index => $listener) {
            $listener->detach($events);
            unset($this->listenersToDetach[$index]);
        }
    }
}
