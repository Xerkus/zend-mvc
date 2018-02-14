<?php
/**
 * @link      http://github.com/zendframework/zend-mvc for the canonical source repository
 * @copyright Copyright (c) 2005-2018 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-mvc/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Zend\Mvc\Container;

use Interop\Container\ContainerInterface;
use Zend\Mvc\Application;
use Zend\ServiceManager\Factory\FactoryInterface;

final class ApplicationFactory implements FactoryInterface
{
    /**
     * Create the Application service
     *
     * Creates a Zend\Mvc\Application service, passing it the configuration
     * service and the service manager instance.
     *
     * @param  string $name
     */
    public function __invoke(ContainerInterface $container, $name, array $options = null) : Application
    {
        $config = $container->has('config') ? $container->get('config') : [];
        $listeners = $config[Application::class]['listeners'] ?? [];
        return new Application(
            $container,
            $container->get('EventManager'),
            $listeners
        );
    }
}
