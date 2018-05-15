<?php
/**
 * @link      http://github.com/zendframework/zend-mvc for the canonical source repository
 * @copyright Copyright (c) 2005-2018 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-mvc/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace ZendTest\Mvc\Controller;

use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use ReflectionProperty;
use Zend\Diactoros\Response;
use Zend\Diactoros\ServerRequest;
use Zend\EventManager\EventManager;
use Zend\EventManager\EventManagerAwareInterface;
use Zend\EventManager\EventManagerInterface;
use Zend\Mvc\Controller\AbstractController;
use Zend\Mvc\Controller\ControllerInterface;
use Zend\Mvc\Exception\DomainException;
use Zend\Mvc\InjectApplicationEventInterface;
use Zend\Mvc\MvcEvent;
use ZendTest\Mvc\Controller\TestAsset\AbstractControllerStub;

/**
 * @covers \Zend\Mvc\Controller\AbstractController
 */
class AbstractControllerTest extends TestCase
{
    /**
     * @var AbstractController|\PHPUnit_Framework_MockObject_MockObject
     */
    private $controller;

    private $events;

    private $responseFactory;

    /**
     * {@inheritDoc}
     */
    protected function setUp()
    {
        $this->events = new EventManager();
        $this->responseFactory = function () {
            return new Response();
        };
        $this->controller = new AbstractControllerStub($this->events, $this->responseFactory);
    }

    public function testConstructorEventManagerIsSet()
    {
        $this->assertSame($this->events, $this->controller->getEventManager());
    }

    public function testSetterReplacesEventManager()
    {
        $eventManager = new EventManager();
        $this->controller->setEventManager($eventManager);
        $this->assertSame($eventManager, $this->controller->getEventManager());
    }

    /**
     * @group 6553
     */
    public function testSetEventManagerWithDefaultIdentifiers()
    {
        /* @var $eventManager EventManagerInterface|\PHPUnit_Framework_MockObject_MockObject */
        $eventManager = $this->createMock(EventManagerInterface::class);

        $eventManager
            ->expects($this->once())
            ->method('setIdentifiers')
            ->with($this->logicalNot($this->contains('customEventIdentifier')));

        $this->controller->setEventManager($eventManager);
    }

    /**
     * @group 6553
     */
    public function testSetEventManagerWithCustomStringIdentifier()
    {
        /* @var $eventManager EventManagerInterface|\PHPUnit_Framework_MockObject_MockObject */
        $eventManager = $this->createMock(EventManagerInterface::class);

        $eventManager->expects($this->once())->method('setIdentifiers')->with($this->contains('customEventIdentifier'));

        $reflection = new ReflectionProperty($this->controller, 'eventIdentifier');

        $reflection->setAccessible(true);
        $reflection->setValue($this->controller, 'customEventIdentifier');

        $this->controller->setEventManager($eventManager);
    }

    /**
     * @group 6553
     */
    public function testSetEventManagerWithMultipleCustomStringIdentifier()
    {
        /* @var $eventManager EventManagerInterface|\PHPUnit_Framework_MockObject_MockObject */
        $eventManager = $this->createMock(EventManagerInterface::class);

        $eventManager->expects($this->once())->method('setIdentifiers')->with($this->logicalAnd(
            $this->contains('customEventIdentifier1'),
            $this->contains('customEventIdentifier2')
        ));

        $reflection = new ReflectionProperty($this->controller, 'eventIdentifier');

        $reflection->setAccessible(true);
        $reflection->setValue($this->controller, ['customEventIdentifier1', 'customEventIdentifier2']);

        $this->controller->setEventManager($eventManager);
    }

    /**
     * @group 6615
     */
    public function testSetEventManagerWithDefaultIdentifiersIncludesImplementedInterfaces()
    {
        /* @var $eventManager EventManagerInterface|\PHPUnit_Framework_MockObject_MockObject */
        $eventManager = $this->createMock(EventManagerInterface::class);

        $eventManager
            ->expects($this->once())
            ->method('setIdentifiers')
            ->with($this->logicalAnd(
                $this->contains(EventManagerAwareInterface::class),
                $this->contains(ControllerInterface::class),
                $this->contains(InjectApplicationEventInterface::class)
            ));

        $this->controller->setEventManager($eventManager);
    }

    public function testSetEventManagerWithDefaultIdentifiersIncludesExtendingClassNameAndNamespace()
    {
        /* @var $eventManager EventManagerInterface|\PHPUnit_Framework_MockObject_MockObject */
        $eventManager = $this->createMock(EventManagerInterface::class);

        $eventManager
            ->expects($this->once())
            ->method('setIdentifiers')
            ->with($this->logicalAnd(
                $this->contains(AbstractController::class),
                $this->contains(AbstractControllerStub::class),
                $this->contains('ZendTest'),
                $this->contains('ZendTest\\Mvc\\Controller\\TestAsset')
            ));

        $this->controller->setEventManager($eventManager);
    }

    public function testConstructorInjectedEventManagerSetWithIdentifiers()
    {
        /* @var $eventManager EventManagerInterface|\PHPUnit_Framework_MockObject_MockObject */
        $eventManager = $this->createMock(EventManagerInterface::class);

        /* @var $eventManager EventManagerInterface|\PHPUnit_Framework_MockObject_MockObject */
        $eventManager = $this->createMock(EventManagerInterface::class);

        $eventManager
            ->expects($this->once())
            ->method('setIdentifiers')
            ->with($this->logicalAnd(
                $this->contains(EventManagerAwareInterface::class),
                $this->contains(ControllerInterface::class),
                $this->contains(InjectApplicationEventInterface::class)
            ));

        new AbstractControllerStub($eventManager, $this->responseFactory);
    }

    public function testDispatchEventIsTriggered()
    {
        $called = false;
        $listener = function (MvcEvent $e) use (&$called) {
            $called = true;
            $this->assertSame($this->controller, $e->getTarget());
        };

        $this->controller->getEventManager()->attach(MvcEvent::EVENT_DISPATCH, $listener, 9000);
        $this->controller->dispatch(new ServerRequest());

        $this->assertTrue($called);
    }

    public function testDispatchSetsRequestIntoEvent()
    {
        $called = false;
        $request = new ServerRequest();
        $listener = function (MvcEvent $e) use (&$called, $request) {
            $called = true;
            $this->assertSame($request, $e->getRequest());
        };

        $this->controller->getEventManager()->attach(MvcEvent::EVENT_DISPATCH, $listener, 9000);
        $this->controller->dispatch($request);
        $this->assertTrue($called);
    }

    public function testDispatchSetsResponseProvidedAsParameterIntoEvent()
    {
        $called = false;
        $response = new Response();
        $listener = function (MvcEvent $e) use (&$called, $response) {
            $called = true;
            $this->assertSame($response, $e->getResponse());
        };

        $this->controller->getEventManager()->attach(MvcEvent::EVENT_DISPATCH, $listener, 9000);
        $this->controller->dispatch(new ServerRequest(), $response);
        $this->assertTrue($called);
    }

    public function testDispatchSetsNewResponseIntoEventIfNoneProvidedAndEventDoesNotHaveOneAlready()
    {
        $called = false;
        $listener = function (MvcEvent $e) use (&$called) {
            $called = true;
            $this->assertInstanceOf(ResponseInterface::class, $e->getResponse());
        };

        $this->controller->getEventManager()->attach(MvcEvent::EVENT_DISPATCH, $listener, 9000);
        $this->controller->dispatch(new ServerRequest());
        $this->assertTrue($called);
    }

    public function testUsesResponseAlreadySetIntoEvent()
    {
        $called = false;
        $response = new Response();
        $this->controller->getEvent()->setResponse($response);
        $listener = function (MvcEvent $e) use (&$called, $response) {
            $called = true;
            $this->assertSame($response, $e->getResponse());
        };

        $this->controller->getEventManager()->attach(MvcEvent::EVENT_DISPATCH, $listener, 9000);
        $this->controller->dispatch(new ServerRequest());
        $this->assertTrue($called);
    }

    public function testGetRequestReturnsRequestFromEvent()
    {
        $request = new ServerRequest();
        $this->controller->getEvent()->setRequest($request);

        $this->assertSame($request, $this->controller->getRequest());
    }

    public function testGetRequestThrowsIfTryingToFetchResponseBeforeDispatch()
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('No request. Request might not be available before dispatch');
        $this->controller->getRequest();
    }

    public function testGetResponseReturnsResponseFromEvent()
    {
        $response = new Response();
        $this->controller->getEvent()->setResponse($response);

        $this->assertSame($response, $this->controller->getResponse());
    }

    public function testSetResponseSetsResponseIntoEvent()
    {
        $response = new Response();
        $this->controller->setResponse($response);

        $this->assertSame($response, $this->controller->getEvent()->getResponse());
    }

    public function testGetResponseUsesResponseFactoryToCreateNewResponseWhenEventHasNone()
    {
        $response = new Response();
        $responseFactory = function () use ($response) {
            return $response;
        };
        $controller = new AbstractControllerStub($this->events, $responseFactory);

        $this->assertSame($response, $controller->getResponse());
    }

    public function testDispatchShortCircuitsIfResponseIsReturned()
    {
        $called1 = false;
        $listener1 = function (MvcEvent $e) use (&$called1) {
            $called1 = true;
            return new Response();
        };

        $called2 = false;
        $listener2 = function (MvcEvent $e) use (&$called2) {
            $called2 = true;
        };

        $this->controller->getEventManager()->attach(MvcEvent::EVENT_DISPATCH, $listener1, 9001);
        $this->controller->getEventManager()->attach(MvcEvent::EVENT_DISPATCH, $listener2, 9000);
        $this->controller->dispatch(new ServerRequest());

        $this->assertTrue($called1);
        $this->assertFalse($called2);
    }

    public function testMethodFromAction()
    {
        $this->assertEquals('fooAction', AbstractController::getMethodFromAction('foo'));
        $this->assertEquals('fooBarAction', AbstractController::getMethodFromAction('foo-bar'));
    }
}
