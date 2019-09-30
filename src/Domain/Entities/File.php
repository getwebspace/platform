<?php

namespace App\Domain\Entities;

use AEngine\Entity\Model;
use AEngine\Support\Str;
use DateTime;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;

/**
 * @ORM\Entity
 * @ORM\Table(name="file")
 */
class File extends Model
{
    /**
     * @var Uuid
     * @ORM\Id
     * @ORM\Column(type="uuid")
     * @ORM\GeneratedValue(strategy="CUSTOM")
     * @ORM\CustomIdGenerator(class="Ramsey\Uuid\Doctrine\UuidGenerator")
     */
    public $uuid;

    /**
     * @ORM\Column(type="string")
     */
    public $name;

    /**
     * @ORM\Column(type="string", nullable=true)
     */
    public $ext;

    /**
     * @ORM\Column(type="string")
     */
    public $type;

    /**
     * @ORM\Column(type="integer")
     */
    public $size;

    /**
     * @ORM\Column(type="string")
     */
    public $salt;

    /**
     * @ORM\Column(type="string")
     */
    public $hash;

    /**
     * @ORM\Column(type="FileItemType", nullable=true)
     */
    public $item;

    /**
     * @ORM\Column(type="uuid", options={"default": NULL})
     */
    public $item_uuid;

    /**
     * @ORM\Column(type="boolean")
     */
    public $private = false;

    /**
     * @var DateTime
     * @ORM\Column(type="datetime", options={"default": "CURRENT_TIMESTAMP"})
     */
    public $date;

    public static function getFromPath(string $path, string $name_with_ext = null): File
    {
        \RunTracy\Helpers\Profiler\Profiler::start('file:getFromPath (%s)', $path);

        $salt = uniqid();
        $info = pathinfo($name_with_ext ?? $path);
        $dir = UPLOAD_DIR . '/' . $salt;
        $name = \AEngine\Support\Str::translate(strtolower($info['filename']));
        $ext = strtolower($info['extension']);

        // change ext
        if (!in_array($ext, ['jpg', 'jpeg', 'png'])) {
            $ext = 'jpg';
        }

        $saved = false;

        switch (true) {
            case Str::start(['http://', 'https://'], $path) === true:
                $entities = ['%20', '%21', '%2A', '%27', '%28', '%29', '%3B', '%3A', '%40', '%26', '%3D', '%2B', '%24', '%2C', '%2F', '%3F', '%25', '%23', '%5B', '%5D'];
                $replacements = [' ', '!', '*', "'", "(", ")", ";", ":", "@", "&", "=", "+", "$", ",", "/", "?", "%", "#", "[", "]"];
                $path = str_replace($entities, $replacements, urlencode($path));

                $headers = get_headers($path);
                $code = substr($headers[0], 9, 3);

                if ($code == 200) {
                    $file = @file_get_contents($path);

                    if ($file) {
                        if (!file_exists($dir)) {
                            mkdir($dir, 0777, true);
                        }
                        $path = $dir . '/' . $name . '.' . $ext;
                        $saved = file_put_contents($path, $file);
                    }
                }
                break;
            default:
                $file = @file_get_contents($path);

                if ($file) {
                    if (!file_exists($dir)) {
                        mkdir($dir, 0777, true);
                    }
                    $path = $dir . '/' . $name . '.' . $ext;
                    $saved = file_put_contents($path, $file);
                }
                break;
        }

        if ($saved) {
            $model = new static([
                'name' => $name,
                'ext' => $ext,
                'type' => addslashes(exec('file -bi ' . $path)),
                'size' => filesize($path),
                'salt' => $salt,
                'hash' => sha1_file($path),
                'date' => new \DateTime(),
            ]);
        }

        \RunTracy\Helpers\Profiler\Profiler::finish('file:getFromPath (%s)', $path);

        return $model ?? null;
    }

    /**
     * File details by path
     *
     * @param $path
     *
     * @return array
     * @throws \RunTracy\Helpers\Profiler\Exception\ProfilerException
     */
    public static function info($path): array
    {
        \RunTracy\Helpers\Profiler\Profiler::start('file:info (%s)', $path);

        $info = pathinfo($path);
        $result = [
            'dir' => $info['dirname'],
            'name' => $info['filename'],
            'ext' => $info['extension'],
            'type' => addslashes(exec('file -bi ' . $path)),
            'size' => filesize($path),
            'hash' => sha1_file($path),
        ];

        \RunTracy\Helpers\Profiler\Profiler::finish('file:info (%s)', $path);

        return $result;
    }

    /**
     * @return bool|resource
     */
    public function getResource() {
        return fopen(
            $this->getInternalPath(),
            'rb'
        );
    }

    /**
     * Formatted file size
     *
     * @return string
     */
    public function getSize()
    {
        return str_convert_size($this->size);
    }

    /**
     * File name with extension
     *
     * @return string
     */
    public function getName()
    {
        return $this->name . '.' . $this->ext;
    }

    /**
     * Valid size correct and check exist file
     *
     * @param string $size
     *
     * @return bool
     */
    protected function isValidSizeAndFileExists(string $size): bool
    {
        if (in_array($size, ['full', 'middle', 'small'])) {
            return file_exists(UPLOAD_DIR . '/' . $this->salt . '/' . $size . '/' . $this->getName());
        }

        return false;
    }

    /**
     * Return file path
     *
     * @param string|null $size
     *
     * @return string
     */
    public function getDir(string $size = 'full')
    {
        return UPLOAD_DIR . '/' . $this->salt . ($this->isValidSizeAndFileExists($size) ? '/' . $size : '');
    }

    /**
     * Return file path
     *
     * @param string|null $size
     *
     * @return string
     */
    public function getInternalPath(string $size = 'full')
    {
        return $this->getDir($size) . '/' . $this->getName();
    }

    /**
     * Return public path with salt and hash
     *
     * @return string
     */
    public function getPublicPath(string $size = 'full')
    {
        if ($this->private) {
            return '/file/get/' . $this->salt . '/' . $this->hash . ($this->isValidSizeAndFileExists($size) ? '/' . $size : '');
        }

        return '/uploads/' . $this->salt . ($this->isValidSizeAndFileExists($size) ? '/' . $size : '') . '/' . $this->getName();
    }
}
