<?php

declare(strict_types=1);

namespace Search\Container;

use ReflectionClass;
use ReflectionException;
use ReflectionFunction;
use ReflectionObject;
use Search\Collection\Groupings;
use Search\Error\Exceptions;
use Search\Filesystem\File;

/**
 *
 */
class Instances
{
    /**
     * @var Groupings
     */
    private Groupings $dependencies;

    /**
     * @var array
     */
    private array $files = [];

    /**
     * @param mixed $class
     * @param bool $addValue
     * @return array
     * @throws Exceptions
     */
    public function attributes(mixed $class, bool $addValue = true): array
    {
        if (is_string($class)) {
            $class = $this->renameClass($class);
        }
        $reflection = $this->class($class);
        $attributes = [];
        foreach ($reflection->getProperties() as $property) {
            if ($property->class !== $reflection->getName()) {
                continue;
            }
            $property->setAccessible(true);
            if ($addValue) {
                $attributes[$property->name] = $property->getValue($class);
            } else {
                $attributes[] = $property->name;
            }
        }
        return $attributes;
    }

    /**
     * @param string $class
     * @return string
     * @throws Exceptions
     */
    public function renameClass(string $class): string
    {
        if ($this->validate($class, 'file')) {
            $class = str_replace(PATH_NAMESPACE, Search_NAMESPACE[1], $class);
        }
        if (stripos($class, (string)ROOT) !== false) {
            $class = substr($class, strlen(ROOT));
            if (stripos($class, '.php') !== false) {
                $class = substr($class, 0, -4);
            }
            if (strripos($class, DS) !== false) {
                $class = str_replace(DS, DS_REVERSE, $class);
            }
            $location = substr($class, 0, stripos($class, (string)DS_REVERSE));
            if (in_array($location, ['vendor', 'src'])) {
                $class = $location === 'vendor' ? str_replace(
                    substr(Search_FRAMEWORK, strlen(ROOT), -1),
                    ROOT_NAMESPACE[0],
                    $class,
                ) : str_replace(substr(ROOT_NAMESPACE, strlen(ROOT), -1), Search_NAMESPACE[1], $class);
            }
        }
        return $class;
    }

    /**
     * @param string $classPath
     * @param string $classInstance
     * @return bool
     * @throws Exceptions
     */
    public function validate(string $classPath, string $classInstance, string $classNamespace = ''): bool
    {
        $dependencies = [$classInstance => $classPath];
        if ($classInstance === 'file') {
            $dependencies = array_merge(['instance' => $this], $dependencies);
        }
        $class = $this->resolveClass(
            Search_NAMESPACE[0] . DS_REVERSE . 'Filesystem' . DS_REVERSE . ucfirst($classInstance),
            $dependencies,
        );
        if ($classNamespace !== '') {
            if ($class->exists()) {
                $isEquals = false;
                foreach ($class->read()['content'] as $data) {
                    if (substr($data, 0, 9) === 'namespace') {
                        $isEquals = stripos($data, $classNamespace) !== false;
                        break;
                    }
                }
                return $isEquals;
            }
            return false;
        }
        return $class->exists();
    }

    /**
     * @param string $class
     * @param array|null $dependencies
     * @param bool $activeExceptions
     * @return object|null
     * @throws Exceptions
     */
    public function resolveClass(string $class, ?array $dependencies = null, bool $activeExceptions = false): ?object
    {
        if (!is_null($dependencies)) {
            $this->saveClassDependencies($dependencies);
        }
        $reflection = $this->class($class);
        if (is_object($reflection)) {
            if (!$reflection->isInstantiable()) {
                throw new Exceptions($reflection->getName() . ' is not instanciable');
            }
            $constructor = $reflection->getConstructor();
            if (is_null($constructor)) {
                return new ($reflection->getName());
            }
            return $reflection->newInstanceArgs(
                $this->validateClassDependencies(
                    $this->convertObjectInArrayParameters($constructor->getParameters())
                )
            );
        }
        return null;
    }

    /**
     * @param array $dependencies
     * @return $this
     */
    public function saveClassDependencies(array $dependencies): Instances
    {
        $this->dependencies = new Groupings($dependencies);
        return $this;
    }

    /**
     * @param mixed $class
     * @return Instances
     * @throws Exceptions
     * @throws ReflectionException
     */
    private function class(mixed $class): ReflectionClass|ReflectionObject|ReflectionFunction
    {
        try {
            if (is_object($class)) {
                return new ReflectionObject($class);
            }
            return new ReflectionClass($class);
        } catch (ReflectionException $reflectionException) {
            $this->renderMsg($reflectionException->getMessage() . ' in line ' . $reflectionException->getLine());
            throw new Exceptions(
                $reflectionException->getMessage(),
                500,
                $reflectionException->getTrace(),
                $reflectionException->getPrevious()
            );
        }
    }

    /**
     * @param string $msg
     * @return void
     * @throws Exceptions
     */
    private function renderMsg(string $msg): void
    {
        $file = new File($this, ROOT . 'logs' . DS . 'notFound.log');
        if (isset($_SERVER['REQUEST_URI'])) {
            $msg .= ' and ' . $_SERVER['REQUEST_URI'];
        }
        $text = '';
        foreach ($file->read()['content'] as $value) {
            if ($value !== '') {
                $text .= $value;
            }
        }
        $text .= vsprintf('[%s][%s]: %s' . PHP_EOL, [date('Y-m-d H:i:s'), 'Info', $msg]);
        $file->write($text);
    }

    /**
     * @param string $classPath
     * @param string $classInstance
     * @return array
     * @throws Exceptions
     */
    public function read(string $classPath, string $classInstance): array
    {
        $dependencies = [$classInstance => $classPath];
        if ($classInstance === 'file') {
            $dependencies = array_merge(['instance' => $this], $dependencies);
        }
        $class = $this->resolveClass(
            Search_NAMESPACE[0] . DS_REVERSE . 'Filesystem' . DS_REVERSE . ucfirst($classInstance),
            $dependencies,
        );
        return $class->read();
    }

    /**
     * @param array $parameters
     * @param bool $compare
     * @return array|bool
     * @throws Exceptions
     */
    public function validateClassDependencies(array $parameters, bool $compare = false): bool|array
    {
        $compareds = $dependencies = [];
        if ($parameters !== []) {
            foreach ($parameters as $key => $parameter) {
                $dependecy = $parameter['name'];
                if ($parameter['type'] === 'object') {
                    if ($this->dependencies->returnValue($key) instanceof $dependecy) {
                        $dependencies[] = $this->dependencies->returnValue($key);
                    } else {
                        $dependencies[] = $this->resolveClass($dependecy);
                    }
                } elseif ($this->dependencies->exist($dependecy)) {
                    $dependencies[] = $this->dependencies->returnValue($dependecy);
                } elseif ($parameter['isDefaultValue']) {
                    $dependencies[] = $parameter['defaultValue'];
                } elseif (!$compare) {
                    throw new Exceptions(sprintf('Unable to resolve parameter %s!', $dependecy), 404);
                }
                if ($compare) {
                    $compareds[] = isset($dependencies[$key]) ? 'exist' : 'not exist';
                }
            }
            if (count($compareds) > 0) {
                return in_array('exist', $compareds) !== false;
            }
        }
        if ($compare) {
            return false;
        }
        return $dependencies;
    }

    /**
     * @param array $parameters
     * @return array
     */
    private function convertObjectInArrayParameters(array $parameters): array
    {
        $params = [];
        foreach ($parameters as $key => $parameter) {
            $params[$key] = [
                'name' => $parameter->getName(),
                'type' => $parameter->getType()->getName(),
                'isDefaultValue' => $parameter->isDefaultValueAvailable(),
                'defaultValue' => null
            ];
            if ($params[$key]['isDefaultValue']) {
                $params[$key]['defaultValue'] = $parameter->getDefaultValue();
            }
        }
        return $params;
    }

    /**
     * @param string $format
     * @param array $args
     * @return string
     * @throws Exceptions
     */
    public function assemblyClassOrPath(string $format, array $args): string
    {
        if (stripos($format, '%s') === false) {
            $oldFormat = explode(' ', $format);
            foreach ($oldFormat as $value) {
                if (stripos($value, '%') !== false) {
                    $value = stripos($value, '(') !== false ? '(%s)' : '%s';
                }
                $newFormat[] = $value;
            }
            $format = implode(' ', $newFormat);
        }
        if (substr_count($format, '%s') !== count($args)) {
            throw new Exceptions(
                'The ' . $format . ' format or ' . implode(', ', $args) . ' files are not equal.',
                404,
            );
        }
        $part = '';
        foreach ($args as $arg) {
            if (stripos($format, '%s') !== 0) {
                $part = substr($format, 0, stripos($format, '%s'));
            }
            $format = $part . $arg . substr($format, stripos($format, '%s') + 2);
            if ($part !== '') {
                $part = '';
            }
        }
        return $format;
    }

    /**
     * @param mixed $callable
     * @param array $datas
     * @return mixed
     */
    public function callableFunction(mixed $callable, array $datas = []): mixed
    {
        return call_user_func_array($callable, $datas);
    }

    /**
     * @param object $class
     * @return string
     * @throws Exceptions
     */
    public function name(object $class): string
    {
        if (is_string($class)) {
            $class = $this->renameClass($class);
        }
        $reflection = $this->class($class);
        return $reflection->getName();
    }

    /**
     * @param mixed $class
     * @return array
     * @throws Exceptions
     */
    public function methods(mixed $class): array
    {
        if (is_string($class)) {
            $class = $this->renameClass($class);
        }
        $reflection = $this->class($class);
        foreach ($reflection->getMethods() as $method) {
            $methods[] = $method->name;
        }
        return $methods;
    }

    /**
     * @param $class
     * @param string $method
     * @return array
     * @throws Exceptions
     */
    public function parameters($class, string $method = ''): array
    {
        if (is_string($class)) {
            $class = $this->renameClass($class);
        }
        $reflection = $this->class($class);
        if (is_callable($class)) {
            return $this->convertObjectInArrayParameters($reflection->getParameters());
        }
        if ($method === '') {
            $newMethod = $reflection->getConstructor();
            if (is_null($newMethod)) {
                return [];
            }
            return $this->convertObjectInArrayParameters($newMethod->getParameters());
        }
        return $this->convertObjectInArrayParameters($reflection->getMethod($method)->getParameters());
    }

    /**
     * @param array $files
     * @return Instances
     */
    public function theseFilesAreFromTheFramework(array $files): Instances
    {
        $this->files = $files;
        return $this;
    }

    /**
     * @param string $class
     * @param string $control
     * @param int $number
     * @return string
     * @throws Exceptions
     */
    public function locateTheFileWhetherItIsInTheAppOrInTheFramework(
        string $class,
        string $control = 'main',
        int $number = 0
    ): string {
        $classPath = $class;
        if (stripos($classPath, (string)DS_REVERSE) !== false) {
            $classPath = str_replace(DS_REVERSE, DS, $classPath);
        }
        $namePath = substr(ROOT_NAMESPACE, 0, strripos(ROOT_NAMESPACE, DS));
        $nameSpace = Search_NAMESPACE[1];
        if ($number === 1) {
            $nameSpace = Search_NAMESPACE[0];
            $namePath = substr(Search_FRAMEWORK, 0, strripos(Search_FRAMEWORK, DS));
        }
        $classPath = str_replace(DS . DS, DS, str_replace($nameSpace, $namePath, $classPath));
        if (stripos($classPath, '.php') === false) {
            $classPath .= '.php';
        }
        if (!$this->validate($classPath, 'file', $class)) {
            if (in_array($control, $this->files) !== false) {
                $class = str_replace(Search_NAMESPACE[1], Search_NAMESPACE[0], $class);
                $classPath = str_replace(Search_NAMESPACE[1], Search_NAMESPACE[0], $classPath);
            }
            if ($number === 0) {
                $number++;
                $class = $this->locateTheFileWhetherItIsInTheAppOrInTheFramework($class, $control, $number);
            }
        }
        if ($control === 'error') {
            $class = $classPath;
        }
        if (stripos($class, '.php') !== false) {
            $class = str_replace('.php', '', $class);
        }
        return $class;
    }

    /**
     * @param string $component
     * @param array $partOfTheClassPath
     * @param string $otherPartClassPath
     * @return array
     * @throws Exceptions
     */
    public function returnMessagesTheExternalComponent(
        string $component,
        array $partOfTheClassPath,
        string $otherPartClassPath,
    ): array {
        if (stripos($component, 'vendor') !== false) {
            $component = substr($component, stripos($component, 'vendor') + strlen('vendor'));
        }
        $component = substr($component, 0, strlen($component) - 1);
        $partClassPaths = explode(DS_REVERSE, $partOfTheClassPath[1]);
        foreach ($partClassPaths as $number => $partClassPath) {
            if (stripos($partClassPath, '-') !== false) {
                $partClassPath = str_replace('-', '', $partClassPath);
            }
            $partClassPaths[$number] = ucfirst($partClassPath);
        }
        $partOfTheClassPath[1] = implode(DS_REVERSE, $partClassPaths);
        $component = str_replace($partOfTheClassPath[0], $partOfTheClassPath[1], $component);
        $classResult = str_replace(DS . PATH_NAMESPACE . DS, '', $component) . $otherPartClassPath;
        if ($this->validate($classResult, 'file')) {
            $classpath = $this->resolveClass($classResult);
            return $classpath->messages();
        }
        return [];
    }
}
