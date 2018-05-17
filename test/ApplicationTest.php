<?php
/**
 * @link      http://github.com/zendframework/zend-mvc for the canonical source repository
 * @copyright Copyright (c) 2005-2018 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-mvc/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace ZendTest\Mvc;

use PHPUnit\Framework\TestCase;
use Prophecy\Prophecy\ObjectProphecy;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ServerRequestInterface;
use ReflectionProperty;
use stdClass;
use Zend\Diactoros\Response;
use Zend\Diactoros\ServerRequest;
use Zend\EventManager\EventManager;
use Zend\EventManager\ListenerAggregateInterface;
use Zend\EventManager\SharedEventManager;
use Zend\EventManager\Test\EventListenerIntrospectionTrait;
use Zend\Mvc\Application;
use Zend\Mvc\DispatchListener;
use Zend\Mvc\Exception\DomainException;
use Zend\Mvc\HttpMethodListener;
use Zend\Mvc\MvcEvent;
use Zend\Mvc\RouteFailureListener;
use Zend\Mvc\RouteListener;
use Zend\Mvc\View\Http\ViewManager;

/**
 * @covers \Zend\Mvc\Application
 */
class ApplicationTest extends TestCase
{
    use ContainerTrait;
    use EventListenerIntrospectionTrait;

    /**
     * @var Application
     */
    private $application;

    /**
     * @var ObjectProphecy|ContainerInterface
     */
    private $container;

    /**
     * @var EventManager
     */
    private $events;

    /**
     * @var ServerRequestInterface
     */
    private $request;

    public function setUp()
    {
        $this->container = $this->mockContainerInterface();
        $this->events = new EventManager(new SharedEventManager());
        $this->request = new ServerRequest([], [], null, 'GET', 'php://memory');

        $route = $this->prophesize(RouteListener::class);
        $this->injectServiceInContainer($this->container, RouteListener::class, $route->reveal());

        $routeFailure = $this->prophesize(RouteFailureListener::class);
        $this->injectServiceInContainer($this->container, RouteFailureListener::class, $routeFailure->reveal());

        $dispatch = $this->prophesize(DispatchListener::class);
        $this->injectServiceInContainer($this->container, DispatchListener::class, $dispatch->reveal());

        $viewManager = $this->prophesize(ViewManager::class);
        $this->injectServiceInContainer($this->container, ViewManager::class, $viewManager->reveal());

        $httpMethod = $this->prophesize(HttpMethodListener::class);
        $this->injectServiceInContainer($this->container, HttpMethodListener::class, $httpMethod->reveal());

        $this->application = new Application(
            $this->container->reveal(),
            $this->events
        );
    }

    public function testEventManagerIsPopulated()
    {
        $this->assertSame($this->events, $this->application->getEventManager());
    }

    public function testEventManagerListensOnApplicationContext()
    {
        $events      = $this->application->getEventManager();
        $identifiers = $events->getIdentifiers();
        $expected    = [Application::class];
        $this->assertEquals($expected, array_values($identifiers));
    }

    public function testContainerIsPopulated()
    {
        $this->assertSame($this->container->reveal(), $this->application->getContainer());
    }

    public function testMvcEventHaveApplicationSet()
    {
        $mvcEvent = $this->application->getMvcEvent();
        $this->assertSame($this->application, $mvcEvent->getApplication());
    }

    public function testBootstrapTriggersBootstrapEvent()
    {
        $called = false;
        $this->application->getEventManager()->attach(
            MvcEvent::EVENT_BOOTSTRAP,
            function ($e) use (&$called) {
                $this->assertInstanceOf(MvcEvent::class, $e);
                $called = true;
            }
        );
        $this->application->bootstrap();
        $this->assertTrue($called);
    }

    public function testBootstrapRegistersDefaultListeners()
    {
        $app = new Application(
            $this->container->reveal(),
            $this->events
        );

        $r = new ReflectionProperty($this->application, 'defaultListeners');
        $r->setAccessible(true);
        $defaultListeners = $r->getValue($this->application);
        foreach ($defaultListeners as $defaultListenerName) {
            $listener = $this->prophesize(ListenerAggregateInterface::class);
            $listener->attach($this->events)->shouldBeCalled();
            $this->injectServiceInContainer($this->container, $defaultListenerName, $listener->reveal());
        }

        $app->bootstrap();
    }

    public function testBootstrapRegistersExtraListeners()
    {
        $app = new Application(
            $this->container->reveal(),
            $this->events,
            ['customListener']
        );

        $custom = $this->prophesize(ListenerAggregateInterface::class);
        $this->injectServiceInContainer($this->container, 'customListener', $custom->reveal());
        $custom->attach($this->events)->shouldBeCalled();

        $app->bootstrap();
    }


    public function testBootstrapAlwaysRegistersDefaultListeners()
    {
        $app = new Application(
            $this->container->reveal(),
            $this->events,
            ['customListener']
        );

        $custom = $this->prophesize(ListenerAggregateInterface::class);
        $this->injectServiceInContainer($this->container, 'customListener', $custom->reveal());

        $r = new ReflectionProperty($this->application, 'defaultListeners');
        $r->setAccessible(true);
        $defaultListeners = $r->getValue($this->application);
        foreach ($defaultListeners as $defaultListenerName) {
            $listener = $this->prophesize(ListenerAggregateInterface::class);
            $listener->attach($this->events)->shouldBeCalled();
            $this->injectServiceInContainer($this->container, $defaultListenerName, $listener->reveal());
        }

        $app->bootstrap();
    }

    public function testBootstrapRegistersListenerProvidedAsInstanceOfListenerAggregate()
    {
        $custom = $this->prophesize(ListenerAggregateInterface::class);
        $custom->attach($this->events)->shouldBeCalled();
        $app = new Application(
            $this->container->reveal(),
            $this->events,
            [$custom->reveal()]
        );

        $app->bootstrap();
    }

    public function testBootstrapShouldThrowExceptionOnInvalidListener()
    {
        $app = new Application(
            $this->container->reveal(),
            $this->events,
            ['customListener']
        );

        $invalid = new stdClass();
        $this->injectServiceInContainer($this->container, 'customListener', $invalid);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Invalid listener provided');

        $app->bootstrap();
    }

    public function testBootstrapSetsApplicationAsEventTarget()
    {
        $this->application->getEventManager()->attach(
            MvcEvent::EVENT_BOOTSTRAP,
            function (MvcEvent $e) {
                $this->assertFalse($e->isError());
                $this->assertSame($this->application, $e->getApplication());
                $this->assertSame($this->application, $e->getTarget());
            }
        );
        $this->application->bootstrap();
    }

    public function testBootstrapEventDoesNotHaveRequestOrResponse()
    {
        $this->application->getEventManager()->attach(
            MvcEvent::EVENT_BOOTSTRAP,
            function (MvcEvent $e) {
                $this->assertNull($e->getRequest());
                $this->assertNull($e->getResponse());
            }
        );
        $this->application->bootstrap();
    }

    public function testBootstrapMethodIsIdempotent()
    {
        $listener = $this->getMockBuilder(stdClass::class)->setMethods(['__invoke'])->getMock();
        $listener->expects(self::once())->method('__invoke');
        $this->application->getEventManager()->attach(MvcEvent::EVENT_BOOTSTRAP, $listener);

        $this->application->bootstrap();
        $this->application->bootstrap();
    }

    public function testHandleBootstrapsImplicitly()
    {
        $this->application->getEventManager()->attach(MvcEvent::EVENT_DISPATCH, function () {
            return new Response();
        });

        $listener = $this->getMockBuilder(stdClass::class)->setMethods(['__invoke'])->getMock();
        $listener->expects(self::once())->method('__invoke');
        $this->application->getEventManager()->attach(MvcEvent::EVENT_BOOTSTRAP, $listener);

        $this->application->handle($this->request);
    }

    /**
     * @dataProvider handleEventsProvider
     */
    public function testRequestIsAvailableForEvent(string $event)
    {
        $this->application->getEventManager()->attach(MvcEvent::EVENT_FINISH, function (MvcEvent $e) {
            $e->setResponse(new Response());
        }, -10000);

        $this->application->getEventManager()->attach(
            $event,
            function (MvcEvent $e) {
                $this->assertNotNull($e->getRequest());
            }
        );
        $this->application->handle($this->request);
    }

    public function handleEventsProvider() : array
    {
        return [
            MvcEvent::EVENT_ROUTE => [MvcEvent::EVENT_ROUTE],
            MvcEvent::EVENT_DISPATCH => [MvcEvent::EVENT_DISPATCH],
            MvcEvent::EVENT_RENDER => [MvcEvent::EVENT_RENDER],
            MvcEvent::EVENT_FINISH => [MvcEvent::EVENT_FINISH],
        ];
    }

    public function testMvcEventIsResetOnSecondHandleCall()
    {
        $this->application->getEventManager()->attach(
            MvcEvent::EVENT_ROUTE,
            function (MvcEvent $e) {
                $this->assertNull($e->getResponse());
                $this->assertEmpty($e->getError());
                $this->assertNull($e->getParam('testing'));
            }
        );
        $this->application->getEventManager()->attach(MvcEvent::EVENT_FINISH, function (MvcEvent $e) {
            $e->setResponse(new Response());
            $e->setError('error-should-not-persist');
            $e->setParam('testing', true);
        }, -10000);

        $this->application->handle($this->request);
        $this->application->handle($this->request);
    }

    public function testHandleTriggersEventsInOrder()
    {
        $this->application->getEventManager()->attach(MvcEvent::EVENT_FINISH, function (MvcEvent $e) {
            $e->setResponse(new Response());
        }, -10000);
        $this->application->bootstrap();

        $expected = [
            MvcEvent::EVENT_ROUTE,
            MvcEvent::EVENT_DISPATCH,
            MvcEvent::EVENT_RENDER,
            MvcEvent::EVENT_FINISH,
        ];
        $triggered = [];
        $this->application->getEventManager()->attach(
            '*',
            function (MvcEvent $e) use (&$triggered) {
                $triggered[] = $e->getName();
            }
        );

        $this->application->handle($this->request);
        $this->assertEquals($expected, $triggered);
    }

    public function testDispatchAndRenderEventsAreSkippedWhenRouteReturnsResponse()
    {
        $this->application->bootstrap();

        $expected = [
            MvcEvent::EVENT_ROUTE,
            MvcEvent::EVENT_FINISH,
        ];
        $triggered = [];
        $this->application->getEventManager()->attach(
            '*',
            function (MvcEvent $e) use (&$triggered) {
                $triggered[] = $e->getName();
            },
            10000
        );

        $response = new Response();
        $this->application->getEventManager()->attach(
            MvcEvent::EVENT_ROUTE,
            function (MvcEvent $e) use ($response) {
                return $response;
            }
        );
        $this->application->getEventManager()->attach(
            MvcEvent::EVENT_FINISH,
            function (MvcEvent $e) use ($response) {
                $this->assertSame($response, $e->getResponse());
            }
        );

        $returnedResponse = $this->application->handle($this->request);
        $this->assertEquals($expected, $triggered);
        $this->assertSame($response, $returnedResponse);
    }

    public function testResponseReturnedInRouteEventShortCircuits()
    {
        $this->application->getEventManager()->attach(
            MvcEvent::EVENT_ROUTE,
            function (MvcEvent $e) {
                return new Response();
            }
        );
        $this->application->getEventManager()->attach(
            MvcEvent::EVENT_ROUTE,
            function (MvcEvent $e) {
                $this->fail('Must not be invoked');
            }
        );

        $this->application->handle($this->request);
        $this->addToAssertionCount(1);
    }

    public function testDispatchEventIsSkippedWhenRouteEventSetsError()
    {
        $this->application->getEventManager()->attach(MvcEvent::EVENT_FINISH, function (MvcEvent $e) {
            $e->setResponse(new Response());
        }, -10000);
        $this->application->bootstrap();

        $expected = [
            MvcEvent::EVENT_ROUTE,
            MvcEvent::EVENT_RENDER,
            MvcEvent::EVENT_FINISH,
        ];
        $triggered = [];
        $this->application->getEventManager()->attach(
            '*',
            function (MvcEvent $e) use (&$triggered) {
                $triggered[] = $e->getName();
            },
            10000
        );

        $response = new Response();
        $this->application->getEventManager()->attach(
            MvcEvent::EVENT_ROUTE,
            function (MvcEvent $e) use ($response) {
                $e->setError('test-error');
            }
        );

        $this->application->handle($this->request);
        $this->assertEquals($expected, $triggered);
    }

    public function testErrorSetInRouteEventShortCircuits()
    {
        $this->application->getEventManager()->attach(MvcEvent::EVENT_FINISH, function (MvcEvent $e) {
            $e->setResponse(new Response());
        }, -10000);

        $this->application->getEventManager()->attach(
            MvcEvent::EVENT_ROUTE,
            function (MvcEvent $e) {
                $e->setError('test-error');
            }
        );
        $this->application->getEventManager()->attach(
            MvcEvent::EVENT_ROUTE,
            function (MvcEvent $e) {
                $this->fail('Must not be invoked');
            }
        );

        $this->application->handle($this->request);
        $this->addToAssertionCount(1);
    }

    public function testResponseReturnedFromRouteIsSetIntoMvcEvent()
    {
        $response = new Response();
        $this->application->getEventManager()->attach(
            MvcEvent::EVENT_ROUTE,
            function (MvcEvent $e) use ($response) {
                return $response;
            }
        );

        $this->application->getEventManager()->attach(
            MvcEvent::EVENT_FINISH,
            function (MvcEvent $e) use ($response) {
                $this->assertSame($response, $e->getResponse());
            }
        );

        $this->application->handle($this->request);
    }

    public function testRenderEventIsSkippedWhenDispatchReturnsResponse()
    {
        $this->application->bootstrap();

        $expected = [
            MvcEvent::EVENT_ROUTE,
            MvcEvent::EVENT_DISPATCH,
            MvcEvent::EVENT_FINISH,
        ];
        $triggered = [];
        $this->application->getEventManager()->attach(
            '*',
            function (MvcEvent $e) use (&$triggered) {
                $triggered[] = $e->getName();
            },
            10000
        );

        $response = new Response();
        $this->application->getEventManager()->attach(
            MvcEvent::EVENT_DISPATCH,
            function (MvcEvent $e) use ($response) {
                return $response;
            }
        );
        $this->application->getEventManager()->attach(
            MvcEvent::EVENT_FINISH,
            function (MvcEvent $e) use ($response) {
                $this->assertSame($response, $e->getResponse());
            }
        );

        $returnedResponse = $this->application->handle($this->request);
        $this->assertEquals($expected, $triggered);
        $this->assertSame($response, $returnedResponse);
    }

    public function testResponseReturnedInDispatchEventShortCircuits()
    {
        $this->application->getEventManager()->attach(
            MvcEvent::EVENT_DISPATCH,
            function (MvcEvent $e) {
                return new Response();
            }
        );
        $this->application->getEventManager()->attach(
            MvcEvent::EVENT_DISPATCH,
            function (MvcEvent $e) {
                $this->fail('Must not be invoked');
            }
        );

        $this->application->handle($this->request);
        $this->addToAssertionCount(1);
    }

    public function testRenderAndFinishShouldTriggerEvenIfDispatchSetsError()
    {
        $this->application->bootstrap();
        $this->application->getEventManager()->attach(MvcEvent::EVENT_FINISH, function (MvcEvent $e) {
            $e->setResponse(new Response());
        }, -10000);

        $this->application->getEventManager()->attach(
            MvcEvent::EVENT_DISPATCH,
            function (MvcEvent $e) {
                $e->setError('test-error');
            }
        );

        $expected = [
            MvcEvent::EVENT_ROUTE,
            MvcEvent::EVENT_DISPATCH,
            MvcEvent::EVENT_RENDER,
            MvcEvent::EVENT_FINISH,
        ];
        $triggered = [];
        $this->application->getEventManager()->attach(
            '*',
            function (MvcEvent $e) use (&$triggered) {
                $triggered[] = $e->getName();
            },
            10000
        );

        $this->application->handle($this->request);
        $this->assertEquals($expected, $triggered);
    }

    public function testErrorSetInDispatchEventShortCircuits()
    {
        $this->application->getEventManager()->attach(MvcEvent::EVENT_FINISH, function (MvcEvent $e) {
            $e->setResponse(new Response());
        }, -10000);

        $this->application->getEventManager()->attach(
            MvcEvent::EVENT_DISPATCH,
            function (MvcEvent $e) {
                $e->setError('test-error');
            }
        );
        $this->application->getEventManager()->attach(
            MvcEvent::EVENT_DISPATCH,
            function (MvcEvent $e) {
                $this->fail('Must not be invoked');
            }
        );

        $this->application->handle($this->request);
        $this->addToAssertionCount(1);
    }

    public function testResponseReturnedFromDispatchIsSetIntoMvcEvent()
    {
        $response = new Response();
        $this->application->getEventManager()->attach(
            MvcEvent::EVENT_DISPATCH,
            function (MvcEvent $e) use ($response) {
                return $response;
            }
        );

        $this->application->getEventManager()->attach(
            MvcEvent::EVENT_FINISH,
            function (MvcEvent $e) use ($response) {
                $this->assertSame($response, $e->getResponse());
            }
        );

        $this->application->handle($this->request);
    }

    public function testResponseSetInRenderEventOverridesOther()
    {
        $this->application->getEventManager()->attach(
            MvcEvent::EVENT_DISPATCH,
            function (MvcEvent $e) {
                $e->setResponse(new Response());
            }
        );

        $response = new Response();
        $this->application->getEventManager()->attach(
            MvcEvent::EVENT_FINISH,
            function (MvcEvent $e) use ($response) {
                $e->setResponse($response);
            }
        );

        $returnedResponse = $this->application->handle($this->request);
        $this->assertSame($response, $returnedResponse);
    }

    public function testResponseSetInFinishEventOverridesOther()
    {
        $this->application->getEventManager()->attach(
            MvcEvent::EVENT_DISPATCH,
            function (MvcEvent $e) {
                return new Response();
            }
        );

        $response = new Response();
        $this->application->getEventManager()->attach(
            MvcEvent::EVENT_FINISH,
            function (MvcEvent $e) use ($response) {
                $e->setResponse($response);
            }
        );

        $returnedResponse = $this->application->handle($this->request);
        $this->assertSame($response, $returnedResponse);
    }

    public function testApplicationThrowsIfNoResponseAvailable()
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Application failed to produce a response');

        $this->application->handle($this->request);
    }
}
