<?php
/**
 * @link      http://github.com/zendframework/zend-mvc for the canonical source repository
 * @copyright Copyright (c) 2005-2018 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-mvc/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Zend\Mvc\Container;

use Interop\Container\ContainerInterface;
use Zend\EventManager\SharedEventManager;
use Zend\ServiceManager\Factory\FactoryInterface;

class SharedEventManagerFactory implements FactoryInterface
{
    /**
     * Create a SharedEventManager instance
     *
     * @param string $name
     */
    public function __invoke(ContainerInterface $container, $name, array $options = null) : SharedEventManager
    {
        return new SharedEventManager();
    }
}
