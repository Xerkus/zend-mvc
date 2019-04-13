<?php
/**
 * @see       https://github.com/zendframework/zend-mvc for the canonical source repository
 * @copyright Copyright (c) 2005-2019 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-mvc/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace ZendTest\Mvc\Application;

use PHPUnit\Framework\TestCase;
use Zend\Mvc\Application;
use Zend\Mvc\MvcEvent;

/**
 * @coversNothing
 */
class InvalidControllerTypeShouldTriggerDispatchErrorTest extends TestCase
{
    use InvalidControllerTypeTrait;

    /**
     * @group error-handling
     */
    public function testInvalidControllerTypeShouldTriggerDispatchError()
    {
        $application = $this->prepareApplication();

        $response = $application->getMvcEvent()->getResponse();
        $events   = $application->getEventManager();
        $events->attach(MvcEvent::EVENT_DISPATCH_ERROR, function ($e) use ($response) {
            $error      = $e->getError();
            $controller = $e->getController();
            $class      = $e->getControllerClass();
            $response->setContent('Code: ' . $error . '; Controller: ' . $controller . '; Class: ' . $class);
            return $response;
        });

        $application->run();
        $this->assertStringContainsString(Application::ERROR_CONTROLLER_INVALID, $response->getContent());
        $this->assertStringContainsString('bad', $response->getContent());
    }
}
