<?php

declare(strict_types=1);

namespace Search\Error;

use Exception;
use Throwable;

/**
 *
 */
class Exceptions extends Exception
{
    /**
     * @var array
     */
    private array $traces = [];

    /**
     * @param string|object $mensagem
     * @param int|null $error
     * @param array|null $trace
     * @param Throwable|null $throwable
     */
    public function __construct(
        string|object $mensagem,
        ?int $error = null,
        ?array $trace = null,
        ?Throwable $throwable = null
    ) {
        if (is_object($mensagem)) {
            $this->message = $mensagem->getMessage();
            $this->code = $mensagem->getCode() === 0 ? 404 : ($error !== '' ? $error : $mensagem->getCode());
            $this->file = $mensagem->getFile();
            $this->line = $mensagem->getLine();
            $this->traces = $mensagem->getTrace();
        } else {
            if (isset($trace)) {
                $newTrace = array_reverse($this->getTrace());
                $newTrace[] = $trace;
                $this->traces = array_reverse($newTrace);
            }
            parent::__construct($mensagem, ($error === '' || !is_null($error) ? 404 : $error), $throwable);
        }
    }

    /**
     * @return array
     */
    public function getTraces(): array
    {
        if (count($this->traces) > 0) {
            return $this->traces;
        }
        return $this->getTrace();
    }
}
