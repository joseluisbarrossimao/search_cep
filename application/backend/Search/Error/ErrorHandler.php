<?php

declare(strict_types=1);

namespace Search\Error;

use JsonException;
use Search\Container\Instances;
use Search\Controller\BaseController;
use Search\Http\Request;
use Search\Http\Response;
use Throwable;

/**
 *
 */
class ErrorHandler
{
    /**
     * @var string
     */
    private string $error;

    /**
     * @param Request $request
     * @param Response $response
     * @param Instances $instance
     */
    public function __construct(private Request $request, private Response $response, private Instances $instance)
    {
    }

    /**
     * @param Throwable $throwable
     * @return bool
     * @throws Exceptions
     */
    public function logError(Throwable $throwable): bool
    {
        $this->error = $throwable->getMessage();
        $file = $this->instance->resolveClass(
            Search_NAMESPACE[0] . DS_REVERSE . 'Filesystem' . DS_REVERSE . 'File',
            [
                'instance' => $this->instance,
                'file' => ROOT_LOGS . strtolower($this->request->controller) . '.log',
            ],
        );
        $code = '500';
        if ($throwable->getCode() !== 0) {
            $code = substr((string)$throwable->getCode(), 0, 1);
            if ($code === 'H') {
                $code = '500';
            } else {
                $code .= '00';
            }
        }
        $text = '';
        foreach ($file->read()['content'] as $value) {
            if ($value !== '') {
                $text .= $value;
            }
        }
        $text .= $this->renderMsg($code, $this->error, $throwable->getFile(), $throwable->getLine());
        return $file->write($text);
    }

    /**
     * @param string $code
     * @param string $error
     * @param string $file
     * @param int $line
     * @return string
     */
    private function renderMsg(string $code, string $error, string $file, int $line): string
    {
        $level = match ($code) {
            '400' => 'WARNING',
            '500' => 'ERROR',
            default => 'INFO',
        };
        return vsprintf('[%s][%s]: %s in %s on line %s' . PHP_EOL, [date('Y-m-d H:i:s'), $level, $error, $file, $line]);
    }

    /**
     * @param Throwable $throwable
     * @return $this
     * @throws Exceptions
     */
    public function run(Throwable $throwable): ErrorHandler
    {
        error_reporting(0);
        ini_set('display_errors', 0);
        $controllerPath = Search_NAMESPACE[1] . DS_REVERSE . MVC[0] . DS_REVERSE . ucfirst(
            $this->request->controller
        ) . MVC[0];
        if (!$this->instance->validate($controllerPath, 'file')) {
            if (stripos($controllerPath, (string)DS_REVERSE) !== false) {
                $controllerPath = str_replace(DS_REVERSE, DS, $controllerPath);
            }
            echo 'Create Error Controller in the path: ' . str_replace(
                Search_NAMESPACE[1],
                substr(ROOT_NAMESPACE, 0, strripos(ROOT_NAMESPACE, DS)),
                $controllerPath
            );
            return $this;
        }
        $controller = $this->instance->resolveClass(
            $controllerPath,
            ['request' => $this->request, 'response' => $this->response, 'instance' => $this->instance],
        );
        $controller->eventProcessVerification('beforeFilter');
        $controller->layout = mb_strtolower($controller->name);
        $controller->errorHeadRender();
        $controller->{$this->request->action}(
            [
                'msg' => $this->error,
                'traces' => (stripos($throwable::class, 'Exceptions') !== false ? $throwable->getTraces(
                ) : $throwable->getTrace()),
            ],
        );
        $controller->eventProcessVerification('afterFilter');
        $viewBuilder = $this->instance->resolveClass(
            Search_NAMESPACE[0] . DS_REVERSE . 'Builders' . DS_REVERSE . MVC[1] . 'Builder',
            [
                'instance' => $this->instance,
                'request' => $this->request,
                'response' => $this->response,
                'datas' => $controller->view,
            ],
        );
        $viewBuilder->config(
            [
                'activeHelpers' => $controller->activeHelpers,
                'action' => $controller->newAction(),
                'encrypted' => $controller->encrypted,
                'unencryptedRoute' => $controller->unencryptedRoute,
            ],
        )->render($this->pathView($controller));
        $this->response = $viewBuilder->responseView();
        return $this;
    }

    /**
     * @param BaseController $baseController
     * @return array
     * @throws Exceptions
     */
    private function pathView(BaseController $baseController): array
    {
        $layout = substr(
            ROOT_NAMESPACE,
            0,
            strripos(ROOT_NAMESPACE, DS)
        ) . DS . 'Template' . DS . 'Layout' . DS . $baseController->layout . '.phtml';
        $pageContent = substr(ROOT_NAMESPACE, 0, strripos(ROOT_NAMESPACE, DS)) . DS . 'Template' . DS . ucfirst(
            $baseController->name,
        ) . DS . $baseController->action . '.phtml';
        if (!$this->instance->validate($pageContent, 'file')) {
            $pageContent = substr(Search_FRAMEWORK, 0, -1) . DS . ucfirst(
                $baseController->name,
            ) . DS . 'Exceptions' . DS . $baseController->action . '.phtml';
        }
        return [$layout, $pageContent];
    }

    /**
     * @param Throwable $throwable
     * @return $this
     * @throws JsonException
     */
    public function apiError(Throwable $throwable): ErrorHandler
    {
        $this->response->body(
            json_encode(
                [
                    'message' => $throwable->getMessage(),
                    'code' => $throwable->getCode(),
                    'file' => $throwable->getFile(),
                    'line' => $throwable->getLine()
                ],
                JSON_THROW_ON_ERROR | JSON_FORCE_OBJECT | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
            ),
        );
        return $this;
    }

    /**
     * @return string
     */
    public function returnDataByAjax(): string
    {
        return $this->error;
    }
}
