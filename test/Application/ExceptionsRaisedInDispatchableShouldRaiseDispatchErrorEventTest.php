<?php
/**
 * @see       https://github.com/zendframework/zend-mvc for the canonical source repository
 * @copyright Copyright (c) 2005-2019 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-mvc/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace ZendTest\Mvc\Application;

use PHPUnit\Framework\TestCase;
use Zend\Mvc\MvcEvent;

/**
 * @coversNothing
 */
class ExceptionsRaisedInDispatchableShouldRaiseDispatchErrorEventTest extends TestCase
{
    use BadControllerTrait;

    /**
     * @group error-handling
     */
    public function testExceptionsRaisedInDispatchableShouldRaiseDispatchErrorEvent()
    {
        $this->markTestIncomplete('Integration test needs to be reimplemented');
        $application = $this->prepareApplication();

        $response = $application->getMvcEvent()->getResponse();
        $events   = $application->getEventManager();
        $events->attach(MvcEvent::EVENT_DISPATCH_ERROR, function ($e) use ($response) {
            $exception = $e->getParam('exception');
            $this->assertInstanceOf('Exception', $exception);
            $response->setContent($exception->getMessage());
            return $response;
        });

        $application->run();
        $this->assertStringContainsString('Raised an exception', $response->getContent());
    }
}
