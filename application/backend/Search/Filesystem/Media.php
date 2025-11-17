<?php

declare(strict_types=1);

namespace Search\Filesystem;

use Search\Container\Instances;
use Search\Error\Exceptions;

/**
 *
 */
class Media extends File
{
    /**
     * @var array
     */
    private array $videoMime = ['video/mp4' => 'mp4', 'video/webm' => 'webm'];

    /**
     * @var array
     */
    private array $audioMime = ['audio/mpeg' => 'mp3', 'audio/webm' => 'webm'];

    /**
     * @param Instances $instance
     * @param string $file
     * @param array|null $arq
     * @throws Exceptions
     */
    public function __construct(Instances $instance, string $file, ?array $arq = null)
    {
        $mimetype = $this->mimetype($file);
        if (substr($mimetype, 0, stripos($mimetype, DS)) === 'video') {
            if (!in_array($mimetype, array_keys($this->videoMime))) {
                throw new Exceptions('This video has no accepted extension. Extensions accepted: MP4, WebM.', 404);
            }
        }
        if (!in_array($mimetype, array_keys($this->audioMime))) {
            throw new Exceptions('This audio has no accepted extension. Extensions accepted: MP3, WebM.', 404);
        }
        if (isset($arq)) {
            parent::__construct($instance, $file, $arq);
        }
        parent::__construct($instance, $file);
    }
}
