<?php

declare(strict_types=1);

namespace Search\Filesystem;

use Search\Container\Instances;
use Search\Error\Exceptions;

/**
 *
 */
class Upload
{
    /**
     * @var int
     */
    protected int $sizetmp = 0;

    /**
     * @var File|Image|Media
     */
    private File|Image|Media $file;

    /**
     * @var string
     */
    private string $mimetype;

    /**
     * @param Instances $instance
     * @param array $file
     * @param int $size
     * @throws Exceptions
     */
    public function __construct(Instances $instance, array $file, int $size = 100000000)
    {
        $this->mimetype = $file['type'];
        if (in_array(substr($this->mimetype, 0, stripos($this->mimetype, DS)), ['video', 'audio', 'image'])) {
            $type = in_array(
                substr($this->mimetype, 0, stripos($this->mimetype, DS)),
                ['video', 'audio'],
            ) ? 'Media' : ucfirst(substr($this->mimetype, 0, stripos($this->mimetype, DS)));
            $this->file = $instance->resolveClass(
                Search_NAMESPACE[0] . DS_REVERSE . 'Filesystem' . DS_REVERSE . $type,
                ['instance' => $instance, 'file' => ROOT . 'temp' . DS . $file['name'], 'arq' => $file],
            );
            if ($this->file->valid($type)) {
                $this->sizeLimit($size);
            }
        } else {
            $this->file = $instance->resolveClass(
                Search_NAMESPACE[0] . DS_REVERSE . 'Filesystem' . DS_REVERSE . 'File',
                ['instance' => $instance, 'file' => ROOT . 'temp' . DS . $file['name'], 'arq' => $file],
            );
            $this->sizeLimit($size);
        }
        if (!is_uploaded_file($file['tmp_name'])) {
            throw new Exceptions(
                'The file you tried to upload was not accepted.',
                404,
            );
        }
    }

    /**
     * @param int $size
     * @return $this
     * @throws Exceptions
     */
    public function sizeLimit(int $size): Upload
    {
        if ($size < $this->file->tmp('size')) {
            throw new Exceptions('Allowed limit exceeded.', 404);
        }
        $this->sizetmp = $size;
        return $this;
    }

    /**
     * @param string $key
     * @return string
     */
    public function tmp(string $key = 'tmp_name'): string
    {
        return $this->file->tmp($key);
    }

    /**
     * @return bool
     * @throws Exceptions
     */
    public function insert(): bool
    {
        $type = $this->file->tmp('type');
        if (substr($type, 0, stripos($type, DS)) === 'image' && $this->sizetmp !== 100000000) {
            $names = $this->file->createDifferentSizes($this->file->tmp('path'), $this->file->pathInfo());
            return count($names) > 0;
        }
        if (!move_uploaded_file($this->file->tmp(), $this->file->namePath())) {
            throw new Exceptions('The' . $this->file->namePath(true) . 'file cannot be moved.', 404);
        }
        return true;
    }

    /**
     * @return bool
     */
    public function exists(): bool
    {
        return $this->file->exists();
    }

    /**
     * @param bool $folder
     * @return bool
     */
    public function delete(bool $folder = false): bool
    {
        return $this->file->delete($folder);
    }

    /**
     * @param array $positions
     * @param int $width
     * @param int $height
     * @param string $path
     * @param bool $rotation
     * @return $this
     */
    public function cut(array $positions, int $width, int $height, string $path, bool $rotation = false): Upload
    {
        $this->file->resize($this->file->calculating($width, $height), [$path], 'cut');
        return $this;
    }

    /**
     * @param string $imageTmp
     * @param string $path
     * @return $this
     * @throws Exceptions
     */
    public function rotation(string $imageTmp, string $path): Upload
    {
        if (($this->file->tmp('type') ?? mime_content_type($this->filename())) === 'image/png') {
            $this->file->convertFromPngToJpg($imageTmp, $path)->pathFile($path);
            return $this;
        }
        [$width, $height] = $this->file->size($imageTmp);
        $this->file->resize($this->file->calculating($width, $height, true), [$imageTmp, $path], 'rotation');
        return $this;
    }

    /**
     * @return string
     */
    public function filename(): string
    {
        return $this->file->pathInfo();
    }
}
