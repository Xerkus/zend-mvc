<?php
/**
 * @link      http://github.com/zendframework/zend-mvc for the canonical source repository
 * @copyright Copyright (c) 2005-2017 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-mvc/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace ZendTest\Mvc;

use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;
use Interop\Container\ContainerInterface;

// @TODO update to Psr after service manager 4.0 is out

/**
 * Helper methods for mock Psr\Container\ContainerInterface.
 */
trait ContainerTrait
{
    /**
     * Returns a prophecy for ContainerInterface.
     *
     * By default returns false for unknown `has('service')` method.
     *
     * @return ObjectProphecy|ContainerInterface
     */
    protected function mockContainerInterface() : ObjectProphecy
    {
        $container = $this->prophesize(ContainerInterface::class);
        $container->has(Argument::type('string'))->willReturn(false);

        return $container;
    }

    /**
     * Inject a service into the container mock.
     *
     * Adjust `has('service')` and `get('service')` returns.
     *
     * @param ObjectProphecy|ContainerInterface $container Prophesized container to configure
     * @param mixed $service
     */
    protected function injectServiceInContainer(ObjectProphecy $container, string $serviceName, $service) : void
    {
        $container->has($serviceName)->willReturn(true);
        $container->get($serviceName)->willReturn($service);
    }
}
