<?php
/**
 * @link      http://github.com/zendframework/zend-mvc for the canonical source repository
 * @copyright Copyright (c) 2005-2018 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-mvc/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Zend\Mvc\Container;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;
use Zend\Router\RouteMatch;
use Zend\Stdlib\ArrayUtils;
use Zend\View\Helper as ViewHelper;
use Zend\View\HelperPluginManager;

use function is_callable;

class ViewHelperManagerFactory implements FactoryInterface
{
    /**
     * Create the view helper manager
     *
     * @param string $name
     */
    public function __invoke(ContainerInterface $container, $name, array $options = null) : HelperPluginManager
    {
        if (null !== $options) {
            return new HelperPluginManager($container, $options);
        }
        // merge config into overrides as we override defaults defined
        // in HelperPluginManager, not the config entries
        // @TODO extract into proper factories, may be into a mvc satellite package
        $config = ArrayUtils::merge(
            $this->prepareOverrideFactories($container),
            static::getViewHelpersConfig($container)
        );
        return new HelperPluginManager($container, $config);
    }

    public static function getViewHelpersConfig(ContainerInterface $container) : array
    {
        $config = $container->has('config') ? $container->get('config') : [];
        return $config['view_helpers'] ?? [];
    }

    /**
     * Prepare override factories for HelperPluginManager
     */
    private function prepareOverrideFactories(ContainerInterface $container) : array
    {
        $config = [];

        // Configure URL view helper
        $urlFactory = $this->createUrlHelperFactory($container);
        $config['factories'][ViewHelper\Url::class] = $urlFactory;
        $config['factories']['zendviewhelperurl'] = $urlFactory;

        // Configure base path helper
        $basePathFactory = $this->createBasePathHelperFactory($container);
        $config['factories'][ViewHelper\BasePath::class] = $basePathFactory;
        $config['factories']['zendviewhelperbasepath'] = $basePathFactory;

        // Configure doctype view helper
        $doctypeFactory = $this->createDoctypeHelperFactory($container);
        $config['factories'][ViewHelper\Doctype::class] = $doctypeFactory;
        $config['factories']['zendviewhelperdoctype'] = $doctypeFactory;

        return $config;
    }

    /**
     * Create and return a factory for creating a URL helper.
     *
     * Retrieves the application and router from the servicemanager,
     * and the route match from the MvcEvent composed by the application,
     * using them to configure the helper.
     */
    private function createUrlHelperFactory(ContainerInterface $container) : callable
    {
        return function () use ($container) : ViewHelper\Url {
            $helper = new ViewHelper\Url();
            $helper->setRouter($container->get('HttpRouter'));

            $match = $container->get('Application')
                ->getMvcEvent()
                ->getRouteMatch()
            ;

            if ($match instanceof RouteMatch) {
                $helper->setRouteMatch($match);
            }

            return $helper;
        };
    }

    /**
     * Create and return a factory for creating a BasePath helper.
     *
     * Uses configuration and request services to configure the helper.
     */
    private function createBasePathHelperFactory(ContainerInterface $container) : callable
    {
        return function () use ($container) : ViewHelper\BasePath {
            $config = $container->has('config') ? $container->get('config') : [];
            $helper = new ViewHelper\BasePath();

            if (isset($config['view_manager']) && isset($config['view_manager']['base_path'])) {
                $helper->setBasePath($config['view_manager']['base_path']);
                return $helper;
            }

            $request = $container->get('Request');

            if (is_callable([$request, 'getBasePath'])) {
                $helper->setBasePath($request->getBasePath());
            }

            return $helper;
        };
    }

    /**
     * Create and return a Doctype helper factory.
     *
     * Other view helpers depend on this to decide which spec to generate their tags
     * based on. This is why it must be set early instead of later in the layout phtml.
     */
    private function createDoctypeHelperFactory(ContainerInterface $container) : callable
    {
        return function () use ($container) : ViewHelper\Doctype {
            $config = $container->has('config') ? $container->get('config') : [];
            $config = isset($config['view_manager']) ? $config['view_manager'] : [];
            $helper = new ViewHelper\Doctype();
            if (isset($config['doctype']) && $config['doctype']) {
                $helper->setDoctype($config['doctype']);
            }
            return $helper;
        };
    }
}
