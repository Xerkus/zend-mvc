<?php
/**
 * @link      http://github.com/zendframework/zend-mvc for the canonical source repository
 * @copyright Copyright (c) 2005-2018 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-mvc/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Zend\Mvc\Container;

use Interop\Container\ContainerInterface;
use Zend\Mvc\RouteListener;
use Zend\ServiceManager\Factory\FactoryInterface;

final class RouteListenerFactory implements FactoryInterface
{
    /**
     * Create an object
     *
     * @param  string $requestedName
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null) : RouteListener
    {
        return new RouteListener($container->get('Zend\Mvc\Router'));
    }
}
