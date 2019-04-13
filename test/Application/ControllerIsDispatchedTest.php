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
class ControllerIsDispatchedTest extends TestCase
{
    use PathControllerTrait;

    public function testControllerIsDispatchedDuringRun()
    {
        $application = $this->prepareApplication();

        $response = $application->run()->getMvcEvent()->getResponse();
        $this->assertStringContainsString('PathController', $response->getContent());
        $this->assertStringContainsString(MvcEvent::EVENT_DISPATCH, $response->toString());
    }
}
