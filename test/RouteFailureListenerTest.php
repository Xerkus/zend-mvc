<?php
/**
 * @link      http://github.com/zendframework/zend-mvc for the canonical source repository
 * @copyright Copyright (c) 2005-2018 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-mvc/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace ZendTest\Mvc;

use Psr\Http\Message\ServerRequestInterface;
use Zend\Diactoros\ServerRequest;
use Zend\EventManager\EventManager;
use Zend\EventManager\EventManagerInterface;
use Zend\Mvc\ApplicationInterface;
use Zend\Mvc\MvcEvent;
use Zend\Mvc\RouteFailureListener;
use PHPUnit\Framework\TestCase;
use Zend\Router\RouteResult;

/**
 * @covers \Zend\Mvc\RouteFailureListener
 */
class RouteFailureListenerTest extends TestCase
{
    /**
     * @var RouteFailureListener
     */
    private $listener;

    /**
     * @var ServerRequestInterface
     */
    private $request;

    public function setUp()
    {
        $this->request = new ServerRequest([], [], null, 'GET', 'php://memory');
        $this->listener = new RouteFailureListener();
    }

    public function testAttachesToRouteEventAtLowPriority()
    {
        $em = $this->prophesize(EventManagerInterface::class);
        $em->attach(MvcEvent::EVENT_ROUTE, [$this->listener, 'onRoute'], -10000)->shouldBeCalled();
        $this->listener->attach($em->reveal());
    }

    public function testTriggersDispatchErrorEventOnRouteFailure()
    {
        $eventManager = new EventManager();
        $application = $this->prophesize(ApplicationInterface::class);
        $application->getEventManager()->willReturn($eventManager);
        $mvcEvent = new MvcEvent();
        $mvcEvent->setApplication($application->reveal());

        $request = $this->request->withAttribute(RouteResult::class, RouteResult::fromRouteFailure());
        $mvcEvent->setRequest($request);

        $expected = [
            MvcEvent::EVENT_DISPATCH_ERROR,
        ];
        $triggered = [];
        $eventManager->attach(
            '*',
            function (MvcEvent $e) use (&$triggered) {
                $triggered[] = $e->getName();
            }
        );

        $this->listener->onRoute($mvcEvent);
        $this->assertEquals($expected, $triggered);
    }
}
