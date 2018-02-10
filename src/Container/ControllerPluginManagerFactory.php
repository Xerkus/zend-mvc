<?php
/**
 * @link      http://github.com/zendframework/zend-mvc for the canonical source repository
 * @copyright Copyright (c) 2005-2018 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-mvc/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Zend\Mvc\Container;

use Interop\Container\ContainerInterface;
use Zend\Mvc\Controller\PluginManager;
use Zend\ServiceManager\Factory\FactoryInterface;

class ControllerPluginManagerFactory implements FactoryInterface
{
    /**
     * Create the controller plugin manager
     *
     * @param string $name
     */
    public function __invoke(ContainerInterface $container, $name, array $options = null) : PluginManager
    {
        if (null !== $options) {
            return new PluginManager($container, $options);
        }
        return new PluginManager($container, static::getControllerPluginsConfig($container));
    }

    public static function getControllerPluginsConfig(ContainerInterface $container) : array
    {
        $config = $container->has('config') ? $container->get('config') : [];
        return $config['controller_plugins'] ?? [];
    }
}
