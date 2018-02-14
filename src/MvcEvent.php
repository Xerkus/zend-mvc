<?php
/**
 * @link      http://github.com/zendframework/zend-mvc for the canonical source repository
 * @copyright Copyright (c) 2005-2018 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-mvc/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Zend\Mvc;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Zend\EventManager\Event;
use Zend\View\Model\ModelInterface as Model;
use Zend\View\Model\ViewModel;

class MvcEvent extends Event
{
    /**#@+
     * Mvc events triggered by eventmanager
     */
    const EVENT_BOOTSTRAP      = 'bootstrap';
    const EVENT_DISPATCH       = 'dispatch';
    const EVENT_DISPATCH_ERROR = 'dispatch.error';
    const EVENT_FINISH         = 'finish';
    const EVENT_RENDER         = 'render';
    const EVENT_RENDER_ERROR   = 'render.error';
    const EVENT_ROUTE          = 'route';
    /**#@-*/

    protected $application;

    /**
     * @var ServerRequestInterface
     */
    protected $request;

    /**
     * @var ResponseInterface
     */
    protected $response;

    /**
     * @var mixed
     */
    protected $result;

    /**
     * @var Model
     */
    protected $viewModel;

    /**
     * Set application instance
     */
    public function setApplication(ApplicationInterface $application) : void
    {
        $this->setParam('application', $application);
        $this->application = $application;
    }

    /**
     * Get application instance
     */
    public function getApplication() : ApplicationInterface
    {
        return $this->application;
    }

    /**
     * Get request
     */
    public function getRequest() : ?ServerRequestInterface
    {
        return $this->request;
    }

    /**
     * Set request
     */
    public function setRequest(?ServerRequestInterface $request) : void
    {
        $this->setParam('request', $request);
        $this->request = $request;
    }

    /**
     * Get response
     */
    public function getResponse() : ?ResponseInterface
    {
        return $this->response;
    }

    /**
     * Set response
     */
    public function setResponse(?ResponseInterface $response) : void
    {
        $this->setParam('response', $response);
        $this->response = $response;
    }

    /**
     * Set the view model
     *
     * @param  Model $viewModel
     * @return MvcEvent
     */
    public function setViewModel(Model $viewModel) : void
    {
        $this->viewModel = $viewModel;
    }

    /**
     * Get the view model
     *
     * @return Model
     */
    public function getViewModel() : Model
    {
        if (null === $this->viewModel) {
            $this->setViewModel(new ViewModel());
        }
        return $this->viewModel;
    }

    /**
     * Get result
     *
     * @return mixed
     */
    public function getResult()
    {
        return $this->result;
    }

    /**
     * Set result
     *
     * @param mixed $result
     */
    public function setResult($result) : void
    {
        $this->setParam('__RESULT__', $result);
        $this->result = $result;
    }

    /**
     * Does the event represent an error response?
     *
     */
    public function isError() : bool
    {
        return (bool) $this->getParam('error', false);
    }

    /**
     * Set the error message (indicating error in handling request)
     */
    public function setError(?string $message) : void
    {
        $this->setParam('error', $message);
    }

    /**
     * Retrieve the error message, if any
     */
    public function getError() : string
    {
        return $this->getParam('error', '');
    }

    /**
     * Get the currently registered controller name
     */
    public function getController() : ?string
    {
        return $this->getParam('controller');
    }

    /**
     * Set controller name
     */
    public function setController(?string $name) : void
    {
        $this->setParam('controller', $name);
    }

    /**
     * Get controller class
     */
    public function getControllerClass() : ?string
    {
        return $this->getParam('controller-class');
    }

    /**
     * Set controller class
     */
    public function setControllerClass(?string $class) : void
    {
        $this->setParam('controller-class', $class);
    }
}
