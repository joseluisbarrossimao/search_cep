<?php

declare(strict_types=1);

namespace Search\Http;

use Search\Collection\Groupings;
use Search\Container\Instances;
use Search\Error\Exceptions;
use Search\Route\Route;

/**
 *
 */
class Response
{
    /**
     * @var int
     */
    private int $httpCode = 200;

    /**
     * @var array
     */
    private array $headers = [];

    /**
     * @var array|string
     */
    private array|string $body = '';

    /**
     * @var array
     */
    private array $message = [
        100 => 'Continue',
        101 => 'Switching',
        102 => 'Processing (WebDAV)',
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        203 => 'Non-Authoritative Information',
        204 => 'No Content',
        205 => 'Reset Content',
        206 => 'Partial Content',
        207 => 'Multi-Status (WebDAV)',
        208 => 'Multi-Status (WebDAV)',
        226 => 'IM Used (HTTP Delta encoding)',
        300 => 'Multiple Choice',
        301 => 'Moved Permanently',
        302 => 'Found',
        303 => 'See Other',
        304 => 'Not Modified',
        305 => 'Use Proxy',
        306 => 'unused',
        307 => 'Temporary Redirect',
        308 => 'Permanent Redirect',
        400 => 'Bad Request',
        401 => 'Unauthorized',
        402 => 'Payment Required',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        406 => 'Not Acceptable',
        407 => 'Proxy Authentication Required',
        408 => 'Request Timeout',
        409 => 'Conflict',
        410 => 'Gone',
        411 => 'Length Required',
        412 => 'Precondition Failed',
        413 => 'Payload Too Large',
        414 => 'URI Too Long',
        415 => 'Unsupported Media Type',
        416 => 'Requested Range Not Satisfiable',
        417 => 'Expectation Failed',
        418 => "I'm a teapot",
        421 => 'Misdirected Request',
        422 => 'Unprocessable Entity (WebDAV)',
        423 => 'Locked (WebDAV)',
        424 => 'Failed Dependency (WebDAV)',
        426 => 'Upgrade Required',
        428 => 'Precondition Required',
        429 => 'Too Many Requests',
        431 => 'Request Header Fields Too Large',
        451 => 'Unavailable For Legal Reasons',
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Timeout',
        505 => 'HTTP Version Not Supported',
        506 => 'Variant Also Negotiates',
        507 => 'Insufficient Storage',
        508 => 'Loop Detected (WebDAV)',
        510 => 'Not Extended',
        511 => 'Network Authentication Required',
        598 => 'Network read timeout error',
        599 => 'Network connect timeout error',
    ];

    /**
     * @param Groupings $server
     */
    public function __construct(public Groupings $server)
    {
    }

    /**
     * @param int|null $httpCode
     * @return mixed
     * @throws Exceptions
     */
    public function httpCode(?int $httpCode = null): mixed
    {
        if (!is_null($httpCode)) {
            if ($httpCode !== 200) {
                $this->httpCode = $httpCode;
            }
            $this->headers['IDM'] = ($this->server->exist('SERVER_PROTOCOL') ? $this->server->returnValue(
                'SERVER_PROTOCOL'
            ) : 'HTTP/1.1') . ' ' . $this->httpCode . ' ' . $this->message[$this->httpCode];
            return $this;
        }
        return $this->httpCode;
    }

    /**
     * @return bool
     */
    public function checkBody(): bool
    {
        return $this->body !== null;
    }

    /**
     * @return mixed
     */
    public function send(): mixed
    {
        foreach ($this->headers as $key => $value) {
            header(sprintf('%s: %s', $key, $value));
        }
        http_response_code($this->httpCode);
        return $this->body;
    }

    /**
     * @param string $url
     * @param bool $ajax
     * @return $this
     */
    public function redirect(string $url, bool $ajax = false): Response
    {
        if (substr($url, 0, 1) === DS) {
            $url = substr($url, 1);
        }
        $url = substr($url, 0, 1) === DS ? URL . $url : URL . DS . $url;
        if (!$ajax) {
            $this->headers = ['location' => $url];
        } else {
            $this->body(
                json_encode(['valid' => true, 'redirect' => $url], JSON_FORCE_OBJECT | JSON_UNESCAPED_UNICODE),
            );
        }
        return $this;
    }

    /**
     * @param array|string $body
     * @return $this
     */
    public function body(array|string $body): Response
    {
        $this->body = $body;
        return $this;
    }

    /**
     * @param array $headers
     * @return $this
     */
    public function headers(array $headers): Response
    {
        $this->headers = $headers;
        return $this;
    }

    /**
     * @return array|string
     */
    public function content(): array|string
    {
        return $this->body;
    }

    /**
     * @return array
     */
    public function recovery(): array
    {
        return ['body' => $this->body, 'headers' => $this->headers];
    }
}