<?php
/**
 * @link      http://github.com/zendframework/zend-mvc for the canonical source repository
 * @copyright Copyright (c) 2005-2018 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-mvc/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace ZendTest\Mvc\Controller\TestAsset;

use Zend\EventManager\EventInterface as Event;
use Zend\Mvc\InjectApplicationEventInterface;
use Zend\Stdlib\DispatchableInterface;
use Zend\Stdlib\RequestInterface as Request;
use Zend\Stdlib\ResponseInterface as Response;

class UnlocatableEventfulController implements DispatchableInterface, InjectApplicationEventInterface
{
    protected $event;

    public function setEvent(Event $event)
    {
        $this->event = $event;
    }

    public function getEvent()
    {
        return $this->event;
    }

    public function dispatch(Request $request, Response $response = null)
    {
    }
}
