<?php

namespace App\Domain\Entities;

use AEngine\Entity\Model;
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

    /**
     * File details by path
     *
     * @param $path
     *
     * @return array
     * @throws \RunTracy\Helpers\Profiler\Exception\ProfilerException
     */
    public static function info($path)
    {
        \RunTracy\Helpers\Profiler\Profiler::start('file:info (%s)', $path);

        $info = pathinfo($path);
        $result = [
            'dir' => $info['dirname'],
            'name' => \AEngine\Support\Str::translate(strtolower($info['filename'])),
            'ext' => strtolower($info['extension']),
            'path' => $path,
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
     * Return file path
     *
     * @param string|null $size
     *
     * @return string
     */
    public function getInternalFolder(string $size = null)
    {
        return UPLOAD_DIR . '/' . $this->salt . ($size ? '/' . $size : '');
    }

    /**
     * Return file path
     *
     * @param string|null $size
     *
     * @return string
     */
    public function getInternalPath(string $size = null)
    {
        return UPLOAD_DIR . '/' . $this->salt . ($size ? '/' . $size : '') . '/' . $this->name . '.' . $this->ext;
    }

    /**
     * Return public path with salt and hash
     *
     * @return string
     */
    public function getPublicPath(string $size = null)
    {
        if ($this->private) {
            return '/file/get/' . $this->salt . '/' . $this->hash . ($size ? '/' . $size : '');
        }

        return '/uploads/' . $this->salt . ($size ? '/' . $size : '') . '/' . $this->name . '.' . $this->ext;
    }
}
