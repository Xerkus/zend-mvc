<?php
/**
 * @see       https://github.com/zendframework/zend-mvc for the canonical source repository
 * @copyright Copyright (c) 2005-2019 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-mvc/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Zend\Mvc\Controller\Plugin\Service;

use Psr\Container\ContainerInterface;
use Zend\Mvc\Controller\Plugin\Forward;
use Zend\ServiceManager\Exception\ServiceNotCreatedException;

use function sprintf;

class ForwardFactory
{
    /**
     * @throws ServiceNotCreatedException if Controllermanager service is not found in application service locator
     */
    public function __invoke(ContainerInterface $container) : Forward
    {
        if (! $container->has('ControllerManager')) {
            throw new ServiceNotCreatedException(sprintf(
                '%s requires that the application service manager contains a "%s" service; none found',
                self::class,
                'ControllerManager'
            ));
        }
        $controllers = $container->get('ControllerManager');

        return new Forward($controllers);
    }
}
