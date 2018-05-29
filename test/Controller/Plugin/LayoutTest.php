<?php
/**
 * @link      http://github.com/zendframework/zend-mvc for the canonical source repository
 * @copyright Copyright (c) 2005-2018 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-mvc/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace ZendTest\Mvc\Controller\Plugin;

use PHPUnit\Framework\TestCase;
use Zend\Mvc\Controller\Plugin\Layout as LayoutPlugin;
use Zend\Mvc\Exception\DomainException;
use Zend\Mvc\MvcEvent;
use ZendTest\Mvc\Controller\TestAsset\SampleController;
use Zend\View\Model\ViewModel;

class LayoutTest extends TestCase
{
    public function setUp()
    {
        $this->markTestIncomplete('PluginController fate is undecided');
        $this->event      = $event = new MvcEvent();
        $this->controller = new SampleController();
        $this->controller->setEvent($event);

        $this->plugin = $this->controller->plugin('layout');
    }

    public function testPluginWithoutControllerRaisesDomainException()
    {
        $plugin = new LayoutPlugin();
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('requires a controller');
        $plugin->setTemplate('home');
    }

    public function testSetTemplateAltersTemplateInEventViewModel()
    {
        $model = new ViewModel();
        $model->setTemplate('layout');
        $this->event->setViewModel($model);

        $this->plugin->setTemplate('alternate/layout');
        $this->assertEquals('alternate/layout', $model->getTemplate());
    }

    public function testInvokeProxiesToSetTemplate()
    {
        $model = new ViewModel();
        $model->setTemplate('layout');
        $this->event->setViewModel($model);

        $plugin = $this->plugin;
        $plugin('alternate/layout');
        $this->assertEquals('alternate/layout', $model->getTemplate());
    }

    public function testCallingInvokeWithNoArgumentsReturnsViewModel()
    {
        $model = new ViewModel();
        $model->setTemplate('layout');
        $this->event->setViewModel($model);

        $plugin = $this->plugin;
        $result = $plugin();
        $this->assertSame($model, $result);
    }
}
