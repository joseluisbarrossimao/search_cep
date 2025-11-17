<?php

declare(strict_types=1);

namespace Search\Filesystem;

use Search\Container\Instances;
use Search\Error\Exceptions;
use SplFileObject;

/**
 *
 */
class File
{
    /**
     * @var string
     */
    public string $name;

    /**
     * @var string
     */
    public string $extension;

    /**
     * @var object
     */
    protected object $folder;

    /**
     * @var Instances
     */
    protected Instances $instance;

    /**
     * @var string
     */
    protected string $file;

    /**
     * @var SplFileObject|null
     */
    private ?SplFileObject $handle = null;

    /**
     * @var array
     */
    private array $datas = [];

    /**
     * @var array
     */
    private array $tmp;

    /**
     * @param Instances $instance
     * @param string $file
     * @param array $arq
     * @throws Exceptions
     */
    public function __construct(Instances $instance, string $file, array $arq = [])
    {
        $path = pathinfo($file);
        $this->folder = $instance->resolveClass(
            Search_NAMESPACE[0] . DS_REVERSE . 'Filesystem' . DS_REVERSE . 'Folder',
            ['folder' => $path['dirname']],
        );
        $this->file = $path['basename'];
        if (isset($path['extension'])) {
            $this->extension = $path['extension'];
        }
        if (count($arq) > 0 && !isset($this->tmp['tmp_name'])) {
            $this->tmp = $arq;
        }
        $this->instance = $instance;
    }

    /**
     * @param string $path
     * @param bool $deleteTmp
     * @return $this
     */
    public function pathFile(string $path, bool $deleteTmp = true): File
    {
        if ($path !== $this->pathInfo()) {
            $path = pathinfo($path);
            $this->folder->pathFolder($path['dirname']);
            $this->file = $path['basename'];
            $this->extension = $path['extension'];
            if ($deleteTmp && ($this->tmp !== null && count($this->tmp) > 0)) {
                unset($this->tmp);
            }
        }
        return $this;
    }

    /**
     * @return string
     */
    public function pathInfo(): string
    {
        return $this->folder->path() . DS . $this->file;
    }

    /**
     * @param bool $deleteFolder
     * @return bool
     */
    public function delete(bool $deleteFolder = false): bool
    {
        $path = $this->folder->path();
        if (
            $this->exists() && is_file(
                $path . DS . $this->file
            ) && $this->handle instanceof SplFileObject
        ) {
            $path = stripos($_SERVER['WINDIR'], 'WINDOWS') !== false ? str_replace(
                DS,
                DS_REVERSE,
                $path,
            ) . DS_REVERSE : $path . DS;
            $file = $path . $this->file;
            unlink($file);
        }
        if ($deleteFolder && count($this->folder->read($path)['files']) === 0) {
            $this->folder->delete($path);
        }
        return $this->exists();
    }

    /**
     * @param string $path
     * @return bool
     */
    public function exists(string $path = ''): bool
    {
        if ($path !== '') {
            return file_exists($path);
        }
        return file_exists($this->folder->path() . DS . $this->file);
    }

    /**
     * @param bool $count
     * @param string $mode
     * @return array[]
     * @throws Exceptions
     */
    public function read(bool $count = false, string $mode = 'r+'): array
    {
        if ($this->handle === null) {
            $this->create($mode);
        }
        $reading = [];
        if ($this->exists()) {
            while ($read = $this->handle->fgets()) {
                $reading[] = $read;
            }
            $this->close();
        }
        $read = ['content' => $reading];
        if ($count) {
            $read = array_merge($read, ['count' => count($reading) - 1]);
        }
        return $read;
    }

    /**
     * @param string $mode
     * @return $this
     * @throws Exceptions
     */
    public function create(string $mode): File
    {
        if (!$this->folder->exists()) {
            if (!$this->folder->create()) {
                throw new Exceptions(sprintf('this %s Folder not created.', $this->folder->path()), 404);
            }
        }
        if ($this->handle === null || $this->handle === false) {
            if (str_starts_with($mode, 'r')) {
                if ($this->exists()) {
                    $this->handle = new SplFileObject($this->folder->path() . DS . $this->file, $mode);
                }
            } else {
                $this->handle = new SplFileObject($this->folder->path() . DS . $this->file, $mode);
            }
        }
        return $this;
    }

    /**
     * @return $this
     */
    public function close(): File
    {
        $this->handle = null;
        return $this;
    }

    /**
     * @param mixed $datas
     * @param string $mode
     * @param bool $close
     * @return bool
     * @throws Exceptions
     */
    public function write(mixed $datas, string $mode = 'w+', bool $close = true): bool
    {
        $this->create($mode);
        $this->datas = is_array($datas) ? (count($this->datas) > 0 ? array_merge(
            $this->datas,
            $datas
        ) : $datas) : (count($this->datas) > 0 ? array_merge($this->datas, [$datas]) : [$datas]);
        if ($close) {
            foreach ($this->datas as $data) {
                $successes[] = $this->handle->fwrite($data) !== false ? 'sucesso' : 'fracasso';
            }
            $success = in_array('fracasso', $successes) === false;
            $this->close();
            $this->datas = [];
            return $success;
        }
        return true;
    }

    /**
     * @param bool $nameAlone
     * @return string
     */
    public function namePath(bool $nameAlone = false): string
    {
        $file = $this->pathInfo();
        if ($nameAlone) {
            return substr($file, strripos($file, DS));
        }
        return $file;
    }

    /**
     * @param string $path
     * @param string $cut
     * @return string
     */
    public function move(string $path, string $cut = ''): string
    {
        rename($this->pathInfo(), $path);
        return substr($path, stripos($path, $cut) + strlen($cut));
    }

    /**
     * @param string $key
     * @return string
     */
    public function tmp(string $key = 'tmp_name'): string
    {
        return $this->tmp[$key];
    }

    /**
     * @return Folder
     */
    public function folder(): Folder
    {
        return $this->folder;
    }

    /**
     * @return SplFileObject
     */
    public function handle(): SplFileObject
    {
        return $this->handle;
    }

    /**
     * @param string $pathinfo
     * @return string
     */
    public function mimetype(string $pathinfo = ''): string
    {
        if ($pathinfo !== '') {
            $mimetype = mime_content_type($pathinfo);
            if ($mimetype === false) {
                return '';
            }
            return $mimetype;
        }
        $mimetype = mime_content_type($this->pathInfo());
        if ($mimetype === false) {
            return '';
        }
        return $mimetype;
    }

    /**
     * @return string
     */
    public function nameFile(): string
    {
        return $this->file;
    }
}
