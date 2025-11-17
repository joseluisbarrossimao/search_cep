<?php

declare(strict_types=1);

if (!defined('DS')) {
    /**
     * Use the DS to separate the directories in other defines
     */
    define('DS', "/");

    /**
     * Use the DS_REVERSE to separate the directories in other defines
     */
    define('DS_REVERSE', '\\');

    /**
     * Define the path to the public directory
     */
    define('PATH_PUBLIC', 'webroot');

    /**
     * Define the path to the namespace directory
     */
    define('PATH_NAMESPACE', 'src');

    /**
     * Define the path to the config directory
     */
    define('PATH_CONFIG', 'config');

    /**
     * Define the path to the abstractions directory
     */
    define('PATH_ABSTRACTIONS', 'abstractions');

    /**
     * Define the path to the vendor directory
     */
    define('PATH_AUTOLOAD', 'vendor');

    /**
     * Define the path to the logs directory
     */
    define('PATH_LOGS', 'logs');

    /**
     * Define the root directory of the application
     */
    define('ROOT', dirname(__DIR__) . DS);

    /**
     * Define the root directory of the configuration files
     */
    define('ROOT_CONFIG', ROOT . PATH_CONFIG . DS);

    /**
     * Define the root directory of the abstractions
     */
    define('ROOT_ABSTRACT', ROOT . PATH_ABSTRACTIONS . DS);

    /**
     * Define the root directory of the public files
     */
    define('ROOT_PUBLIC', ROOT . PATH_PUBLIC . DS);

    /**
     * Define the root namespace directory
     */
    define('ROOT_NAMESPACE', ROOT . PATH_NAMESPACE . DS);

    /**
     * Define the root autoload file
     * This file is used to autoload the classes of the application
     * It is recommended to use Composer for autoloading, but if you want to use
     * your own autoloading mechanism, you can define the path to the autoload file
     * here. The autoload file should return an array with the namespaces and their
     * corresponding directories. For example:
     * return [
     *     'Restfull' => ROOT_NAMESPACE . 'Restfull' . DS,
     *     'App' => ROOT_NAMESPACE . 'App' . DS,
     * ];
     */
    define('ROOT_AUTOLOAD', ROOT . PATH_AUTOLOAD . DS . 'autoload.php');

    /**
     * Define the root logs directory
     */
    define('ROOT_LOGS', ROOT . PATH_LOGS . DS);
}

/**
 * Define the path to the Restfull framework
 */
define('RESTFULL_FRAMEWORK', dirname(__DIR__) . DS . PATH_NAMESPACE . DS);

/**
 * Define the path to the Restfull namespace
 */
define('RESTFULL_NAMESPACE', ['Restfull', 'App']);


/**
 * Define the path to the Restfull MVC components
 */
define('MVC', ['Controller', 'View', ['app' => 'Model', 'restfull' => 'ORM']]);

/**
 * Define the path to the Restfull MVC components for SubMVC
 */
define(
    'SUBMVC',
    [
        'Component',
        'Helper',
        ['Behavior', 'Entity', 'Table', 'Migration', 'Validation', 'Query', 'Mapper']
    ]
);