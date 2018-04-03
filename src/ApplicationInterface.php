<?php
/**
 * @link      http://github.com/zendframework/zend-mvc for the canonical source repository
 * @copyright Copyright (c) 2005-2018 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-mvc/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Zend\Mvc;

use Psr\Container\ContainerInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Zend\EventManager\EventsCapableInterface;

interface ApplicationInterface extends EventsCapableInterface, RequestHandlerInterface
{
    /**
     * Get the container
     */
    public function getContainer() : ContainerInterface;

    public function getMvcEvent() : MvcEvent;
}
