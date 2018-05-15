<?php
/**
 * @link      http://github.com/zendframework/zend-mvc for the canonical source repository
 * @copyright Copyright (c) 2005-2018 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-mvc/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Zend\Mvc\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Zend\View\Model\ModelInterface;

interface ControllerInterface
{
    /**
     * Dispatch a request
     *
     * @return ResponseInterface|ModelInterface|array|null
     */
    public function dispatch(ServerRequestInterface $request, ResponseInterface $responsePrototype = null);
}
