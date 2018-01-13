<?php
/**
 * @link      http://github.com/zendframework/zend-mvc for the canonical source repository
 * @copyright Copyright (c) 2005-2018 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-mvc/blob/master/LICENSE.md New BSD License
 */

namespace Zend\Mvc\Container;

use Zend\Mvc\Service\AbstractPluginManagerFactory;
use Zend\Paginator\AdapterPluginManager as PaginatorPluginManager;

class PaginatorPluginManagerFactory extends AbstractPluginManagerFactory
{
    const PLUGIN_MANAGER_CLASS = PaginatorPluginManager::class;
}
