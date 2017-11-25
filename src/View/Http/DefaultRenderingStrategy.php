<?php
/**
 * @link      http://github.com/zendframework/zend-mvc for the canonical source repository
 * @copyright Copyright (c) 2005-2018 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-mvc/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Zend\Mvc\View\Http;

use Psr\Http\Message\ResponseInterface;
use Zend\EventManager\AbstractListenerAggregate;
use Zend\EventManager\EventManagerInterface;
use Zend\Http\PhpEnvironment\Response as HttpResponse;
use Zend\Mvc\Application;
use Zend\Mvc\MvcEvent;
use Zend\Psr7Bridge\Psr7Response;
use Zend\Psr7Bridge\Psr7ServerRequest;
use Zend\View\Model\ModelInterface as ViewModel;
use Zend\View\View;

class DefaultRenderingStrategy extends AbstractListenerAggregate
{
    /**
     * Layout template - template used in root ViewModel of MVC event.
     *
     * @var string
     */
    protected $layoutTemplate = 'layout';

    /**
     * @var View
     */
    protected $view;

    /**
     * Set view
     *
     * @param  View $view
     */
    public function __construct(View $view)
    {
        $this->view = $view;
    }

    /**
     * {@inheritDoc}
     */
    public function attach(EventManagerInterface $events, $priority = 1) : void
    {
        $this->listeners[] = $events->attach(MvcEvent::EVENT_RENDER, [$this, 'render'], -10000);
        $this->listeners[] = $events->attach(MvcEvent::EVENT_RENDER_ERROR, [$this, 'render'], -10000);
    }

    /**
     * Set layout template value
     *
     * @param  string $layoutTemplate
     * @return void
     */
    public function setLayoutTemplate(string $layoutTemplate) : void
    {
        $this->layoutTemplate = $layoutTemplate;
    }

    /**
     * Get layout template value
     *
     * @return string
     */
    public function getLayoutTemplate() : string
    {
        return $this->layoutTemplate;
    }

    /**
     * Render the view
     *
     * @param  MvcEvent $e
     * @return null|ResponseInterface
     * @throws \Throwable
     */
    public function render(MvcEvent $e) : ?ResponseInterface
    {
        $result = $e->getResult();
        if ($result instanceof ResponseInterface) {
            return $result;
        }

        // Martial arguments
        $request   = $e->getRequest();
        $response  = $e->getResponse();
        $viewModel = $e->getViewModel();
        if (! $viewModel instanceof ViewModel) {
            return null;
        }

        $view = $this->view;
        // @TODO fix after view is updated
        $view->setRequest(Psr7ServerRequest::toZend($request));
        $httpResponse = $response ? Psr7Response::toZend($response) : new HttpResponse();
        $view->setResponse($httpResponse);

        try {
            $view->render($viewModel);
        } catch (\Throwable $ex) {
            if ($e->getName() === MvcEvent::EVENT_RENDER_ERROR) {
                throw $ex;
            }

            $application = $e->getApplication();
            $events      = $application->getEventManager();

            $e->setError(Application::ERROR_EXCEPTION);
            $e->setParam('exception', $ex);
            $e->setName(MvcEvent::EVENT_RENDER_ERROR);
            $events->triggerEvent($e);
        }

        $response = Psr7Response::fromZend($httpResponse);
        $e->setResponse($response);
        return $response;
    }
}
