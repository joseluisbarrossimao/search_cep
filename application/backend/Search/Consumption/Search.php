<?php

declare(strict_types=1);

namespace Search\Consumption;

use Search\Container\Instances;
use Search\Error\Exceptions;

/**
 *
 */
class Search
{
    /**
     * @var AccessCurl|AccessSoap
     */
    private AccessCurl|AccessSoap $reflection;

    /**
     * @var string
     */
    private string $className;

    /**
     * @param Instances $instance
     */
    public function __construct(private Instances $instance)
    {
    }

    /**
     * @param string $className
     * @param string $data
     * @return $this
     * @throws Exceptions
     */
    public function startApi(string $className, mixed $data,): Search
    {
        if (strlen($className) === 4) {
            $className = 'Access' . ucfirst($className);
        }
        if (in_array($className, ['AccessCurl', 'AccessSoap']) === false) {
            throw new Exceptions(
                "This {$className} isn't a valid class, class accepted são: AccessSoap e AccessCurl.",
                404
            );
        }
        $this->reflection = $this->instance->resolveClass(
            'Search' . DS_REVERSE . 'Consumption' . DS_REVERSE . $className,
            ['access' => $data],
        );
        $this->className = $className;
        return $this;
    }

    /**
     * @param array $data
     * @param string $identify
     * @return mixed
     */
    public function responseData(array $data, string $identify = ''): mixed
    {
        if ($this->className === 'AccessCurl') {
            $route = '';
            if (isset($data['uriConcat'])) {
                $route = '?' . $data['uriConcat'];
                unset($data['uriConcat']);
            }
            $this->reflection->access($data, $route);
        } else {
            $this->reflection->access($data);
        }
        return $this->treatment($this->reflection->returnObject(), $identify);
    }

    /**
     * @param mixed $object
     * @param string $identify
     * @return mixed
     */
    private function treatment(mixed $object, string $identify = ''): mixed
    {
        if (!is_object($object)) {
            $object = (object)$object;
        }
        if (isset($object->return)) {
            return $object->return;
        }
        if ($identify !== '') {
            return $object->{$identify};
        }
        return $object;
    }
}