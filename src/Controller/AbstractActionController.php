<?php
/**
 * @link      http://github.com/zendframework/zend-mvc for the canonical source repository
 * @copyright Copyright (c) 2005-2018 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-mvc/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Zend\Mvc\Controller;

use Fig\Http\Message\StatusCodeInterface as Status;
use Zend\Mvc\Exception;
use Zend\Mvc\MvcEvent;
use Zend\Router\RouteResult;
use Zend\View\Model\ViewModel;

use function method_exists;

/**
 * Basic action controller
 */
abstract class AbstractActionController extends AbstractController
{
    /**
     * {@inheritDoc}
     */
    protected $eventIdentifier = __CLASS__;

    /**
     * Default action if none provided
     *
     * @return ViewModel
     */
    public function indexAction()
    {
        return new ViewModel([
            'content' => 'Placeholder page'
        ]);
    }

    /**
     * Action called if matched action does not exist
     *
     * @throws Exception\InvalidControllerActionException
     */
    public function notFoundAction()
    {
        $this->setResponse($this->getResponse()->withStatus(Status::STATUS_NOT_FOUND));
        return new ViewModel(['content' => 'Page not found']);
    }

    /**
     * Execute the request
     *
     * @return mixed
     * @throws Exception\DomainException When RouteResult is not set
     */
    public function onDispatch(MvcEvent $e)
    {
        /** @var RouteResult $routeResult */
        $routeResult = $e->getRequest()->getAttribute(RouteResult::class);
        if (! $routeResult) {
            /**
             * Potentially allow pulling directly from request metadata?
             */
            throw new Exception\DomainException('Missing route result; unsure how to retrieve action');
        }

        $action = $routeResult->getMatchedParams()['action'] ?? 'not-found';
        $method = static::getMethodFromAction($action);

        if (! method_exists($this, $method)) {
            $method = 'notFoundAction';
        }

        $actionResponse = $this->$method();

        $e->setResult($actionResponse);

        return $actionResponse;
    }
}
