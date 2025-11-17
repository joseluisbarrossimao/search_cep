<?php

declare(strict_types=1);

namespace Search\Filesystem;

use GdImage;
use Search\Container\Instances;
use Search\Error\Exceptions;

/**
 *
 */
class Image extends File
{
    /**
     * @var array
     */
    protected array $imageMime = ['image/jpeg' => 'jpeg', 'image/png' => 'png'];

    /**
     * @var GdImage|null
     */
    private ?GdImage $image = null;

    /**
     * @param Instances $instance
     * @param string $file
     * @param array|null $arq
     * @throws Exceptions
     */
    public function __construct(Instances $instance, string $file, ?array $arq = null)
    {
        if (in_array($this->mimetype($file), array_keys($this->imageMime)) === false) {
            throw new Exceptions(
                "This image has no accepted extension. Extensions accepted: JPG, JPEG or PNG.",
                404,
            );
        }
        if (isset($arq)) {
            parent::__construct($instance, $file, $arq);
        }
        parent::__construct($instance, $file);
    }

    /**
     * @param string $sizes
     * @param string $imageTmp
     * @return $this
     */
    public function createDifferentSizes(string $sizes, string $imageTmp): Image
    {
        $size = explode('x', $sizes);
        $size[0] = (int)$size[0];
        $size[1] = (int)$size[1];
        $this->resize(
            $this->calculating($size[0], $size[1]),
            [$imageTmp],
            'ico',
        );
        return $this;
    }

    /**
     * @param array $size
     * @param array $paths
     * @param string $type
     * @return $this
     */
    public function resize(array $size, array $paths, string $type = 'other'): Image
    {
        if ($this->image === null) {
            $this->image = $this->createImage($this->extension, $paths[0]);
        }
        if ($type === 'rotation') {
            $this->rotation($paths[0]);
        }
        $thumb = $this->createImageNew($size);
        $path = $paths[1] ?? $paths[0];
        if ($type === 'cut') {
            $path = str_replace("/", '/cut', str_replace('../', '', $path));
        }
        if ($type === 'ico') {
            $file = pathinfo($path);
            $file['basename'] = substr(
                $file['basename'],
                0,
                stripos($file['basename'], '.'),
            ) . '_' . $size['dst_w'] . 'x' . $size['dst_h'] . '.' . $file['extension'];
            $path = $file['dirname'] . DS . $file['basename'];
        }
        if (!$this->folder->exists()) {
            $this->folder->create();
        }
        $this->image = $this->createImage($this->extension, $path, $thumb);
        return $this;
    }

    /**
     * @param string $type
     * @param string $path
     * @param $thumb
     * @return GdImage|bool
     */
    private function createImage(string $type, string $path, $thumb = ''): GdImage|bool
    {
        if ($thumb !== '') {
            if ($type === 'png') {
                return imagepng($thumb, $path);
            }
            return imagejpeg($thumb, $path);
        }
        if ($type === 'png') {
            return imagecreatefrompng($path);
        }
        return imagecreatefromjpeg($path);
    }

    /**
     * @param string $tmp_name
     * @return void
     */
    private function rotation(string $tmp_name): void
    {
        if (isset(exif_read_data($tmp_name)['Orientation'])) {
            switch (exif_read_data($tmp_name)['Orientation']) {
                case 1:
                case 2:
                    $this->image = imagerotate($this->image, 0, 0);
                    break;
                case 3:
                case 4:
                    $this->image = imagerotate($this->image, 180, 0);
                    break;
                case 5:
                case 6:
                    $this->image = imagerotate($this->image, -90, 0);
                    break;
                case 8:
                case 7:
                    $this->image = imagerotate($this->image, 90, 0);
                    break;
            }
        }
    }

    /**
     * @param array $size
     * @return GdImage
     */
    private function createImageNew(array $size): GdImage
    {
        $thumb = imagecreatetruecolor($size['dst_w'], $size['dst_h']);
        imagealphablending($thumb, false);
        imagesavealpha($thumb, true);
        imagecopyresampled(
            $thumb,
            $this->image,
            $size['dst_x'],
            $size['dst_y'],
            $size['src_x'],
            $size['src_y'],
            $size['dst_w'],
            $size['dst_h'],
            $size['src_w'],
            $size['src_h'],
        );
        return $thumb;
    }

    /**
     * @param int $width
     * @param int $height
     * @param bool $tmp
     * @return int[]
     */
    public function calculating(int $width, int $height, bool $tmp = false): array
    {
        $path = $this->pathInfo();
        if ($tmp) {
            $path = $this->tmp();
        }
        [$srcw, $srch] = getimagesize($path);
        $srcx = 0;
        $srcy = 0;
        $imgx = $srcx / $width;
        $imgy = $srcy / $height;
        if ($imgx > $imgy) {
            $srcx = round($width - (($width / $imgx) * $imgy));
            $srcw = round(($width / $imgx) * $imgy);
        } elseif ($imgy > $imgx) {
            $srch = round(($height / $imgy) * $imgx);
            $srcy = round($height - (($height / $imgy) * $imgx));
        }
        return [
            'dst_x' => 0,
            'dst_y' => 0,
            'src_x' => (int)$srcx,
            'src_y' => (int)$srcy,
            'dst_w' => $width,
            'dst_h' => $height,
            'src_w' => (int)$srcw,
            'src_h' => (int)$srch,
        ];
    }

    /**
     * @param string $imageTmp
     * @param string $path
     * @return $this
     */
    public function convertFromPngToJpg(string $imageTmp, string $path): Image
    {
        $this->createImage($this->extension, $imageTmp);
        [$width, $height] = $this->size($imageTmp);
        $thumb = $this->createImageNew($this->calculating($width, $height));
        $this->createImage('jpg', $path, $thumb);
        $this->destroyImages([$this->image, $thumb]);
        return $this;
    }

    /**
     * @param string $imageTmp
     * @return array
     * @throws Exceptions
     */
    public function size(string $imageTmp): array
    {
        $this->image = $this->createImage($this->extension, $imageTmp);
        if ($this->image === null && !($this->image instanceof GdImage)) {
            throw new Exceptions('The image is not valid or could not be created.', 500);
        }
        return [imagesx($this->image), imagesy($this->image)];
    }

    /**
     * @param array $images
     * @param bool $resource
     * @return void
     */
    private function destroyImages(array $images, bool $resource = true): void
    {
        if ($resource) {
            foreach ($images as $image) {
                imagedestroy($image);
            }
        }
        foreach ($images as $image) {
            unlink($image);
        }
    }
}
