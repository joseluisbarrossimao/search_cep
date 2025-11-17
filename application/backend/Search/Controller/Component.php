<?php

declare(strict_types=1);

namespace Search\Controller;

use Search\Controller\BaseController;
use Search\Container\Instances;
use Search\Error\Exceptions;
use Search\Http\Request;
use Search\Http\Response;

/**
 *
 */
class Component
{
    /**
     * @var BaseController
     */
    protected BaseController $controller;

    /**
     * @var Request
     */
    protected Request $request;

    /**
     * @var Response
     */
    protected Response $response;

    /**
     * @param BaseController $controller
     */
    public function __construct(BaseController $controller)
    {
        $this->request = $controller->request;
        $this->response = $controller->response;
        $this->controller = $controller;
    }


    public function instance()
    {
        return $this->instance;
    }

    /**
     * @param string $type
     * @param array $table
     * @param array $options
     * @param array $details
     * @return object
     * @throws Exceptions
     */
    public function querys(string $type, array $table, array $options = [], array $details = []): object
    {
        return $this->controller->querys($type, $table, $options, $details);
    }

    /**
     * @param string $format
     * @param array $args
     * @return string
     * @throws Exceptions
     */
    public function vsprintf(string $format, array $args): string
    {
        return $this->instance->assemblyClassOrPath($format, $args);
    }

    public function otherComponent(string $component): object
    {
        if (ctype_lower($component)) {
            $component = ucfirst($component);
        }
        return $this->controller->{$component};
    }

    protected function returnMetadata(array $table, array $options, array $optionsQuery = []): object
    {
        return $this->controller->TableRegistry(
            $this->instance->resolveClass(
                Search_NAMESPACE[0] . DS_REVERSE . 'Collection' . DS_REVERSE . 'Groupings',
                ['datas' => $optionsQuery]
            ),
            $table
        );
    }
}
