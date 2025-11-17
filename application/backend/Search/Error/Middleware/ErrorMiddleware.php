<?php

declare(strict_types=1);

namespace Search\Error\Middleware;

use Exception;
use JsonException;
use Search\Container\Instances;
use Search\Error\ErrorHandler;
use Search\Error\Exceptions;
use Search\Http\Middleware\Middleware;
use Search\Http\Request;
use Search\Http\Response;
use Search\Http\Runner;
use Throwable;

/**
 *
 */
class ErrorMiddleware extends Middleware
{
    /**
     * @var ErrorHandler
     */
    private ErrorHandler $errorHandler;

    /**
     * @var Request
     */
    private Request $request;

    /**
     * @var Response
     */
    private Response $response;

    /**
     * @param Request $request
     * @param Response $response
     * @param Instances $instance
     * @throws Exceptions
     */
    public function __construct(Request $request, Response $response, Instances $instance)
    {
        $this->errorHandler = $instance->resolveClass(
            Search_NAMESPACE[0] . DS_REVERSE . 'Error' . DS_REVERSE . 'ErrorHandler',
            ['request' => $request, 'response' => $response, 'instance' => $instance],
        );
        parent::__construct($instance);
        $this->request = $request;
        $this->response = $response;
    }

    /**
     * @param Runner $next
     * @return $this
     * @throws JsonException
     * @throws Exceptions
     * @throws Exception
     */
    public function invoke(Runner $next): ErrorMiddleware
    {
        try {
            $next->invoke();
            return $this;
        } catch (Throwable $throwable) {
            if ($this->request->booleanApi()) {
                $this->errorHandler->apiError($throwable);
                return $this;
            }
            if ($this->request->server->returnValue('HTTP_HOST') === 'localhost') {
                $this->insertDatasInClassRequest();
                if (!$this->errorHandler->logError($throwable)) {
                    throw new Exception('this problem with log error.', $throwable->getCode(), $throwable);
                }
                $this->errorHandler->run($throwable);
                if ($this->request->ajax) {
                    $this->response->body(
                        json_encode(
                            ['type' => 'exception', 'result' => $this->errorHandler->returnDataByAjax()],
                            JSON_FORCE_OBJECT | JSON_UNESCAPED_UNICODE
                        ),
                    );
                }
                return $this;
            }
            $this->response->body($throwable->getMessage());
            return $this;
        }
    }

    /**
     * @return void
     */
    private function insertDatasInClassRequest(): void
    {
        $this->request->controller = 'Error';
        $this->request->action = 'handling';
        if (!isset($this->request->encryptionKeys)) {
            $this->request->encryptionKeys = [
                'internal' => true,
                'general' => false,
                'linkInternalWithExternalAccess' => false
            ];
        }
    }
}
