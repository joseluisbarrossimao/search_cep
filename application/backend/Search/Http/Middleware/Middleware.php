<?php

declare(strict_types=1);

namespace Search\Http\Middleware;

use Search\Collection\Groupings;
use Search\Container\Instances;
use Search\Error\Exceptions;
use Search\Http\Runner;

/**
 *
 */
class Middleware
{
    /**
     * @param Instances $instance
     */
    public function __construct(protected Instances $instance)
    {
    }

    /**
     * @param Runner $runner
     * @param Groupings $middleware
     * @return Runner
     * @throws Exceptions
     */
    public function queue(Runner $runner, Groupings $middleware): Runner
    {
        $classesPath = [
            'Error' . DS_REVERSE . 'Middleware' . DS_REVERSE . 'ErrorMiddleware',
            'Security' . DS_REVERSE . 'Middleware' . DS_REVERSE . 'SecurityMiddleware',
            'Executing' . DS_REVERSE . 'Middleware' . DS_REVERSE . 'WebServiceMiddleware',
            'Route' . DS_REVERSE . 'Middleware' . DS_REVERSE . 'RouteMiddleware',
        ];
        $classesPath = array_merge(
            array_merge($classesPath, $middleware->clone()),
            ['Core' . DS_REVERSE . 'Middleware' . DS_REVERSE . 'ApplicationMiddleware'],
        );
        foreach ($classesPath as $classPath) {
            if (
                in_array(
                    substr($classPath, strripos($classPath, (string)DS_REVERSE) + 1),
                    [
                    'ErrorMiddleware',
                    'SecurityMiddleware',
                    'RouteMiddleware',
                    'WebServiceMiddleware',
                    'ApplicationMiddleware',
                    'CacheMiddleware',
                    ],
                )
            ) {
                if (
                    $this->instance->validate(
                        Search_FRAMEWORK . str_replace(DS_REVERSE, DS, $classPath) . '.php',
                        'file',
                    )
                ) {
                    $runner->addQueue(Search_NAMESPACE[0] . DS_REVERSE . $classPath);
                }
            } elseif (
                $this->instance->validate(
                    ROOT_NAMESPACE . str_replace(DS_REVERSE, DS, $classPath) . '.php',
                    'file',
                )
            ) {
                $runner->addQueue(Search_NAMESPACE[1] . DS_REVERSE . $classPath);
            }
        }
        return $runner;
    }

    /**
     * @return array
     */
    public function returnDatas(): array
    {
        return ['instance' => $this->instance];
    }
}