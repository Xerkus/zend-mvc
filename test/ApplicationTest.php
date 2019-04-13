<?php
/**
 * @see       https://github.com/zendframework/zend-mvc for the canonical source repository
 * @copyright Copyright (c) 2005-2019 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-mvc/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace ZendTest\Mvc;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionProperty;
use stdClass;
use Throwable;
use Zend\Diactoros\ServerRequest;
use Zend\EventManager\EventManager;
use Zend\EventManager\SharedEventManager;
use Zend\EventManager\Test\EventListenerIntrospectionTrait;
use Zend\Http\PhpEnvironment;
use Zend\Http\PhpEnvironment\Response;
use Zend\Mvc\Application;
use Zend\Mvc\ConfigProvider;
use Zend\Mvc\Controller\ControllerManager;
use Zend\Mvc\MvcEvent;
use Zend\Router;
use Zend\ServiceManager\ServiceManager;
use Zend\Stdlib\ArrayUtils;
use Zend\Stdlib\ResponseInterface;
use Zend\View\Model\ViewModel;

use function array_values;
use function get_class;
use function sprintf;

/**
 * @covers \Zend\Mvc\Application
 */
class ApplicationTest extends TestCase
{
    use EventListenerIntrospectionTrait;

    /** @var ServiceManager */
    protected $serviceManager;

    /** @var Application */
    protected $application;

    public function setUp() : void
    {
        $serviceConfig = ArrayUtils::merge(
            (new ConfigProvider())->getDependencies(),
            (new Router\ConfigProvider())->getDependencyConfig()
        );

        $serviceConfig        = ArrayUtils::merge(
            $serviceConfig,
            [
                'invokables' => [
                    'Response'          => PhpEnvironment\Response::class,
                    'ViewManager'       => TestAsset\MockViewManager::class,
                    'BootstrapListener' => TestAsset\StubBootstrapListener::class,
                ],
                'factories'  => [
                    'Router' => Router\RouterFactory::class,
                ],
                'services'   => [
                    'config' => [],
                ],
            ]
        );
        $this->serviceManager = new ServiceManager($serviceConfig);
        $this->serviceManager->setAllowOverride(true);
        $this->application = $this->serviceManager->get('Application');
    }

    public function testEventManagerIsPopulated()
    {
        $events       = $this->serviceManager->get('EventManager');
        $sharedEvents = $events->getSharedManager();
        $appEvents    = $this->application->getEventManager();
        $this->assertInstanceOf(EventManager::class, $appEvents);
        $this->assertNotSame($events, $appEvents);
        $this->assertSame($sharedEvents, $appEvents->getSharedManager());
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
        $this->assertSame($this->serviceManager, $this->application->getContainer());
    }

    public function testEventsAreEmptyAtFirst()
    {
        $application = new Application(
            $this->serviceManager,
            new EventManager(new SharedEventManager())
        );
        /** @var EventManager $events */
        $events           = $application->getEventManager();
        $registeredEvents = $this->getEventsFromEventManager($events);
        $this->assertEquals([], $registeredEvents);

        $sharedEvents = $events->getSharedManager();
        $this->assertInstanceOf(SharedEventManager::class, $sharedEvents);
        /**
         * @todo change test not to depend on internal state
         */
        $property = new ReflectionProperty(SharedEventManager::class, 'identifiers');
        $property->setAccessible(true);
        $this->assertEquals([], $property->getValue($sharedEvents));
    }

    public function testBootstrapRegistersConfiguredMvcEvent()
    {
        $this->application->bootstrap();
        $event = $this->application->getMvcEvent();
        $this->assertInstanceOf(MvcEvent::class, $event);

        $router = $this->serviceManager->get('HttpRouter');

        $this->assertFalse($event->isError());
        $this->assertNull($event->getRequest());
        $this->assertInstanceOf(ResponseInterface::class, $event->getResponse());
        $this->assertSame($router, $event->getRouter());
        $this->assertSame($this->application, $event->getApplication());
        $this->assertSame($this->application, $event->getTarget());
    }

    public function testBootstrapTriggersBootstrapEvent()
    {
        /** @var MockObject|callable $bootstrapListener */
        $bootstrapListener = $this->getMockBuilder(stdClass::class)
            ->setMethods(['__invoke'])
            ->getMock();
        $bootstrapListener->expects(self::once())
            ->method('__invoke');
        $this->application
            ->getEventManager()
            ->attach(MvcEvent::EVENT_BOOTSTRAP, $bootstrapListener);
        $this->application->bootstrap();
    }

    public function testBootstrapTriggersBootstrapEventOnce()
    {
        /** @var MockObject|callable $bootstrapListener */
        $bootstrapListener = $this->getMockBuilder(stdClass::class)
            ->setMethods(['__invoke'])
            ->getMock();
        $bootstrapListener->expects(self::once())
            ->method('__invoke');
        $this->application
            ->getEventManager()
            ->attach(MvcEvent::EVENT_BOOTSTRAP, $bootstrapListener);
        $this->application->bootstrap();
        $this->application->bootstrap();
    }

    public function setupPathController($addService = true)
    {
        $request = new ServerRequest([], [], 'http://example.local/path');

        $router = $this->serviceManager->get('HttpRouter');
        $route  = Router\Http\Literal::factory([
            'route'    => '/path',
            'defaults' => [
                'controller' => 'path',
            ],
        ]);
        $router->addRoute('path', $route);
        $this->serviceManager->setService('HttpRouter', $router);
        $this->serviceManager->setService('Router', $router);

        if ($addService) {
            $this->services->addFactory('ControllerManager', function ($services) {
                return new ControllerManager($services, [
                    'factories' => [
                        'path' => function () {
                            return new TestAsset\PathController();
                        },
                    ],
                ]);
            });
        }

        $this->application->bootstrap();
        return $request;
    }

    public function setupActionController()
    {
        $request = new ServerRequest([], [], 'http://example.local/sample');

        $router = $this->serviceManager->get('HttpRouter');
        $route  = Router\Http\Literal::factory([
            'route'    => '/sample',
            'defaults' => [
                'controller' => 'sample',
                'action'     => 'test',
            ],
        ]);
        $router->addRoute('sample', $route);

        $this->serviceManager->setFactory('ControllerManager', function ($services) {
            return new ControllerManager($services, [
                'factories' => [
                    'sample' => function () {
                        return new Controller\TestAsset\SampleController();
                    },
                ],
            ]);
        });

        $this->application->bootstrap();
        return $request;
    }

    public function setupBadController($addService = true, $action = 'test')
    {
        $request = new ServerRequest([], [], 'http://example.local/bad');

        $router = $this->serviceManager->get('HttpRouter');
        $route  = Router\Http\Literal::factory([
            'route'    => '/bad',
            'defaults' => [
                'controller' => 'bad',
                'action'     => $action,
            ],
        ]);
        $router->addRoute('bad', $route);

        if ($addService) {
            $this->serviceManager
                ->get('ControllerManager')
                ->setFactory('bad', function () {
                    return new Controller\TestAsset\BadController();
                });
        }

        $this->application->bootstrap();
        return $request;
    }

    public function testFinishEventIsTriggeredAfterDispatching()
    {
        $request     = $this->setupActionController();
        $application = $this->application;
        $application->getEventManager()->attach(MvcEvent::EVENT_FINISH, function ($e) {
            return $e->getResponse()->setContent($e->getResponse()->getBody() . 'foobar');
        });
        $application->handle($request);
        $this->assertStringContainsString(
            'foobar',
            $this->application->getMvcEvent()->getResponse()->getBody(),
            'The "finish" event was not triggered ("foobar" not in response)'
        );
    }

    /**
     * @group error-handling
     */
    public function testRoutingFailureShouldTriggerDispatchError()
    {
        $request     = $this->setupBadController();
        $application = $this->application;
        $router      = new Router\SimpleRouteStack();
        $event       = $application->getMvcEvent();
        $event->setRouter($router);

        $response = $application->getMvcEvent()->getResponse();
        $events   = $application->getEventManager();
        $events->attach(MvcEvent::EVENT_DISPATCH_ERROR, function ($e) use ($response) {
            $error = $e->getError();
            $response->setContent('Code: ' . $error);
            return $response;
        });

        $application->handle($request);
        $this->assertTrue($event->isError());
        $this->assertStringContainsString(Application::ERROR_ROUTER_NO_MATCH, $response->getContent());
    }

    /**
     * @group error-handling
     */
    public function testLocatorExceptionShouldTriggerDispatchError()
    {
        $request     = $this->setupPathController(false);
        $application = $this->application;
        $response    = new Response();
        $application->getEventManager()->attach(MvcEvent::EVENT_DISPATCH_ERROR, function ($e) use ($response) {
            return $response;
        });

        $result = $application->handle($request);
        $this->assertSame($response, $application->getMvcEvent()->getResponse(), get_class($result));
    }

    /**
     * @requires PHP 7.0
     * @group error-handling
     */
    public function testPhp7ErrorRaisedInDispatchableShouldRaiseDispatchErrorEvent()
    {
        /**
         * @todo move to a proper place, it belongs to dispatch listener and integration tests
         */
        $request  = $this->setupBadController(true, 'test-php7-error');
        $response = $this->application->getMvcEvent()->getResponse();
        $events   = $this->application->getEventManager();
        $events->attach(MvcEvent::EVENT_DISPATCH_ERROR, function ($e) use ($response) {
            $exception = $e->getParam('exception');
            $this->assertInstanceOf(Throwable::class, $exception);
            $response->setContent($exception->getMessage());
            return $response;
        });

        $this->application->handle($request);
        $this->assertStringContainsString('Raised an error', $response->getContent());
    }

    /**
     * @group error-handling
     */
    public function testFailureForRouteToReturnRouteMatchShouldPopulateEventError()
    {
        $request     = $this->setupBadController();
        $application = $this->application;
        $router      = new Router\SimpleRouteStack();
        $event       = $application->getMvcEvent();
        $event->setRouter($router);

        $response = $application->getMvcEvent()->getResponse();
        $events   = $application->getEventManager();
        $events->attach(MvcEvent::EVENT_DISPATCH_ERROR, function ($e) use ($response) {
            $error = $e->getError();
            $response->setContent('Code: ' . $error);
            return $response;
        });

        $application->handle($request);
        $this->assertTrue($event->isError());
        $this->assertEquals(Application::ERROR_ROUTER_NO_MATCH, $event->getError());
    }

    /**
     * @group ZF2-171
     */
    public function testFinishShouldRunEvenIfRouteEventReturnsResponse()
    {
        $this->application->bootstrap();
        $response = $this->application->getMvcEvent()->getResponse();
        $events   = $this->application->getEventManager();
        $events->attach(MvcEvent::EVENT_ROUTE, function ($e) use ($response) {
            return $response;
        }, 100);

        $token = new stdClass();
        $events->attach(MvcEvent::EVENT_FINISH, function ($e) use ($token) {
            $token->foo = 'bar';
        });

        $this->application->handle(new ServerRequest());
        $this->assertTrue(isset($token->foo));
        $this->assertEquals('bar', $token->foo);
    }

    /**
     * @group ZF2-171
     */
    public function testFinishShouldRunEvenIfDispatchEventReturnsResponse()
    {
        $this->application->bootstrap();
        $response = $this->application->getMvcEvent()->getResponse();
        $events   = $this->application->getEventManager();
        $events->clearListeners(MvcEvent::EVENT_ROUTE);
        $events->attach(MvcEvent::EVENT_DISPATCH, function ($e) use ($response) {
            return $response;
        }, 100);

        $token = new stdClass();
        $events->attach(MvcEvent::EVENT_FINISH, function ($e) use ($token) {
            $token->foo = 'bar';
        });

        $this->application->handle(new ServerRequest());
        $this->assertTrue(isset($token->foo));
        $this->assertEquals('bar', $token->foo);
    }

    public function testApplicationShouldBeEventTargetAtFinishEvent()
    {
        $request = $this->setupActionController();

        $application = $this->application;
        $events      = $application->getEventManager();
        $response    = $application->getMvcEvent()->getResponse();
        $events->attach(MvcEvent::EVENT_FINISH, function ($e) use ($response) {
            $response->setContent('EventClass: ' . get_class($e->getTarget()));
            return $response;
        });

        $application->handle($request);
        $this->assertStringContainsString(Application::class, $response->getContent());
    }

    public function testOnDispatchErrorEventPassedToTriggersShouldBeTheOriginalOne()
    {
        $request     = $this->setupPathController(false);
        $application = $this->application;
        $model       = $this->createMock(ViewModel::class);
        $application->getEventManager()->attach(MvcEvent::EVENT_DISPATCH_ERROR, function ($e) use ($model) {
            $e->setResult($model);
        });

        $application->handle($request);
        $event = $application->getMvcEvent();
        $this->assertInstanceOf(ViewModel::class, $event->getResult());
    }

    /**
     * @group 2981
     */
    public function testReturnsResponseFromListenerWhenRouteEventShortCircuits()
    {
        $this->application->bootstrap();
        $testResponse = new Response();
        $events       = $this->application->getEventManager();
        $events->clearListeners(MvcEvent::EVENT_DISPATCH);
        $events->attach(MvcEvent::EVENT_ROUTE, function ($e) use ($testResponse) {
            $testResponse->setContent('triggered');
            return $testResponse;
        }, 100);

        $triggered = false;
        $events->attach(MvcEvent::EVENT_FINISH, function ($e) use ($testResponse, &$triggered) {
            $this->assertSame($testResponse, $e->getResponse());
            $triggered = true;
        });

        $this->application->handle(new ServerRequest());
        $this->assertTrue($triggered);
    }

    /**
     * @group 2981
     */
    public function testReturnsResponseFromListenerWhenDispatchEventShortCircuits()
    {
        $this->application->bootstrap();
        $testResponse = new Response();
        $events       = $this->application->getEventManager();
        $events->clearListeners(MvcEvent::EVENT_ROUTE);
        $events->attach(MvcEvent::EVENT_DISPATCH, function ($e) use ($testResponse) {
            $testResponse->setContent('triggered');
            return $testResponse;
        }, 100);

        $triggered = false;
        $events->attach(MvcEvent::EVENT_FINISH, function ($e) use ($testResponse, &$triggered) {
            $this->assertSame($testResponse, $e->getResponse());
            $triggered = true;
        });

        $this->application->handle(new ServerRequest());
        $this->assertTrue($triggered);
    }

    public function testCompleteRequestShouldReturnApplicationInstance()
    {
        $r = new ReflectionMethod($this->application, 'completeRequest');
        $r->setAccessible(true);

        $this->application->bootstrap();
        $event  = $this->application->getMvcEvent();
        $result = $r->invoke($this->application, $event);
        $this->assertSame($this->application, $result);
    }

    public function testFailedRoutingShouldBePreventable()
    {
        $this->application->bootstrap();

        $response     = new Response();
        $finishMock   = $this->getMockBuilder(stdClass::class)
            ->setMethods(['__invoke'])
            ->getMock();
        $routeMock    = $this->getMockBuilder(stdClass::class)
            ->setMethods(['__invoke'])
            ->getMock();
        $dispatchMock = $this->getMockBuilder(stdClass::class)
            ->setMethods(['__invoke'])
            ->getMock();

        $routeMock->expects($this->once())->method('__invoke')->will($this->returnCallback(function (MvcEvent $event) {
            $event->stopPropagation(true);
            $event->setRouteMatch(new Router\RouteMatch([]));
        }));
        $dispatchMock->expects($this->once())->method('__invoke')->will($this->returnValue($response));
        $finishMock->expects($this->once())->method('__invoke')->will($this->returnCallback(function (MvcEvent $event) {
            $event->stopPropagation(true);
        }));

        $this->application->getEventManager()->attach(MvcEvent::EVENT_ROUTE, $routeMock, 100);
        $this->application->getEventManager()->attach(MvcEvent::EVENT_DISPATCH, $dispatchMock, 100);
        $this->application->getEventManager()->attach(MvcEvent::EVENT_FINISH, $finishMock, 100);

        $this->application->handle(new ServerRequest());
        $this->assertSame($response, $this->application->getMvcEvent()->getResponse());
    }

    public function testCanRecoverFromApplicationError()
    {
        $this->application->bootstrap();

        $response     = new Response();
        $errorMock    = $this->getMockBuilder(stdClass::class)
            ->setMethods(['__invoke'])
            ->getMock();
        $finishMock   = $this->getMockBuilder(stdClass::class)
            ->setMethods(['__invoke'])
            ->getMock();
        $routeMock    = $this->getMockBuilder(stdClass::class)
            ->setMethods(['__invoke'])
            ->getMock();
        $dispatchMock = $this->getMockBuilder(stdClass::class)
            ->setMethods(['__invoke'])
            ->getMock();

        $errorMock->expects($this->once())->method('__invoke')->will($this->returnCallback(function (MvcEvent $event) {
            $event->stopPropagation(true);
            $event->setRouteMatch(new Router\RouteMatch([]));
            $event->setError('');
        }));
        $routeMock->expects($this->once())->method('__invoke')->will($this->returnCallback(function (MvcEvent $event) {
            $event->stopPropagation(true);
            $event->setName(MvcEvent::EVENT_DISPATCH_ERROR);
            $event->setError(Application::ERROR_ROUTER_NO_MATCH);
            return $event->getApplication()->getEventManager()->triggerEvent($event)->last();
        }));
        $dispatchMock->expects($this->once())->method('__invoke')->will($this->returnValue($response));
        $finishMock->expects($this->once())->method('__invoke')->will($this->returnCallback(function (MvcEvent $event) {
            $event->stopPropagation(true);
        }));

        $this->application->getEventManager()->attach(MvcEvent::EVENT_DISPATCH_ERROR, $errorMock, 100);
        $this->application->getEventManager()->attach(MvcEvent::EVENT_ROUTE, $routeMock, 100);
        $this->application->getEventManager()->attach(MvcEvent::EVENT_DISPATCH, $dispatchMock, 100);
        $this->application->getEventManager()->attach(MvcEvent::EVENT_FINISH, $finishMock, 100);

        $this->application->handle(new ServerRequest());
        $this->assertSame($response, $this->application->getMvcEvent()->getResponse());
    }

    public function eventPropagation()
    {
        return [
            'route'    => [[MvcEvent::EVENT_ROUTE]],
            'dispatch' => [[MvcEvent::EVENT_DISPATCH, MvcEvent::EVENT_RENDER, MvcEvent::EVENT_FINISH]],
        ];
    }

    /**
     * @dataProvider eventPropagation
     */
    public function testEventPropagationStatusIsClearedBetweenEventsDuringRun($events)
    {
        // Intentionally not using default bootstrap
        $application = new Application(
            $this->serviceManager,
            new EventManager()
        );
        $event       = $this->application->getMvcEvent();
        $event->stopPropagation(true);

        // Setup listeners that stop propagation, but do nothing else
        $marker = [];
        foreach ($events as $event) {
            $marker[$event] = true;
        }
        $marker   = (object) $marker;
        $listener = function ($e) use ($marker) {
            $marker->{$e->getName()} = $e->propagationIsStopped();
            $e->stopPropagation(true);
        };
        foreach ($events as $event) {
            $application->getEventManager()->attach($event, $listener);
        }

        $application->handle(new ServerRequest());

        foreach ($events as $event) {
            $this->assertFalse($marker->{$event}, sprintf('Assertion failed for event "%s"', $event));
        }
    }
}
