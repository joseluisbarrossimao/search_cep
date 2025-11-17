<?php

declare(strict_types=1);

namespace Search\Http;

use Search\Collection\Groupings;
use Search\Container\Instances;
use Search\Error\Exceptions;

/**
 *
 */
class Server
{
    /**
     * @var Runner
     */
    private Runner $runner;

    /**
     * @var Request
     */
    private Request $request;

    /**
     * @var Response
     */
    private Response $response;

    /**
     * @var Instances
     */
    private Instances $instance;

    /**
     * @param Instances $instance
     * @param Groupings $server
     * @param array $configGlobal
     * @throws Exceptions
     */
    public function __construct(Instances $instance, Groupings $server, array $configGlobal)
    {
        $hostname = $server->returnValue('HTTP_HOST');
        if (stripos($hostname, ':') !== false) {
            [$hostname, $port] = explode(':', $hostname);
        }
        if (!isset($port)) {
            $port = '80';
        }
        $server->change(['HTTP_HOST' => $hostname, 'HTTP_PORT' => $port], true);
        if (!defined('Search_FRAMEWORK')) {
            require_once __DIR__ . '/../../config/pathServer.php';
            /**
             * Define the URL of the application
             */
            if ($server->exist('REQUEST_SCHEME')) {
                $scheme = $server->returnValue('REQUEST_SCHEME');
                define(
                    'URL',
                    $port === "80" ? $scheme . ":" . DS . DS . $hostname : $scheme . ":" . DS . DS . $hostname . ':' . $port
                );
                unset($scheme);
            }
        }
        unset($hostname, $port);
        $this->request = $instance->resolveClass(
            Search_NAMESPACE[0] . DS_REVERSE . 'Http' . DS_REVERSE . 'Request',
            ['server' => $server],
        );
        $this->request->methods($configGlobal);
        $this->response = $instance->resolveClass(
            Search_NAMESPACE[0] . DS_REVERSE . 'Http' . DS_REVERSE . 'Response',
            ['server' => $server],
        );
        $this->runner = $instance->resolveClass(
            Search_NAMESPACE[0] . DS_REVERSE . 'Http' . DS_REVERSE . 'Runner',
            ['request' => $this->request, 'response' => $this->response],
        );
        $this->instance = $instance;
    }

    /**
     * @return $this
     * @throws Exceptions
     */
    public function execute(): Server
    {
        $this->runner->run($this->instance);
        return $this;
    }

    /**
     * @return mixed
     */
    public function send(): mixed
    {
        return $this->response->send();
    }
}
