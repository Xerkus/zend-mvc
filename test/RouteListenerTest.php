<?php
/**
 * @link      http://github.com/zendframework/zend-mvc for the canonical source repository
 * @copyright Copyright (c) 2005-2018 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-mvc/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace ZendTest\Mvc;

use Prophecy\Prophecy\ObjectProphecy;
use Psr\Http\Message\ServerRequestInterface;
use Zend\Diactoros\ServerRequest;
use Zend\EventManager\EventManagerInterface;
use Zend\Mvc\MvcEvent;
use Zend\Mvc\RouteListener;
use PHPUnit\Framework\TestCase;
use Zend\Router\Router;
use Zend\Router\RouteResult;

/**
 * @covers \Zend\Mvc\RouteListener
 */
class RouteListenerTest extends TestCase
{
    /**
     * @var ObjectProphecy|Router
     */
    private $router;

    /**
     * @var RouteListener
     */
    private $routeListener;

    /**
     * @var ServerRequestInterface
     */
    private $request;

    public function setUp()
    {
        $this->request = new ServerRequest([], [], null, 'GET', 'php://memory');
        $this->router = $this->prophesize(Router::class);
        $this->routeListener = new RouteListener($this->router->reveal());
    }

    public function testAttachesToRouteEvent()
    {
        $em = $this->prophesize(EventManagerInterface::class);
        $em->attach(MvcEvent::EVENT_ROUTE, [$this->routeListener, 'onRoute'])->shouldBeCalled();
        $this->routeListener->attach($em->reveal());
    }

    public function testRoutesRequestAndSetsResultIntoRequestAttributes()
    {
        $expectedRouteResult = RouteResult::fromRouteMatch([]);
        $mvcEvent = new MvcEvent();
        $mvcEvent->setRequest($this->request);
        $this->router->match($this->request)->shouldBeCalled()->willReturn($expectedRouteResult);

        $this->routeListener->onRoute($mvcEvent);

        $routeResult = $mvcEvent->getRequest()->getAttribute(RouteResult::class);
        $this->assertInstanceOf(RouteResult::class, $routeResult);
        $this->assertSame($expectedRouteResult, $routeResult);
    }

    public function testRoutesRequestAndSetsFailedResultIntoRequestAttributes()
    {
        $expectedRouteResult = RouteResult::fromRouteFailure();
        $mvcEvent = new MvcEvent();
        $mvcEvent->setRequest($this->request);
        $this->router->match($this->request)->shouldBeCalled()->willReturn($expectedRouteResult);

        $this->routeListener->onRoute($mvcEvent);

        $routeResult = $mvcEvent->getRequest()->getAttribute(RouteResult::class);
        $this->assertInstanceOf(RouteResult::class, $routeResult);
        $this->assertSame($expectedRouteResult, $routeResult);
    }

    /**
     * This test have no counterpart that checks matched parameters are not injected
     * for failed routing since failed route result currently can't have matched
     * parameters
     */
    public function testInjectsMatchedParametersAsRequestAttributesForSuccessfulResult()
    {
        $expectedRouteResult = RouteResult::fromRouteMatch(['controller' => 'testing']);
        $mvcEvent = new MvcEvent();
        $mvcEvent->setRequest($this->request);
        $this->router->match($this->request)->willReturn($expectedRouteResult);

        $this->routeListener->onRoute($mvcEvent);

        $param = $mvcEvent->getRequest()->getAttribute('controller');
        $this->assertSame('testing', $param);
    }

    public function testRouteResultOverridesMatchedParameterWithSameKey()
    {
        $expectedRouteResult = RouteResult::fromRouteMatch([RouteResult::class => 'testing']);
        $mvcEvent = new MvcEvent();
        $mvcEvent->setRequest($this->request);
        $this->router->match($this->request)->willReturn($expectedRouteResult);

        $this->routeListener->onRoute($mvcEvent);

        $routeResult = $mvcEvent->getRequest()->getAttribute(RouteResult::class);
        $this->assertInstanceOf(RouteResult::class, $routeResult);
    }
}
