<?php
/**
 * @link      http://github.com/zendframework/zend-mvc for the canonical source repository
 * @copyright Copyright (c) 2005-2018 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-mvc/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Zend\Mvc\Controller;

use Fig\Http\Message\StatusCodeInterface as Status;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Zend\EventManager\EventManagerInterface;
use Zend\Json\Json;
use Zend\Mvc\Exception;
use Zend\Mvc\MvcEvent;
use Zend\Router\RouteResult;

use function count;
use function gettype;
use function get_class;
use function is_array;
use function is_callable;
use function is_object;
use function parse_str;
use function reset;
use function strtolower;

/**
 * Abstract RESTful controller
 *
 * @todo needs proper refactoring. Tests are passing but I am not sure if it is correct.
 */
abstract class AbstractRestfulController extends AbstractController
{
    public const CONTENT_TYPE_JSON = 'json';

    /**
     * {@inheritDoc}
     */
    protected $eventIdentifier = __CLASS__;

    /**
     * @var array
     */
    protected $contentTypes = [
        self::CONTENT_TYPE_JSON => [
            'application/hal+json',
            'application/json'
        ]
    ];

    /**
     * Name of request or query parameter containing identifier
     *
     * @var string
     */
    protected $identifierName = 'id';

    /**
     * Flag to pass to json_decode and/or Zend\Json\Json::decode.
     *
     * The flags in Zend\Json\Json::decode are integers, but when evaluated
     * in a boolean context map to the flag passed as the second parameter
     * to json_decode(). As such, you can specify either the Zend\Json\Json
     * constant or the boolean value. By default, starting in v3, we use
     * the boolean value, and cast to integer if using Zend\Json\Json::decode.
     *
     * Default value is boolean true, meaning JSON should be cast to
     * associative arrays (vs objects).
     *
     * Override the value in an extending class to set the default behavior
     * for your class.
     *
     * @var int|bool
     */
    protected $jsonDecodeType = true;

    /**
     * Map of custom HTTP methods and their handlers
     *
     * @var array
     */
    protected $customHttpMethodsMap = [];

    /**
     * @var callable
     */
    protected $streamFactory;

    public function __construct(EventManagerInterface $events, callable $responseFactory, callable $streamFactory)
    {
        parent::__construct($events, $responseFactory);
        // Ensures type safety of the composed factory
        $this->streamFactory = function ($stream = 'php://memory', string $mode = 'wb+') use ($streamFactory) : StreamInterface {
            return $streamFactory($stream, $mode);
        };
    }

    /**
     * Set the route match/query parameter name containing the identifier
     */
    public function setIdentifierName(string $name) : void
    {
        $this->identifierName = $name;
    }

    /**
     * Retrieve the route match/query parameter name containing the identifier
     */
    public function getIdentifierName() : string
    {
        return $this->identifierName;
    }

    /**
     * Create a new resource
     *
     * @param  mixed $data
     * @return mixed
     */
    public function create($data)
    {
        $this->setResponse($this->getResponse()->withStatus(Status::STATUS_METHOD_NOT_ALLOWED));

        return [
            'content' => 'Method Not Allowed'
        ];
    }

    /**
     * Delete an existing resource
     *
     * @param  mixed $id
     * @return mixed
     */
    public function delete($id)
    {
        $this->setResponse($this->getResponse()->withStatus(Status::STATUS_METHOD_NOT_ALLOWED));

        return [
            'content' => 'Method Not Allowed'
        ];
    }

    /**
     * Delete the entire resource collection
     *
     * @return mixed
     */
    public function deleteList($data)
    {
        $this->setResponse($this->getResponse()->withStatus(Status::STATUS_METHOD_NOT_ALLOWED));

        return [
            'content' => 'Method Not Allowed'
        ];
    }

    /**
     * Return single resource
     *
     * @param  mixed $id
     * @return mixed
     */
    public function get($id)
    {
        $this->setResponse($this->getResponse()->withStatus(Status::STATUS_METHOD_NOT_ALLOWED));

        return [
            'content' => 'Method Not Allowed'
        ];
    }

    /**
     * Return list of resources
     *
     * @return mixed
     */
    public function getList()
    {
        $this->setResponse($this->getResponse()->withStatus(Status::STATUS_METHOD_NOT_ALLOWED));

        return [
            'content' => 'Method Not Allowed'
        ];
    }

    /**
     * Retrieve HEAD metadata for the resource
     *
     * @param  null|mixed $id
     * @return mixed
     */
    public function head($id = null)
    {
        $this->setResponse($this->getResponse()->withStatus(Status::STATUS_METHOD_NOT_ALLOWED));

        return [
            'content' => 'Method Not Allowed'
        ];
    }

    /**
     * Respond to the OPTIONS method
     *
     * Typically, set the Allow header with allowed HTTP methods, and
     * return the response.
     *
     * @return mixed
     */
    public function options()
    {
        $this->setResponse($this->getResponse()->withStatus(Status::STATUS_METHOD_NOT_ALLOWED));

        return [
            'content' => 'Method Not Allowed'
        ];
    }

    /**
     * Respond to the PATCH method
     *
     * @param  $id
     * @param  $data
     * @return array
     */
    public function patch($id, $data)
    {
        $this->setResponse($this->getResponse()->withStatus(Status::STATUS_METHOD_NOT_ALLOWED));

        return [
            'content' => 'Method Not Allowed'
        ];
    }

    /**
     * Replace an entire resource collection
     *
     * @param  mixed $data
     * @return mixed
     */
    public function replaceList($data)
    {
        $this->setResponse($this->getResponse()->withStatus(Status::STATUS_METHOD_NOT_ALLOWED));

        return [
            'content' => 'Method Not Allowed'
        ];
    }

    /**
     * Modify a resource collection without completely replacing it
     *
     * @param  mixed $data
     * @return mixed
     */
    public function patchList($data)
    {
        $this->setResponse($this->getResponse()->withStatus(Status::STATUS_METHOD_NOT_ALLOWED));

        return [
            'content' => 'Method Not Allowed'
        ];
    }

    /**
     * Update an existing resource
     *
     * @param  mixed $id
     * @param  mixed $data
     * @return mixed
     */
    public function update($id, $data)
    {
        $this->setResponse($this->getResponse()->withStatus(Status::STATUS_METHOD_NOT_ALLOWED));

        return [
            'content' => 'Method Not Allowed'
        ];
    }

    /**
     * Basic functionality for when a page is not available
     *
     * @return array
     */
    public function notFoundAction()
    {
        $this->setResponse($this->getResponse()->withStatus(Status::STATUS_NOT_FOUND));

        return [
            'content' => 'Page not found'
        ];
    }

    /**
     * Handle the request
     *
     * @param  MvcEvent $e
     * @return mixed
     * @throws Exception\DomainException if no route matches in event or invalid HTTP method
     */
    public function onDispatch(MvcEvent $e)
    {
        $request = $this->getRequest();
        $routeResult = $request->getAttribute(RouteResult::class);
        if (! $routeResult) {
            throw new Exception\DomainException('Missing route result; unsure how to retrieve action');
        }

        $request = $e->getRequest();

        // Was an "action" requested?
        $action  = $routeResult->getMatchedParams()['action'] ?? null;
        if (! empty($action)) {
            // Handle arbitrary methods, ending in Action
            $method = static::getMethodFromAction((string)$action);
            if (! method_exists($this, $method)) {
                $method = 'notFoundAction';
            }
            $return = $this->$method();
            $e->setResult($return);
            return $return;
        }

        // RESTful methods
        $method = strtolower($request->getMethod());
        switch ($method) {
            // Custom HTTP methods (or custom overrides for standard methods)
            case (isset($this->customHttpMethodsMap[$method])):
                $callable = $this->customHttpMethodsMap[$method];
                $action = $method;
                $return = $callable($e);
                break;
            // DELETE
            case 'delete':
                $id = $this->getIdentifier($request);

                if ($id !== false) {
                    $action = 'delete';
                    $return = $this->delete($id);
                    break;
                }

                $data = $this->processBodyContent($request);

                $action = 'deleteList';
                $return = $this->deleteList($data);
                break;
            // GET
            case 'get':
                $id = $this->getIdentifier($request);
                if ($id !== false) {
                    $action = 'get';
                    $return = $this->get($id);
                    break;
                }
                $action = 'getList';
                $return = $this->getList();
                break;
            // HEAD
            case 'head':
                $id = $this->getIdentifier($request);
                if ($id === false) {
                    $id = null;
                }
                $action = 'head';
                $headResult = $this->head($id);
                $response = ($headResult instanceof ResponseInterface) ? $headResult : $this->getResponse();
                $response = $response->withBody(($this->streamFactory)());
                $return = $response;
                break;
            // OPTIONS
            case 'options':
                $action = 'options';
                $return = $this->options();
                $return = $return instanceof ResponseInterface ? $return : $this->getResponse();
                break;
            // PATCH
            case 'patch':
                $id = $this->getIdentifier($request);
                $data = $this->processBodyContent($request);

                if ($id !== false) {
                    $action = 'patch';
                    $return = $this->patch($id, $data);
                    break;
                }

                $action = 'patchList';
                $return = $this->patchList($data);
                break;
            // POST
            case 'post':
                $action = 'create';
                $return = $this->processPostData($request);
                break;
            // PUT
            case 'put':
                $id   = $this->getIdentifier($request);
                $data = $this->processBodyContent($request);

                if ($id !== false) {
                    $action = 'update';
                    $return = $this->update($id, $data);
                    break;
                }

                $action = 'replaceList';
                $return = $this->replaceList($data);
                break;
            // All others...
            default:
                $response = $this->getResponse();
                $response = $response->withStatus(Status::STATUS_METHOD_NOT_ALLOWED);
                $this->setResponse($response);
                return $response;
        }

        $params = $routeResult->getMatchedParams();
        $params['action'] = $action;
        $routeResult = $routeResult->withMatchedParams($params);
        $request = $e->getRequest()->withAttribute(RouteResult::class, $routeResult);
        $e->setRequest($request);
        $e->setResult($return);
        if ($return instanceof ResponseInterface) {
            $this->setResponse($return);
        }
        return $return;
    }

    /**
     * Process post data and call create
     *
     * @return mixed
     * @throws Exception\DomainException If a JSON request was made, but no
     *    method for parsing JSON is available.
     */
    public function processPostData(ServerRequestInterface $request)
    {
        if ($this->requestHasContentType($request, self::CONTENT_TYPE_JSON)) {
            return $this->create($this->jsonDecode($request->getBody()->__toString()));
        }

        $payload = $request->getParsedBody() ?: [];
        return $this->create($payload);
    }

    /**
     * Check if request has certain content type
     *
     * @param  string|null $contentType
     * @return bool
     */
    public function requestHasContentType(ServerRequestInterface $request, $contentType = '') : bool
    {
        $headersContentType = $request->getHeader('content-type');
        if (empty($headersContentType)) {
            return false;
        }

        $requestedContentType = array_pop($headersContentType);
        if (false !== strpos($requestedContentType, ';')) {
            $headerData = explode(';', $requestedContentType);
            $requestedContentType = array_shift($headerData);
        }
        $requestedContentType = trim($requestedContentType);
        if (array_key_exists($contentType, $this->contentTypes)) {
            foreach ($this->contentTypes[$contentType] as $contentTypeValue) {
                if (stripos($contentTypeValue, $requestedContentType) === 0) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Register a handler for a custom HTTP method
     *
     * This method allows you to handle arbitrary HTTP method types, mapping
     * them to callables. Typically, these will be methods of the controller
     * instance: e.g., array($this, 'foobar'). The typical place to register
     * these is in your constructor.
     *
     * Additionally, as this map is checked prior to testing the standard HTTP
     * methods, this is a way to override what methods will handle the standard
     * HTTP methods. However, if you do this, you will have to retrieve the
     * identifier and any request content manually.
     *
     * Callbacks will be passed the current MvcEvent instance.
     *
     * To retrieve the identifier, you can use "$id =
     * $this->getIdentifier($routeMatch, $request)",
     * passing the appropriate objects.
     *
     * To retrieve the body content data, use "$data = $this->processBodyContent($request)";
     * that method will return a string, array, or, in the case of JSON, an object.
     */
    public function addHttpMethodHandler(string $method, callable $handler) : void
    {
        if (! is_callable($handler)) {
            throw new Exception\InvalidArgumentException(sprintf(
                'Invalid HTTP method handler: must be a callable; received "%s"',
                (is_object($handler) ? get_class($handler) : gettype($handler))
            ));
        }
        $method = strtolower($method);
        $this->customHttpMethodsMap[$method] = $handler;
    }

    /**
     * Retrieve the identifier, if any
     *
     * Attempts to see if an identifier was passed in either the URI or the
     * query string, returning it if found. Otherwise, returns a boolean false.
     *
     * @return false|mixed
     */
    protected function getIdentifier(ServerRequestInterface $request)
    {
        $identifier = $this->getIdentifierName();
        $id = $request->getAttribute(RouteResult::class)->getMatchedParams()[$identifier] ?? false;
        if ($id !== false) {
            return $id;
        }

        return $request->getQueryParams()[$identifier] ?? false;
    }

    /**
     * Process the raw body content
     *
     * If the content-type indicates a JSON payload, the payload is immediately
     * decoded and the data returned. Otherwise, the data is passed to
     * parse_str(). If that function returns a single-member array with a empty
     * value, the method assumes that we have non-urlencoded content and
     * returns the raw content; otherwise, the array created is returned.
     *
     * @param  mixed $request
     * @return object|string|array
     * @throws Exception\DomainException If a JSON request was made, but no
     *    method for parsing JSON is available.
     */
    protected function processBodyContent(ServerRequestInterface $request)
    {
        $content = $request->getBody()->__toString();

        // JSON content? decode and return it.
        if ($this->requestHasContentType($request, self::CONTENT_TYPE_JSON)) {
            return $this->jsonDecode($content);
        }

        parse_str($content, $parsedParams);

        // If parse_str fails to decode, or we have a single element with empty value
        if (! is_array($parsedParams) || empty($parsedParams)
            || (1 == count($parsedParams) && '' === reset($parsedParams))
        ) {
            return $content;
        }

        return $parsedParams;
    }

    /**
     * Decode a JSON string.
     *
     * Uses json_decode by default. If that is not available, checks for
     * availability of Zend\Json\Json, and uses that if present.
     *
     * Otherwise, raises an exception.
     *
     * Marked protected to allow usage from extending classes.
     *
     * @return mixed
     * @throws Exception\DomainException if no JSON decoding functionality is
     *     available.
     */
    protected function jsonDecode(string $string)
    {
        if (function_exists('json_decode')) {
            return json_decode($string, (bool) $this->jsonDecodeType);
        }

        if (class_exists(Json::class)) {
            return Json::decode($string, (int) $this->jsonDecodeType);
        }

        throw new Exception\DomainException(sprintf(
            'Unable to parse JSON request, due to missing ext/json and/or %s',
            Json::class
        ));
    }
}
