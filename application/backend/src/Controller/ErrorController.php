<?php

declare(strict_types=1);

namespace App\Controller;

use Restfull\Error\Exceptions;
use Restfull\Event\Event;

/**
 *
 */
class ErrorController extends AppController
{

    /**
     * @param Event $event
     * @return $this
     * @throws Exceptions
     */
    public function beforeFilter(Event $event): ErrorController
    {
        $actions = ['destiny'];
        foreach ($actions as $action) {
            $this->unencryptedRoute[] = 'main' . DS . $action;
        }
        $this->Auth->pages([strtolower($this->name) => $actions]);
        $this->notORM = [$actions];
        parent::beforeFilter($event);
        return $this;
    }

    /**
     * @param array $param
     * @return void
     * @throws Exceptions
     */
    public function handling(array $param): void
    {
        $traces = $arguments = [];
        for ($a = (count($param['traces']) - 1); $a >= 0; $a--) {
            $function = $param['traces'][$a]['function'];
            if ($function === 'call_user_func_array') {
                $function = $param['traces'][$a - 1]['function'];
                $file = $param['traces'][$a - 2]['file'];
                if (is_null($file)) {
                    $file = $param['traces'][$a - 1]['file'];
                }
                $param['traces'][$a - 1]['line'] = $this->identifyNextTrace($function, $file);
                $param['traces'][$a - 1]['file'] = $param['traces'][$a - 2]['file'];
            }
            if (in_array($function, ["__construct", "__Construct", 'loadClass']) === false || $a === 0) {
                if (isset($param['traces'][$a]['class']) && isset($param['traces'][$a]['type']) && isset($function)) {
                    $line = $param['traces'][$a]['line'];
                    $line--;
                    $function = $param['traces'][$a]['class'] . $param['traces'][$a]['type'] . $function;
                    if (!in_array($function, $traces)) {
                        $arguments[$function] = $this->arguments(
                            $line,
                            $param['traces'][$a]['file'],
                        );
                    }
                    $traces[] = $function . " - " . $param['traces'][$a]['file'] . ", line: " . $line;
                }
            }
        }
        $this->dataView('traces', $traces);
        $this->dataView('msg', $param['msg']);
        $this->dataView('args', $arguments);
        return;
    }

    /**
     * @param string $method
     * @param string $file
     * @return int
     * @throws Exceptions
     */
    private function identifyNextTrace(string $method, string $file): int
    {
        $arq = $this->instance->resolveClass(
            'Restfull' . DS_REVERSE . 'Filesystem' . DS_REVERSE . 'File',
            ['instance' => $this->instance, 'file' => $file],
        );
        $file = $arq->read();
        $count = count($file['content']);
        for ($a = 0; $a < $count; $a++) {
            if (stripos($file['content'][$a], $method) !== false) {
                $line = $a + 1;
            }
        }
        return $line;
    }

    /**
     * @param int $line
     * @param string $file
     * @return array
     * @throws Exceptions
     */
    private function arguments(int $line, string $file): array
    {
        $arq = $this->instance->resolveClass(
            'Restfull' . DS_REVERSE . 'Filesystem' . DS_REVERSE . 'File',
            ['instance' => $this->instance, 'file' => $file],
        );
        $fileRead = $arq->read()['content'];
        $linesLimit = $this->validLines($line, $fileRead);
        $numbers = [$line - $linesLimit, $line + ($linesLimit + 1)];
        for ($number = $numbers[0]; $number < $numbers[1]; $number++) {
            if (isset($fileRead[$number])) {
                $lines[$number] = $fileRead[$number];
            }
        }
        return ['line' => $lines, 'identify' => $line];
    }

    /**
     * @param int $line
     * @param array $file
     * @param int $limit
     * @return int
     */
    private function validLines(int $line, array $file, int $limit = 5): int
    {
        $limitResult = $limit;
        if (in_array(($line + $limit), array_keys($file)) === false) {
            $limitResult = $this->validLines($line, $file, $limit - 1);
        }
        return $limitResult;
    }

    /**
     * @param array $params
     * @return void
     * @throws Exceptions
     */
    public function destiny(array $params = []): void
    {
        $this->layout = $this->name;
        $this->dataView(
            'msg',
            $this->Translator->translation($this->Auth->outherData('errorRoute')[0]),
        );
        return;
    }
}
