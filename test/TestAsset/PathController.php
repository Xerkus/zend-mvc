<?php
/**
 * @link      http://github.com/zendframework/zend-mvc for the canonical source repository
 * @copyright Copyright (c) 2005-2018 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-mvc/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace ZendTest\Mvc\TestAsset;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Zend\Diactoros\Response;
use Zend\Mvc\Controller\ControllerInterface;

class PathController implements ControllerInterface
{
    public function dispatch(ServerRequestInterface $request, ResponseInterface $responsePrototype = null)
    {
        if (! $responsePrototype) {
            $response = new Response();
        }
        $response->getBody()->write(__METHOD__);
        return $response;
    }
}
