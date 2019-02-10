<?php
/**
 * @see       https://github.com/zendframework/zend-mvc for the canonical source repository
 * @copyright Copyright (c) 2019 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-mvc/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Zend\Mvc\Bootstrapper;

use Zend\Mvc\ApplicationInterface;
use Zend\Mvc\MvcEvent;

class BootstrapEmitter implements BootstrapperInterface
{
    public function bootstrap(ApplicationInterface $application) : void
    {
        $events = $application->getEventManager();

        $mvcEvent = $application->getMvcEvent();
        $mvcEvent->setTarget($application);
        $mvcEvent->setName(MvcEvent::EVENT_BOOTSTRAP);

        // reset propagation flag if set
        $mvcEvent->stopPropagation(true);

        // Trigger bootstrap event
        $events->triggerEvent($mvcEvent);
    }
}
