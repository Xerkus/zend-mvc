<?php
/**
 * @link      http://github.com/zendframework/zend-mvc for the canonical source repository
 * @copyright Copyright (c) 2005-2018 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-mvc/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace ZendTest\Mvc\Container;

use ArrayObject;
use Interop\Container\ContainerInterface;
use PHPUnit\Framework\TestCase;
use Zend\Mvc\Container\InjectTemplateListenerFactory;
use Zend\Mvc\View\Http\InjectTemplateListener;
use Zend\ServiceManager\ServiceLocatorInterface;

/**
 * Tests for {@see \Zend\Mvc\Container\InjectTemplateListenerFactory}
 *
 * @covers \Zend\Mvc\Container\InjectTemplateListenerFactory
 */
class InjectTemplateListenerFactoryTest extends TestCase
{
    public function testFactoryCanCreateInjectTemplateListener()
    {
        $this->buildInjectTemplateListenerWithConfig([]);
    }

    public function testFactoryCanSetControllerMap()
    {
        $listener = $this->buildInjectTemplateListenerWithConfig([
            'view_manager' => [
                'controller_map' => [
                    'SomeModule' => 'some/module',
                ],
            ],
        ]);

        $this->assertEquals('some/module', $listener->mapController("SomeModule"));
    }

    public function testFactoryCanSetControllerMapViaArrayAccessVM()
    {
        $listener = $this->buildInjectTemplateListenerWithConfig([
            'view_manager' => new ArrayObject([
                'controller_map' => [
                    // must be an array due to type hinting on setControllerMap()
                    'SomeModule' => 'some/module',
                ],
            ])
        ]);

        $this->assertEquals('some/module', $listener->mapController("SomeModule"));
    }

    /**
     * @param mixed $config
     *
     * @return \PHPUnit_Framework_MockObject_MockObject|\Zend\Mvc\View\Http\InjectTemplateListener
     */
    private function buildInjectTemplateListenerWithConfig($config)
    {
        $serviceLocator = $this->prophesize(ServiceLocatorInterface::class);
        $serviceLocator->willImplement(ContainerInterface::class);

        $serviceLocator->get('config')->willReturn($config);

        $factory  = new InjectTemplateListenerFactory();
        $listener = $factory($serviceLocator->reveal(), 'InjectTemplateListener');

        $this->assertInstanceOf(InjectTemplateListener::class, $listener);

        return $listener;
    }
}
