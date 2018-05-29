<?php
/**
 * @link      http://github.com/zendframework/zend-mvc for the canonical source repository
 * @copyright Copyright (c) 2005-2018 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-mvc/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace ZendTest\Mvc\View;

use PHPUnit\Framework\TestCase;
use Zend\Diactoros\Response;
use Zend\Diactoros\ServerRequest;
use Zend\EventManager\EventManager;
use Zend\EventManager\SharedEventManager;
use Zend\EventManager\Test\EventListenerIntrospectionTrait;
use Zend\Mvc\Application;
use Zend\Mvc\MvcEvent;
use Zend\Mvc\View\Http\DefaultRenderingStrategy;
use Zend\ServiceManager\Config;
use Zend\ServiceManager\ServiceManager;
use Zend\View\Renderer\PhpRenderer;
use Zend\View\View;
use Zend\View\Model\ViewModel;
use Zend\View\Resolver\TemplateMapResolver;
use Zend\View\Strategy\PhpRendererStrategy;
use Zend\View\ViewEvent;

class DefaultRendereringStrategyTest extends TestCase
{
    use EventListenerIntrospectionTrait;

    protected $event;
    protected $request;
    protected $response;
    protected $view;
    protected $renderer;
    protected $strategy;

    public function setUp()
    {
        $this->view     = new View();
        $this->request  = new ServerRequest([], [], null, 'GET', 'php://memory');
        $this->response = new Response();
        $this->event    = new MvcEvent();
        $this->renderer = new PhpRenderer();

        $this->event->setRequest($this->request);
        $this->event->setResponse($this->response);

        $this->strategy = new DefaultRenderingStrategy($this->view);
    }

    public function testAttachesRendererAtExpectedPriority()
    {
        $evm = new EventManager();
        $this->strategy->attach($evm);
        $events = [MvcEvent::EVENT_RENDER, MvcEvent::EVENT_RENDER_ERROR];

        foreach ($events as $event) {
            $this->assertListenerAtPriority(
                [$this->strategy, 'render'],
                -10000,
                $event,
                $evm,
                'Renderer not found'
            );
        }
    }

    public function testCanDetachListenersFromEventManager()
    {
        $events = new EventManager();
        $this->strategy->attach($events);
        $listeners = $this->getArrayOfListenersForEvent(MvcEvent::EVENT_RENDER, $events);
        $this->assertCount(1, $listeners);

        $this->strategy->detach($events);
        $listeners = $this->getArrayOfListenersForEvent(MvcEvent::EVENT_RENDER, $events);
        $this->assertCount(0, $listeners);
    }

    public function testWillRenderAlternateStrategyWhenSelected()
    {
        $renderer = new TestAsset\DumbStrategy();
        $this->view->addRenderingStrategy(function ($e) use ($renderer) {
            return $renderer;
        }, 100);
        $this->view->addResponseStrategy(function (ViewEvent $e) {
            $e->getResponse()->setContent($e->getResult());
        }, 100);
        $model = new ViewModel(['foo' => 'bar']);
        $model->setOption('template', 'content');
        $this->event->setViewModel($model);

        $result = $this->strategy->render($this->event);

        $expected = sprintf('content (%s): %s', json_encode(['template' => 'content']), json_encode(['foo' => 'bar']));
        $this->assertEquals($expected, $result->getBody()->__toString());
    }

    public function testLayoutTemplateIsLayoutByDefault()
    {
        $this->assertEquals('layout', $this->strategy->getLayoutTemplate());
    }

    public function testLayoutTemplateIsMutable()
    {
        $this->strategy->setLayoutTemplate('alternate/layout');
        $this->assertEquals('alternate/layout', $this->strategy->getLayoutTemplate());
    }

    public function testBypassesRenderingIfResultIsAResponse()
    {
        $renderer = new TestAsset\DumbStrategy();
        $this->view->addRenderingStrategy(function ($e) use ($renderer) {
            return $renderer;
        }, 100);
        $model = new ViewModel(['foo' => 'bar']);
        $model->setOption('template', 'content');
        $this->event->setViewModel($model);
        $this->event->setResult($this->response);

        $result = $this->strategy->render($this->event);
        $this->assertSame($this->response, $result);
    }

    public function testTriggersRenderErrorEventInCaseOfRenderingException()
    {
        $resolver = new TemplateMapResolver();
        $resolver->add('exception', __DIR__ . '/_files/exception.phtml');
        $this->renderer->setResolver($resolver);

        $strategy = new PhpRendererStrategy($this->renderer);
        $strategy->attach($this->view->getEventManager());

        $model = new ViewModel();
        $model->setTemplate('exception');
        $this->event->setViewModel($model);

        $services = new ServiceManager();
        (new Config([
            'invokables' => [
                'SharedEventManager' => SharedEventManager::class,
            ],
            'factories' => [
                'EventManager' => function ($services) {
                    $sharedEvents = $services->get('SharedEventManager');
                    $events = new EventManager($sharedEvents);
                    return $events;
                },
            ],
            'shared' => [
                'EventManager' => false,
            ],
        ]))->configureServiceManager($services);

        $application = new Application($services, $services->get('EventManager'));
        $this->event->setApplication($application);

        $test = (object) ['flag' => false];
        $application->getEventManager()->attach(MvcEvent::EVENT_RENDER_ERROR, function ($e) use ($test) {
            $test->flag      = true;
            $test->error     = $e->getError();
            $test->exception = $e->getParam('exception');
        });

        $this->strategy->render($this->event);

        $this->assertTrue($test->flag);
        $this->assertEquals(Application::ERROR_EXCEPTION, $test->error);
        $this->assertInstanceOf('Exception', $test->exception);
        $this->assertContains('script', $test->exception->getMessage());
    }
}
