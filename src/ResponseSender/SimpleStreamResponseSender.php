<?php
/**
 * @link      http://github.com/zendframework/zend-mvc for the canonical source repository
 * @copyright Copyright (c) 2005-2018 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-mvc/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Zend\Mvc\ResponseSender;

use Zend\Http\Response\Stream;
use Zend\Mvc\Exception\DomainException;

class SimpleStreamResponseSender extends AbstractResponseSender
{
    /**
     * Send the stream
     *
     * @param  SendResponseEvent $event
     * @return SimpleStreamResponseSender
     */
    public function sendStream(SendResponseEvent $event)
    {
        if ($event->contentSent()) {
            return $this;
        }
        $response = $event->getResponse();
        if (! $response instanceof Stream) {
            throw new DomainException('Attempting to send stream of non-stream response');
        }
        $stream   = $response->getStream();
        fpassthru($stream);
        $event->setContentSent();
    }

    /**
     * Send stream response
     *
     * @param  SendResponseEvent $event
     * @return SimpleStreamResponseSender
     */
    public function __invoke(SendResponseEvent $event)
    {
        $response = $event->getResponse();
        if (! $response instanceof Stream) {
            return $this;
        }

        $this->sendHeaders($event);
        $this->sendStream($event);
        $event->stopPropagation(true);
        return $this;
    }
}
