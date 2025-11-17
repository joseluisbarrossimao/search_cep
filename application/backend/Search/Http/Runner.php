<?php

declare(strict_types=1);

namespace Search\Http;

use Exception;
use Search\Container\Instances;
use Search\Error\Exceptions;
use Search\Http\Middleware\Middleware;

/**
 *
 */
class Runner
{
    /**
     * @var array
     */
    private array $queue = [];

    /**
     * @var int
     */
    private int $index = 0;

    /**
     * @var Middleware|null
     */
    private ?Middleware $middleware = null;

    /**
     * @var Instances|null
     */
    private ?Instances $instance = null;

    /**
     * @param Request $request
     * @param Response $response
     */
    public function __construct(private Request $request, private Response $response)
    {
    }

    /**
     * @param string $class
     * @return $this
     */
    public function addQueue(string $class): Runner
    {
        $this->queue[] = $class;
        return $this;
    }

    /**
     * @param Instances $instance
     * @return $this
     * @throws Exceptions
     */
    public function run(Instances $instance): Runner
    {
        $this->middleware = $instance->resolveClass(
            Search_NAMESPACE[0] . DS_REVERSE . 'Http' . DS_REVERSE . 'Middleware' . DS_REVERSE . 'Middleware',
            ['instance' => $instance],
        );
        $this->request->applicationBootstrap(
            $instance,
            $instance->resolveClass(
                Search_NAMESPACE[0] . DS_REVERSE . 'Core' . DS_REVERSE . 'Middleware' . DS_REVERSE . 'ApplicationMiddleware',
                array_merge(
                    ['request' => $this->request, 'response' => $this->response],
                    $this->middleware->returnDatas()
                )
            )->bootstrap(),
        );
        $this->instance = $instance;
        $this->request->pathInfo();
        if ($this->files($this->request->route)) {
            $this->request->checkExistApiInRoute();
            $this->middleware->queue($this, $this->request->bootstrap('middleware'));
            $this->index = 0;
            return $this->invoke();
        }
        return $this;
    }

    /**
     * @param string $file
     * @return bool
     * @throws Exceptions
     */
    public function files(string $file): bool
    {
        if ($file !== DS) {
            $validated = true;
            foreach ($this->instance->read(ROOT_PUBLIC, 'folder')['directories'] as $folder) {
                if (stripos($file, DS . $folder . DS) !== false) {
                    $validated = !$validated;
                    break;
                }
            }
            if (!$validated) {
                $file = $this->instance->resolveClass(
                    Search_NAMESPACE[0] . DS_REVERSE . 'Filesystem' . DS_REVERSE . 'File',
                    ['instance' => $this->instance, 'file' => ROOT_PUBLIC . $file]
                );
                $mimetype = $file->mimetype();
                if ($mimetype === '') {
                    throw new Exception(sprintf("This %s file isn'o't accepted.", $file->namePath()));
                }
                header('Content-Type: ' . ($mimetype === 'text/plain' ? 'text/css' : $mimetype));
                if (in_array($mimetype, ['text/javascript', 'text/plain'])) {
                    echo file_get_contents($file->pathInfo());
                } else {
                    readfile($file->pathInfo());
                }
                return false;
            }
            return true;
        }
        return true;
    }

    /**
     * @return $this
     * @throws Exceptions
     */
    public function invoke(): Runner
    {
        $next = $this->queue[$this->index];
        if (!is_object($this->queue[$this->index])) {
            if (
                !$this->instance->validate(
                    str_replace(
                        DS_REVERSE,
                        DS,
                        str_replace(Search_NAMESPACE[0], substr(Search_FRAMEWORK, 0, -1), $next . '.php')
                    ),
                    'file'
                )
            ) {
                throw new Exceptions(sprintf('Middleware %s was not found.', $next));
            }
            $next = $this->instance->resolveClass(
                $next,
                array_merge(
                    ['request' => $this->request, 'response' => $this->response],
                    $this->middleware->returnDatas()
                )
            );
        }
        $this->index++;
        $next->invoke($this);
        return $this;
    }
}
