<?php

declare(strict_types=1);

namespace Search\Collection;

use ArrayIterator;
use ArrayObject;
use Search\Error\Exceptions;

/**
 *
 */
class Groupings
{
    /**
     * @var ArrayObject|null
     */
    private ?ArrayObject $arrayObject = null;

    /**
     * @var bool
     */
    private bool $instantiated = false;

    /**
     * @param array $datas
     */
    public function __construct(array $datas)
    {
        if ($datas === []) {
            return;
        }
        $this->startArrayObject($datas);
    }

    /**
     * @param array $datas
     * @return void
     */
    private function startArrayObject(array $datas): void
    {
        if (!$this->instantiated) {
            $this->instantiated = !$this->instantiated;
        }
        $this->arrayObject = new ArrayObject($datas);
    }

    /**
     * @param string $key
     * @param string|null $subkey
     * @return bool
     */
    public function exist(string $key, ?string $subkey = null): bool
    {
        if (!is_null($subkey)) {
            return array_key_exists($subkey, $this->arrayObject->offsetGet($key));
        }
        return $this->arrayObject->offsetExists($key);
    }

    /**
     * @param array $datas
     * @param bool $concat
     * @return $this
     */
    public function change(array $datas, bool $concat = false): Groupings
    {
        if ($concat) {
            $oldDatas = $this->clone();
            if ($oldDatas === []) {
                $this->startArrayObject($datas);
            }
            $multi = false;
            foreach (array_map('gettype', $oldDatas) as $key => $value) {
                if (array_key_exists($key, $datas) !== false) {
                    if ($value === 'array') {
                        $multi = true;
                        break;
                    }
                }
            }
            [$makeMerge, $oldDatas] = $this->mergeData($oldDatas, $datas, $multi);
            if ($makeMerge) {
                $this->arrayObject->exchangeArray($oldDatas);
                return $this;
            }
            $this->arrayObject->exchangeArray(array_merge($oldDatas, $datas));
            return $this;
        }
        if (!($this->arrayObject instanceof ArrayObject)) {
            $this->startArrayObject($datas);
            return $this;
        }
        $this->arrayObject->exchangeArray($datas);
        return $this;
    }

    /**
     * @return array
     */
    public function clone(): array
    {
        if (!$this->instantiated) {
            return [];
        }
        return $this->arrayObject->getArrayCopy();
    }

    /**
     * @param array $oldDatas
     * @param array $newDatas
     * @param bool $multi
     * @return array
     */
    private function mergeData(array $oldDatas, array $newDatas, bool $multi = false): array
    {
        $makeMerge = false;
        foreach (array_keys($newDatas) as $key) {
            if (array_key_exists($key, $oldDatas)) {
                if ($multi) {
                    [$makeMerge, $oldDatas[$key]] = $this->mergeData($oldDatas[$key], $newDatas[$key]);
                } elseif ($oldDatas[$key] !== $newDatas[$key]) {
                    if (!$makeMerge) {
                        $makeMerge = true;
                    }
                    $oldDatas[$key] = $newDatas[$key];
                }
            } else {
                if (!$makeMerge) {
                    $makeMerge = true;
                }
                $oldDatas[$key] = $newDatas[$key];
            }
        }
        return [$makeMerge, $oldDatas];
    }

    /**
     * @param array $keys
     * @param string $identifyInsertKey
     * @param mixed $value
     * @return $this
     */
    public function insertValue(array $keys, string $identifyInsertKey, mixed $value): Groupings
    {
        $this->arrayObject->exchangeArray($this->nestedValue($this->clone(), $keys, $identifyInsertKey, $value));
        return $this;
    }

    /**
     * @param array $oldDatas
     * @param array $keys
     * @param string $identifyInsertKey
     * @param mixed $value
     * @return array
     */
    private function nestedValue(array $oldDatas, array $keys, string $identifyInsertKey, mixed $values): array
    {
        $exists = false;
        $currentKey = array_shift($keys);
        foreach ($oldDatas as $key => $datas) {
            if (is_array($datas)) {
                if ($currentKey === $key) {
                    $oldDatas[$key] = $this->nestedValue($datas, [$key], $identifyInsertKey, $values);
                }
            } else {
                if ($key === $identifyInsertKey) {
                    $oldDatas[$key] = $values;
                    $exists = true;
                }
            }
        }
        if ($exists) {
            return $oldDatas;
        }
        $oldDatas[$identifyInsertKey] = $values;
        return $oldDatas;
    }

    /**
     * @param string $key
     * @param string|null $subkey
     * @return mixed
     * @throws Exceptions
     */
    public function returnValue(string $key, ?string $subkey = null): mixed
    {
        if (!is_null($subkey)) {
            if (array_key_exists($subkey, $this->arrayObject->offsetGet($key)) === false) {
                throw new Exceptions(sprintf('The key %s does not exist in the array.', $subkey), 404);
            }
            return $this->arrayObject->offsetGet($key)[$subkey];
        }
        return $this->arrayObject->offsetGet($key);
    }

    /**
     * @param string|null $iterator
     * @return ArrayIterator
     */
    public function iterator(?string $iterator = null): ArrayIterator
    {
        if (!is_null($iterator)) {
            $this->arrayObject->setIteratorClass($iterator);
        }
        return $this->arrayObject->getIterator();
    }

    /**
     * @param string $key
     * @return $this
     */
    public function paramUnset(string $key): Groupings
    {
        $this->arrayObject->offsetUnset($key);
        return $this;
    }

    /**
     * @return bool
     */
    public function exists(): bool
    {
        return $this->instantiated;
    }

    /**
     * @return int
     */
    public function counts(): int
    {
        if (!$this->instantiated) {
            return 0;
        }
        return $this->arrayObject->count();
    }
}
