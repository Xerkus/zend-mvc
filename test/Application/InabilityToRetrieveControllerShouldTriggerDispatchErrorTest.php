<?php
/**
 * @link      http://github.com/zendframework/zend-mvc for the canonical source repository
 * @copyright Copyright (c) 2005-2018 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-mvc/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace ZendTest\Mvc\Application;

use PHPUnit\Framework\TestCase;
use Zend\Mvc\Application;
use Zend\Mvc\MvcEvent;

class InabilityToRetrieveControllerShouldTriggerDispatchErrorTest extends TestCase
{
    use MissingControllerTrait;

    /**
     * @group error-handling
     */
    public function testInabilityToRetrieveControllerShouldTriggerDispatchError()
    {
        $application = $this->prepareApplication();

        $response = $application->getResponse();
        $events   = $application->getEventManager();
        $events->attach(MvcEvent::EVENT_DISPATCH_ERROR, function ($e) use ($response) {
            $error      = $e->getError();
            $controller = $e->getController();
            $response->setContent("Code: " . $error . '; Controller: ' . $controller);
            return $response;
        });

        $application->run();
        $this->assertContains(Application::ERROR_CONTROLLER_NOT_FOUND, $response->getContent());
        $this->assertContains('bad', $response->getContent());
    }
}
