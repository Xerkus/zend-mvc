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
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use stdClass;
use Zend\Mvc\Controller\ControllerInterface;
use Zend\Mvc\Controller\ControllerManager;
use Zend\Mvc\Controller\PluginManager as ControllerPluginManager;
use Zend\ServiceManager\Config;
use Zend\ServiceManager\Exception\InvalidServiceException;
use Zend\ServiceManager\Exception\ServiceNotFoundException;
use Zend\ServiceManager\ServiceManager;

/**
 * @covers \Zend\Mvc\Controller\ControllerManager
 */
class ControllerManagerTest extends TestCase
{
    public function setUp()
    {
        $this->services = new ServiceManager();
        (new Config([
            'factories' => [
                'ControllerPluginManager' => function ($services) {
                    return new ControllerPluginManager($services);
                },
            ],
            'services' => [
                'Foo'       => 'Bar',
            ],
        ]))->configureServiceManager($this->services);

        $this->controllers = new ControllerManager($this->services);
    }

    /**
     * @covers \Zend\ServiceManager\ServiceManager::has
     * @covers \Zend\ServiceManager\AbstractPluginManager::get
     */
    public function testDoNotUsePeeringServiceManagers()
    {
        $this->assertFalse($this->controllers->has('Foo'));
        $this->expectException(ServiceNotFoundException::class);
        $this->controllers->get('Foo');
    }

    public function testAllowsInstanceOfControllerInterface()
    {
        $controller = new class implements ControllerInterface {
            public function dispatch(ServerRequestInterface $request, ResponseInterface $responsePrototype = null)
            {
                // noop
            }
        };

        $this->controllers->setFactory('foo', function () use ($controller) {
            return $controller;
        });

        $this->assertSame($controller, $this->controllers->get('foo'));
    }

    public function testAllowsInstanceOfRequestHandlerInterface()
    {
        $controller = new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request) : ResponseInterface
            {
                // noop
            }
        };

        $this->controllers->setFactory('foo', function () use ($controller) {
            return $controller;
        });

        $this->assertSame($controller, $this->controllers->get('foo'));
    }

    public function testThrowsOnInvalidControllerType()
    {
        $this->controllers->setFactory('foo', function () {
            return new stdClass();
        });

        $this->expectException(InvalidServiceException::class);
        $this->expectExceptionMessage(sprintf(
            'Plugin of type "stdClass" is invalid; must implement %s or %s',
            ControllerInterface::class,
            RequestHandlerInterface::class
        ));
        $this->controllers->get('foo');
    }
}
