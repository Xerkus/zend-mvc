<?php
/**
 * @link      http://github.com/zendframework/zend-mvc for the canonical source repository
 * @copyright Copyright (c) 2005-2018 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-mvc/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Zend\Mvc\Container;

use Psr\Container\ContainerInterface;
use Zend\Router\Container\RouterFactory as BaseRouterFactory;

use function array_merge;

class RouterFactory extends BaseRouterFactory
{
    public function getRouterConfig(ContainerInterface $container) : array
    {
        $config = $container->has('config') ? $container->get('config') : [];
        $routerConfig = $config['router'] ?? [];
        return array_merge(['routes' => [], 'prototypes' => []], $routerConfig);
    }
}
