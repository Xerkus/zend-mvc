<?php
/**
 * @link      http://github.com/zendframework/zend-mvc for the canonical source repository
 * @copyright Copyright (c) 2005-2018 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-mvc/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace ZendTest\Mvc\Controller;

use PHPUnit\Framework\TestCase;
use Zend\Diactoros\Response;
use Zend\Diactoros\ServerRequest;
use Zend\EventManager\EventManager;
use Zend\EventManager\SharedEventManager;
use Zend\EventManager\SharedEventManagerInterface;
use Zend\Mvc\InjectApplicationEventInterface;
use Zend\Mvc\MvcEvent;
use Zend\Router\RouteResult;
use Zend\View\Model\ModelInterface;
use ZendTest\Mvc\Controller\TestAsset\SampleController;

use function array_merge;

/**
 * @covers \Zend\Mvc\Controller\AbstractActionController
 */
class AbstractActionControllerTest extends TestCase
{
    private $controller;
    private $event;
    private $events;
    private $request;
    private $responseFactory;
    private $routeResult;
    private $sharedEvents;

    public function setUp()
    {
        $this->sharedEvents = new SharedEventManager();
        $this->events       = $this->createEventManager($this->sharedEvents);
        $this->responseFactory = function () {
            return new Response();
        };
        $this->controller = new SampleController($this->events, $this->responseFactory);
        $this->routeResult = RouteResult::fromRouteMatch(['controller' => 'controller-sample']);
        $this->request = (new ServerRequest())->withAttribute(RouteResult::class, $this->routeResult);
        $this->event = new MvcEvent();
        $this->controller->setEvent($this->event);
    }

    /**
     * @param SharedEventManager
     * @return EventManager
     */
    protected function createEventManager(SharedEventManagerInterface $sharedManager)
    {
        return new EventManager($sharedManager);
    }

    public function requestWithMatchedParams(ServerRequest $request, array $params)
    {
        /** @var RouteResult $routeResult */
        $routeResult = $request->getAttribute(RouteResult::class);
        $routeResult = $routeResult->withMatchedParams(array_merge($routeResult->getMatchedParams(), $params));
        foreach ($params as $name => $param) {
            $request = $request->withAttribute($name, $param);
        }
        return $request->withAttribute(RouteResult::class, $routeResult);
    }

    public function testDispatchInvokesNotFoundActionWhenNoActionPresentInRouteMatch()
    {
        $result = $this->controller->dispatch($this->request);
        $response = $this->controller->getResponse();
        $this->assertEquals(404, $response->getStatusCode());
        $this->assertInstanceOf(ModelInterface::class, $result);
        $this->assertEquals('content', $result->captureTo());
        $vars = $result->getVariables();
        $this->assertArrayHasKey('content', $vars, var_export($vars, true));
        $this->assertContains('Page not found', $vars['content']);
    }

    public function testDispatchInvokesNotFoundActionWhenInvalidActionPresentInRouteMatch()
    {
        $request = $this->requestWithMatchedParams($this->request, ['action' => 'totally-made-up-action']);
        $result = $this->controller->dispatch($request);
        $response = $this->controller->getResponse();
        $this->assertEquals(404, $response->getStatusCode());
        $this->assertInstanceOf(ModelInterface::class, $result);
        $this->assertEquals('content', $result->captureTo());
        $vars = $result->getVariables();
        $this->assertArrayHasKey('content', $vars, var_export($vars, true));
        $this->assertContains('Page not found', $vars['content']);
    }

    public function testDispatchInvokesProvidedActionWhenMethodExists()
    {
        $request = $this->requestWithMatchedParams($this->request, ['action' => 'test']);
        $result = $this->controller->dispatch($request);
        $this->assertTrue(isset($result['content']));
        $this->assertContains('test', $result['content']);
    }

    public function testDispatchCallsActionMethodBasedOnNormalizingAction()
    {
        $request = $this->requestWithMatchedParams($this->request, ['action' => 'test.some-strangely_separated.words']);
        $result = $this->controller->dispatch($request);
        $this->assertTrue(isset($result['content']));
        $this->assertContains('Test Some Strangely Separated Words', $result['content']);
    }

    public function testShortCircuitsBeforeActionIfPreDispatchReturnsAResponse()
    {
        $response = new Response();
        $this->controller->getEventManager()->attach(MvcEvent::EVENT_DISPATCH, function ($e) use ($response) {
            return $response;
        }, 100);
        $result = $this->controller->dispatch($this->request);
        $this->assertSame($response, $result);
    }

    public function testPostDispatchEventAllowsReplacingResponse()
    {
        $response = new Response();
        $this->controller->getEventManager()->attach(MvcEvent::EVENT_DISPATCH, function ($e) use ($response) {
            return $response;
        }, -10);
        $result = $this->controller->dispatch($this->request);
        $this->assertSame($response, $result);
    }

    public function testControllerIsEventAware()
    {
        $this->assertInstanceOf(InjectApplicationEventInterface::class, $this->controller);
    }
}
