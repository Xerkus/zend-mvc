<?php
/**
 * @link      http://github.com/zendframework/zend-mvc for the canonical source repository
 * @copyright Copyright (c) 2005-2018 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-mvc/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace ZendTest\Mvc\ResponseSender;

use PHPUnit\Framework\TestCase;
use Zend\Mvc\ResponseSender\SendResponseEvent;
use Zend\Stdlib\ResponseInterface;

class SendResponseEventTest extends TestCase
{
    public function testContentSentAndHeadersSent()
    {
        $mockResponse = $this->getMockForAbstractClass(ResponseInterface::class);
        $mockResponse2 = $this->getMockForAbstractClass(ResponseInterface::class);
        $event = new SendResponseEvent();
        $event->setResponse($mockResponse);
        $this->assertFalse($event->headersSent());
        $this->assertFalse($event->contentSent());
        $event->setHeadersSent();
        $event->setContentSent();
        $this->assertTrue($event->headersSent());
        $this->assertTrue($event->contentSent());
        $event->setResponse($mockResponse2);
        $this->assertFalse($event->headersSent());
        $this->assertFalse($event->contentSent());
    }
}
