<?php
/**
 * @link      http://github.com/zendframework/zend-mvc for the canonical source repository
 * @copyright Copyright (c) 2005-2018 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-mvc/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace ZendTest\Mvc\View;

use PHPUnit\Framework\TestCase;
use Zend\EventManager\EventManager;
use Zend\EventManager\Test\EventListenerIntrospectionTrait;
use Zend\Mvc\MvcEvent;
use Zend\Mvc\View\Http\InjectViewModelListener;
use Zend\View\Model\ViewModel;

class InjectViewModelListenerTest extends TestCase
{
    use EventListenerIntrospectionTrait;

    public function setUp()
    {
        $this->listener   = new InjectViewModelListener();
        $this->event      = new MvcEvent();
    }

    public function testReplacesEventModelWithChildModelIfChildIsMarkedTerminal()
    {
        $childModel  = new ViewModel();
        $childModel->setTerminal(true);
        $this->event->setResult($childModel);

        $this->listener->injectViewModel($this->event);
        $this->assertSame($childModel, $this->event->getViewModel());
    }

    public function testAddsViewModelAsChildOfEventViewModelWhenChildIsNotTerminal()
    {
        $childModel  = new ViewModel();
        $this->event->setResult($childModel);

        $this->listener->injectViewModel($this->event);
        $model = $this->event->getViewModel();
        $this->assertNotSame($childModel, $model);
        $this->assertTrue($model->hasChildren());
        $this->assertEquals(1, count($model));
        $child = false;
        foreach ($model as $child) {
            break;
        }
        $this->assertSame($childModel, $child);
    }

    public function testLackOfViewModelInResultBypassesViewModelInjection()
    {
        $this->assertNull($this->listener->injectViewModel($this->event));
        $this->assertNull($this->event->getResult());
        $this->assertFalse($this->event->getViewModel()->hasChildren());
    }

    public function testAttachesListenersAtExpectedPriorities()
    {
        $events = new EventManager();
        $this->listener->attach($events);
        $this->assertListenerAtPriority(
            [$this->listener, 'injectViewModel'],
            -100,
            MvcEvent::EVENT_DISPATCH,
            $events
        );

        $this->assertListenerAtPriority(
            [$this->listener, 'injectViewModel'],
            -100,
            MvcEvent::EVENT_DISPATCH_ERROR,
            $events
        );
    }

    public function testDetachesListeners()
    {
        $events = new EventManager();
        $this->listener->attach($events);

        $listeners = $this->getArrayOfListenersForEvent(MvcEvent::EVENT_DISPATCH, $events);
        $this->assertCount(1, $listeners);
        $listeners = $this->getArrayOfListenersForEvent(MvcEvent::EVENT_DISPATCH_ERROR, $events);
        $this->assertCount(1, $listeners);

        $this->listener->detach($events);
        $listeners = $this->getArrayOfListenersForEvent(MvcEvent::EVENT_DISPATCH, $events);
        $this->assertCount(0, $listeners);
        $listeners = $this->getArrayOfListenersForEvent(MvcEvent::EVENT_DISPATCH_ERROR, $events);
        $this->assertCount(0, $listeners);
    }
}
