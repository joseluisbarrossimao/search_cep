<?php

declare(strict_types=1);

namespace Search\Consumption;

use Search\Error\Exceptions;
use SoapClient;
use SoapFault;
use stdClass;

/**
 *
 */
class AccessSoap
{
    /**
     * @var SoapClient
     */
    private SoapClient $soapClient;

    /**
     * @var array
     */
    private array $methods;

    /**
     * @var mixed
     */
    private mixed $response;

    /**
     * @param string $access
     * @throws Exceptions
     */
    public function __construct(string $access)
    {
        try {
            if (stripos(strtolower($access), 'wsdl') === false) {
                throw new Exceptions('This is not a valid WSDL', 404);
            }
            $this->soapClient = new SoapClient($access);
            $this->readWsdl();
        } catch (SoapFault $soapFault) {
            throw new Exceptions($soapFault->getMessage(), $soapFault->getCode());
        }
    }

    private function readWsdl(): void
    {
        $functions = $this->soapClient->__getFunctions();
        $types = $this->soapClient->__getTypes();
        $typesIndex = $this->indexTypes($types);
        foreach ($functions as $sig) {
            $parsed = $this->parseFunctionSignature($sig);
            if ($parsed === null) {
                continue;
            }
            [$method, $requestType, $inlineParams] = $parsed;
            if (str_ends_with($method, 'Response')) {
                continue;
            }
            if ($requestType !== null && isset($typesIndex[$requestType])) {
                $params = $typesIndex[$requestType];
                $this->methods[$method] = $params;
                continue;
            }
            if (!empty($inlineParams)) {
                $this->methods[$method] = $inlineParams;
                continue;
            }
            $this->methods[$method] = [];
        }
        if (empty($this->methods)) {
            throw new Exceptions('No operations found in WSDL', 404);
        }
    }

    private function indexTypes(array $types): array
    {
        $index = [];
        foreach ($types as $t) {
            if (preg_match('/(?:^|\s)struct\s+([A-Za-z0-9_]+)\s*\{([^}]*)\}/m', $t, $m)) {
                $body = $m[2];
                $fields = [];
                foreach (preg_split('/\R+/', trim($body)) as $line) {
                    $line = trim($line);
                    if ($line === '' || !str_ends_with($line, ';')) {
                        continue;
                    }
                    $line = rtrim($line, ';');
                    if (preg_match('/[A-Za-z0-9_\[\]\.]+\s+([A-Za-z0-9_]+)/', $line, $fm)) {
                        $fields[] = $fm[1];
                    }
                }
                if (!empty($fields)) {
                    $index[$m[1]] = $fields;
                }
            }
        }
        return $index;
    }

    private function parseFunctionSignature(string $sig): ?array
    {
        if (!preg_match('/^\s*\S+\s+([A-Za-z0-9_]+)\s*\((.*?)\)\s*$/', $sig, $m)) {
            return null;
        }
        $method = $m[1];
        $args = trim($m[2]);
        if ($args === '' || $args === 'void') {
            return [$method, null, []];
        }
        if (preg_match('/^\s*([A-Za-z0-9_\.]+)\s+\$?[A-Za-z0-9_]+\s*$/', $args, $am)) {
            $requestType = $am[1];
            return [$method, $requestType, []];
        }
        $inline = [];
        foreach (explode(',', $args) as $p) {
            $p = trim($p);
            if ($p === '') {
                continue;
            }
            if (preg_match('/^[A-Za-z0-9_\[\]\.]+\s+\$?([A-Za-z0-9_]+)$/', $p, $pm)) {
                $inline[] = $pm[1];
            }
        }
        return [$method, null, $inline];
    }

    /**
     * @param array $datas
     * @return $this
     * @throws Exceptions
     */
    public function access(array $datas): AccessSoap
    {
        try {
            $this->response = $this->soapClient->{$this->maping($datas)}(array_values($datas));
            return $this;
        } catch (SoapFault $soapFault) {
            throw new Exceptions($soapFault->getMessage(), $soapFault->getCode());
        }
    }

    /**
     * @param array $datas
     * @return string
     * @throws Exceptions
     */
    private function maping(array $datas): string
    {
        $numberFound = 0;
        $found = false;
        $countData = count($datas);
        foreach ($this->methods as $nameMethod => $params) {
            foreach ($params as $param) {
                if (array_key_exists($param, $datas)) {
                    $numberFound++;
                }
                if ($numberFound === $countData) {
                    $found = true;
                    break;
                }
            }
            if ($found) {
                break;
            }
            $numberFound = 0;
        }
        if (!$found) {
            throw new Exceptions('Method not found', 404);
        }
        return $nameMethod;
    }

    /**
     * @return object
     */
    public function returnObject(): object
    {
        if ($this->response instanceof stdClass) {
            return $this->response;
        }
        if (json_validate($this->response)) {
            return json_decode($this->response);
        }
        return $this->response;
    }
}