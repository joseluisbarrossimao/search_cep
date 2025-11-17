<?php

declare(strict_types=1);

namespace Search\Http;

use Search\Route\Route;
use Search\Error\Exceptions;
use Search\Authentication\Auth;
use Search\Container\Instances;
use Search\Collection\Groupings;

/**
 *
 */
class Request
{
    
    /**
     * @var array
     */
    public array $callbacks = [];

    /**
     * @var string
     */
    public string $redirectedApi = '';

    /**
     * @var array
     */
    public array $post;

    /**
     * @var array
     */
    public array $activations;

    /**
     * @var string
     */
    public string $route;

    /**
     * @var string
     */
    public string $base = '';

    /**
     * @var string
     */
    public string $controller;

    /**
     * @var array
     */
    public array $params = [];

    /**
     * @var string
     */
    public string $action;

    /**
     * @var bool
     */
    public bool $ajax = false;

    /**
     * @var bool
     */
    public bool $existSecondFactorRoute = false;

    /**
     * @var bool
     */
    public bool $shorten = false;

    /**
     * @var Groupings
     */
    public Groupings $server;

    /**
     * @var Auth
     */
    public Auth $auth;

    /**
     * @var string
     */
    public string $prefix = '';

    /**
     * @var array
     */
    private array $routes = [];

    /**
     * @var bool
     */
    public bool $renderCsrf = false;

    /**
     * @var array
     */
    public array $encryptionKeys;

    /**
     * @var string
     */
    public string $userAgent = 'desktop';

    /**
     * @var bool
     */
    public bool $blockedRoute = false;

    /**
     * @var Groupings|null
     */
    private ?Groupings $bootstrap = null;

    /**
     * @var bool
     */
    private bool $changeRoute = false;

    /**
     * @var bool
     */
    private bool $api = false;

    /**
     * @var array|string[]
     */
    private array $home = ['htdocs', 'public_html', 'www'];

    /**
     * @var mixed|null
     */
    private mixed $gets = null;

    /**
     * @var mixed|null
     */
    private mixed $posts = null;

    /**
     * @var mixed|null
     */
    private mixed $puts = null;

    /**
     * @var mixed|null
     */
    private mixed $patches = null;

    /**
     * @var mixed|null
     */
    private mixed $deletes = null;

    /**
     * @var array|null
     */
    private ?array $files = null;

    /**
     * @var mixed|null
     */
    private mixed $attachments = null;

    /**
     * @var string
     */
    private string $newRoute = '';

    /**
     * @param Groupings $server
     * @throws Exceptions
     */
    public function __construct(Groupings $server)
    {
        $this->server = $server;
        if ($this->server->returnValue('HTTP_HOST') === 'localhost') {
            $this->server->change(['REMOTE_ADDR' => '187.85.61.4'], true);
        }
        $this->userAgent();
    }

    /**
     * @return $this
     * @throws Exceptions
     */
    public function userAgent(): Request
    {
        if (stripos($this->server->returnValue('HTTP_USER_AGENT'), 'Mobile') === false) {
            $this->userAgent = 'mobile';
        }
        return $this;
    }

    /**
     * @return $this
     * @throws Exceptions
     */
    public function pathInfo(): Request
    {
        $url = $this->server->returnValue('REQUEST_URI');
        $this->base($url);
        if (stripos($url, '?') === false) {
            $url = parse_url(urldecode($url), PHP_URL_PATH);
        } else {
            $url = parse_url(urldecode(substr($url, 0, stripos($url, '?'))), PHP_URL_PATH);
        }
        if ($this->base === '') {
            $url = substr($url, strlen($this->base));
        }
        $this->ajax = $this->server->exist('HTTP_X_REQUESTED_WITH');
        $this->route = $url;
        $this->checkReplaceRoute();
        return $this;
    }

    /**
     * @param string $url
     * @return $this
     */
    public function base(string $url): Request
    {
        $project = explode(DS, substr(ROOT, 0, -1));
        $numberHomeArray = 0;
        foreach ($this->home as $home) {
            if (in_array($home, $project)) {
                $numberHomeArray = array_search($home, $project, true);
                break;
            }
        }
        if (isset($project[$numberHomeArray + 1]) && stripos($url, $project[$numberHomeArray + 1]) !== false) {
            $this->base = DS . $project[$numberHomeArray + 1];
        }
        unset($project);
        return $this;
    }

    /**
     * @return void
     */
    private function checkReplaceRoute(): void
    {
        if (
            isset($this->post) && (array_key_exists('control', $this->post) !== false && array_key_exists(
                'action',
                $this->post,
            ) !== false)
        ) {
            if (
                stripos($this->route, (string)$this->post['control']) !== false && stripos(
                    $this->route,
                    (string)$this->post['action'],
                ) !== false
            ) {
                $this->changeRoute = !$this->changeRoute;
            }
            $keys = ['control', 'action'];
            foreach ($keys as $key) {
                $this->newRoute = $this->post['key'];
                if ($key === 'control') {
                    $this->newRoute .= '+';
                }
                unset($this->post[$key], $_POST[$key]);
            }
        }
    }

    /**
     * @return $this
     */
    public function checkExistApiInRoute(): Request
    {
        $route = explode(DS, $this->route);
        if ($this->base !== '' && in_array($this->base, $route) !== false) {
            unset($route[array_search($this->base, $route, true)]);
        }
        if (in_array("api", $route) !== false) {
            $this->api = true;
            unset($route[array_search("api", $route, true)]);
            $newRoute = [];
            foreach ($route as $value) {
                if ($value !== '') {
                    $newRoute[] = $value;
                }
            }
            $route = $newRoute;
            unset($newRoute);
            $this->route = DS . implode((count($route) > 1 ? DS : ''), $route);
        }
        return $this;
    }

    /**
     * @param array $config
     * @return $this
     * @throws Exceptions
     */
    public function methods(array $config): Request
    {
        $this->bootstrap->returnValue('security')->superGlobal($config);
        if (isset($config['files']['attachments'])) {
            $this->attachments = $config['files']['attachments'];
            unset($config['files']['attachments']);
        }
        foreach ($config as $key => $value) {
            $this->{$key} = $value ?? null;
        }
        $this->auth = $this->bootstrap->returnValue('auth');
        $this->bootstrap->paramUnset('auth');
        return $this;
    }

    /**
     * @return $this
     */
    public function activation(): Request
    {
        $this->activations['paginator'] = true;
        return $this;
    }

    /**
     * @return bool
     */
    public function booleanApi(): bool
    {
        return $this->api;
    }

    /**
     * @param array $keys
     * @return $this
     * @throws Exceptions
     */
    public function urlParamsDecrypt(array $keys = []): Request
    {
        if (count($this->params) > 0) {
            $keys = $keys !== [] ? array_merge($keys, ['page', 'id']) : ['page', 'id'];
            foreach ($this->params as $key => $param) {
                if (in_array($key, $keys)) {
                    if ($this->bootstrap->returnValue('security')->validBase64($param)) {
                        throw new Exceptions('Parameter passed is not properly configured.', 404);
                    }
                    $this->params[$key] = base64_decode($param);
                }
            }
        }
        return $this;
    }

    /**
     * @param string $name
     * @param object $object
     * @return $this
     */
    public function changeBootstrap(string $name, object $object): Request
    {
        $this->bootstrap->change([$name => $object], true);
        return $this;
    }

    /**
     * @param string $name
     * @return mixed
     * @throws Exceptions
     */
    public function bootstrap(string $name): mixed
    {
        return $this->bootstrap->returnValue($name);
    }

    /**
     * @return string
     */
    public function csrfPost(): string
    {
        if (isset($this->post['csrf'])) {
            $csrf = $this->post['csrf'];
            unset($this->post['csrf']);
            return $csrf;
        }
        return '';
    }

    /**
     * @param string|null $modo
     * @return mixed
     * @throws Exceptions
     */
    public function datas(?string $modo = null): mixed
    {
        if (isset($modo)) {
            if ($modo === 'method') {
                return $this->server->returnValue('REQUEST_METHOD');
            }
            $modo .= 's';
            return $this->{$modo};
        }
        return $this->posts;
    }

    /**
     * @return $this
     */
    public function requestMethodGet(): Request
    {
        $this->renderCsrf = true;
        $this->server->change(['REQUEST_METHOD' => 'GET'], true);
        return $this;
    }

    /**
     * @return $this
     */
    public function requestMethod(): Request
    {
        if (isset($this->post['_METHOD'])) {
            $method = $this->post['_METHOD'];
            unset($this->post['_METHOD']);
            if (is_array($method)) {
                $newMethod = $method[0];
                $method = $newMethod;
                unset($mewMethod);
            }
            $this->server->change(['REQUEST_METHOD' => strtoupper($method)], true);
        }
        return $this;
    }

    /**
     * @return string
     */
    public function newRoute(): string
    {
        if ($this->changeRoute) {
            return $this->newRoute;
        }
        return '';
    }

    /**
     * @param Instances $instance
     * @param array $bootstrap
     * @return $this
     * @throws Exceptions
     */
    public function applicationBootstrap(Instances $instance, array $bootstrap): Request
    {
        $this->bootstrap = $instance->resolveClass(
            Search_NAMESPACE[0] . DS_REVERSE . 'Collection' . DS_REVERSE . 'Groupings',
            ['datas' => $bootstrap]
        );
        return $this;
    }

    /**
     * @param array|null $routes
     * @return mixed
     */
    public function routes(?array $routes = null): mixed
    {
        if(is_null($routes)){
            return $this->routes;
        }
        $this->routes = $routes;
        return $this;
    }

}