<?php

namespace Domain\Entities;

use AEngine\Entity\Model;
use AEngine\Support\Str;
use DateTime;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\UuidInterface;
use Slim\Http\UploadedFile;

/**
 * @ORM\Entity
 * @ORM\Table(name="file")
 */
class File extends Model
{
    /**
     * @var UuidInterface
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
     * @ORM\Column(type="uuid", options={"default": \Ramsey\Uuid\Uuid::NIL})
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
     * @return bool|resource
     */
    public function getResource() {
        return fopen(
            $this->getInternalPath(),
            'rb'
        );
    }

    /**
     * Formated file size
     *
     * @return string
     */
    public function getSize()
    {
        return str_convert_size($this->size);
    }

    /**
     * Return file path
     *
     * @return string
     */
    public function getInternalPath()
    {
        return UPLOAD_DIR . '/' . $this->salt . '/' . $this->name;
    }

    /**
     * Return public path with salt and hash
     *
     * @return string
     */
    public function getPublicPath()
    {
        if ($this->private) {
            return '/file/get/' . $this->salt . '/' . $this->hash;
        }

        return '/uploads/' . $this->salt . '/' . $this->name;
    }
}
