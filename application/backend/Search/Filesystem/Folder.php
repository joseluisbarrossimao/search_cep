<?php

declare(strict_types=1);

namespace Search\Filesystem;

use DirectoryIterator;

/**
 *
 */
class Folder
{
    /**
     * @var string
     */
    private string $path;

    /**
     * @var array
     */
    private array $info;

    /**
     * @param string|null $folder
     */
    public function __construct(?string $folder = null)
    {
        if (!isset($folder)) {
            $this->path = ROOT;
        } else {
            $this->path = $folder;
            $this->pathFolder($folder);
        }
    }

    /**
     * @param string $folder
     * @return $this
     */
    public function pathFolder(string $folder): Folder
    {
        if ($this->path != $folder) {
            $this->path = $folder;
            if (
                stripos(
                    $folder,
                    substr(substr(ROOT, 0, -1), strripos(substr(ROOT, 0, -1), (string)DS_REVERSE) + 1),
                ) === false
            ) {
                $path = '';
                if (str_starts_with($folder, 'Search')) {
                    $path = ROOT_NAMESPACE;
                    $folder = substr($folder, strlen('Search') + 1);
                }
                $this->path = $path . $folder;
            }
        }
        return $this;
    }

    /**
     * @param string $path
     * @param int $mode
     * @return bool
     */
    public function create(string $path = '', int $mode = 0755): bool
    {
        $path = $path === '' ? $this->path : $this->path . DS . $path;
        if ($this->exists($path)) {
            return true;
        }
        $this->info($path);
        if ($this->create($this->info['dirname'])) {
            $old = umask(0);
            $success = mkdir(
                $this->info['dirname'] . DS . $this->info['basename'],
                $mode,
                true,
            );
            umask($old);
            return $success;
        }
        return false;
    }

    /**
     * @param string|null $path
     * @return bool
     */
    public function exists(?string $path = ''): bool
    {
        if ($path !== '') {
            return file_exists($path);
        }
        return file_exists($this->path);
    }

    /**
     * @param string $path
     * @return $this
     */
    public function info(string $path): Folder
    {
        $this->info = pathinfo($path);
        return $this;
    }

    /**
     * @return string
     */
    public function path(): string
    {
        return $this->path;
    }

    /**
     * @param string $path
     * @return $this
     */
    public function delete(string $path = ''): Folder
    {
        $path = $path === '' ? $this->path : $this->path . DS . $path;
        $filesDirs = $this->read($path);
        $this->info($path);
        if (count($filesDirs['directories']) === 0 && count($filesDirs['files']) === 0) {
            rmdir($this->info['filename']);
        }
        if ($this->exists()) {
            if (isset($filesDirs['directories'])) {
                foreach ($filesDirs['directories'] as $fileDir) {
                    $this->delete($fileDir);
                }
            }
            if (count($filesDirs['files']) > 0) {
                foreach ($filesDirs['files'] as $fileDir) {
                    $this->delete($fileDir);
                }
            }
            if (count($filesDirs['directories']) === 0 && count($filesDirs['files']) === 0) {
                rmdir($this->info['filename']);
            }
        }
        return $this;
    }

    /**
     * @param string $path
     * @return array|array[]
     */
    public function read(string $path = ''): array
    {
        $datas = ['directories' => [], 'files' => []];
        $path = $path === '' ? $this->path : $this->path . DS . $path;
        foreach (new DirectoryIterator($path) as $item) {
            if ($item->isDot()) {
                continue;
            }
            $read = $item->getFilename();
            if ($read != 'empty') {
                $datas[$item->isDir() ? 'directories' : 'files'][] = $read;
            }
        }
        return $datas;
    }
}
