<?php

declare(strict_types=1);

namespace Search\Consumption;

use Search\Error\Exceptions;

/**
 *
 */
class AccessCurl
{
    /**
     * @var mixed
     */
    private mixed $response = '';

    /**
     * @param string $access
     */
    public function __construct(private string $access)
    {
    }

    /**
     * @param array $datas
     * @param string $uriConcat
     * @return $this
     * @throws Exceptions
     */
    public function access(array $datas, string $uriConcat = ''): AccessCurl
    {
        if ($uriConcat !== '') {
            $this->access .= $uriConcat;
        }
        if (stripos($this->access, (string)DS_REVERSE) !== false) {
            $this->access = str_replace(DS_REVERSE, DS, $this->access);
        }
        $ch = curl_init($this->access);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        if (array_key_exists('CURLOPT_CUSTOMREQUEST', $datas)) {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $datas['CURLOPT_CUSTOMREQUEST']);
        }
        if (array_key_exists('CURLOPT_POSTFIELDS', $datas)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $datas['CURLOPT_POSTFIELDS']);
        }
        if (array_key_exists('CURLOPT_FOLLOWLOCATION', $datas)) {
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, $datas['CURLOPT_FOLLOWLOCATION']);
        }
        if (array_key_exists('CURLOPT_SSL_VERIFYPEER', $datas)) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $datas['CURLOPT_SSL_VERIFYPEER']);
        }
        if (array_key_exists('CURLOPT_ENCODING', $datas)) {
            curl_setopt($ch, CURLOPT_ENCODING, $datas['CURLOPT_ENCODING']);
        }
        if (array_key_exists('CURLOPT_CONNECTTIMEOUT', $datas)) {
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $datas['CURLOPT_CONNECTTIMEOUT']);
        }
        if (array_key_exists('CURLOPT_USERAGENT', $datas)) {
            curl_setopt($ch, CURLOPT_USERAGENT, $datas['CURLOPT_USERAGENT']);
        }
        if (array_key_exists('CURLOPT_HEADER', $datas)) {
            curl_setopt($ch, CURLOPT_HEADER, $datas['CURLOPT_HEADER']);
        }
        if (array_key_exists('CURLOPT_HTTPHEADER', $datas)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $datas['CURLOPT_HTTPHEADER']);
        }
        if (array_key_exists('CURLOPT_TIMEOUT', $datas)) {
            curl_setopt($ch, CURLOPT_TIMEOUT, $datas['CURLOPT_TIMEOUT']);
        }
        $response = curl_exec($ch);
        curl_close($ch);
        if ($response === false) {
            throw new Exceptions('Curl callback not found.', 404);
        }
        $this->response = $response;
        return $this;
    }

    /**
     * @return object
     * @throws Exceptions
     */
    public function returnObject(): object
    {
        $json = json_decode($this->response);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exceptions('Error in json_decode: ' . json_last_error_msg(), 500);
        }
        return $json;
    }
}
