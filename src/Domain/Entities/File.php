<?php declare(strict_types=1);

namespace App\Domain\Entities;

use App\Application\i18n;
use App\Domain\AbstractEntity;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: 'file')]
#[ORM\Entity(repositoryClass: 'App\Domain\Repository\FileRepository')]
class File extends AbstractEntity
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'Ramsey\Uuid\Doctrine\UuidGenerator')]
    protected \Ramsey\Uuid\UuidInterface $uuid;

    public function getUuid(): \Ramsey\Uuid\UuidInterface
    {
        return $this->uuid;
    }

    #[ORM\Column(type: 'string', options: ['default' => ''])]
    protected string $name = '';

    /**
     * @return $this
     */
    public function setName(string $name)
    {
        if ($this->checkStrLenMax($name, 255) && $this->validText($name)) {
            $this->name = $name;
        }

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    #[ORM\Column(type: 'string', options: ['default' => ''])]
    protected string $ext = '';

    /**
     * @return $this
     */
    public function setExt(string $ext)
    {
        if ($this->checkStrLenMax($ext, 32)) {
            $this->ext = $ext;
        }

        return $this;
    }

    public function getExt(): string
    {
        return $this->ext;
    }

    #[ORM\Column(type: 'string', options: ['default' => ''])]
    protected string $type = '';

    /**
     * @return $this
     */
    public function setType(string $type)
    {
        if ($this->checkStrLenMax($type, 255)) {
            $this->type = $type;
        }

        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    protected int $size = 0;

    /**
     * @return $this
     */
    public function setSize(int $size)
    {
        $this->size = $size;

        return $this;
    }

    public function getSize(): int
    {
        return $this->size;
    }

    #[ORM\Column(type: 'string', options: ['default' => ''])]
    protected string $salt = '';

    /**
     * @return $this
     */
    public function setSalt(string $salt)
    {
        if ($this->checkStrLenMax($salt, 50)) {
            $this->salt = $salt;
        }

        return $this;
    }

    public function getSalt(): string
    {
        return $this->salt;
    }

    #[ORM\Column(type: 'string', options: ['default' => ''])]
    protected string $hash = '';

    /**
     * @return $this
     */
    public function setHash(string $hash)
    {
        if ($this->checkStrLenMax($hash, 50)) {
            $this->hash = $hash;
        }

        return $this;
    }

    public function getHash(): string
    {
        return $this->hash;
    }

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    protected bool $private = false;

    /**
     * @param mixed $value
     *
     * @return $this
     */
    public function setPrivate($value)
    {
        $this->private = $this->getBooleanByValue($value);

        return $this;
    }

    public function getPrivate(): bool
    {
        return $this->private;
    }

    #[ORM\Column(type: 'datetime', options: ['default' => 'CURRENT_TIMESTAMP'])]
    protected \DateTime $date;

    /**
     * @param mixed $timezone
     * @param mixed $date
     *
     * @throws \Exception
     *
     * @return $this
     */
    public function setDate($date, $timezone = 'UTC')
    {
        $this->date = $this->getDateTimeByValue($date, $timezone);

        return $this;
    }

    /**
     * @return \DateTime
     */
    public function getDate()
    {
        return $this->date;
    }

    /**
     * File details by path
     *
     * @param mixed $path
     */
    public static function info($path): array
    {
        $info = pathinfo($path);

        return [
            'dir' => $info['dirname'],
            'name' => isset($info['filename']) ? static::prepareName($info['filename']) : '',
            'ext' => isset($info['extension']) ? mb_strtolower($info['extension']) : '',
            'type' => addslashes(@exec('file -bi "' . $path . '"')),
            'size' => filesize($path),
            'hash' => sha1_file($path),
        ];
    }

    public static function prepareName($name)
    {
        $replacements = [
            '!', '*', "'",
            '(', ')', ';',
            ':', '@', '&',
            '=', '+', '$',
            ',', '/', '?',
            '%', '#', '[',
            ']',
        ];

        $name = urldecode($name);
        $name = str_replace(' ', '_', $name);
        $name = str_replace($replacements, '', $name);
        $name = i18n::getTranslatedText($name);

        return mb_strtolower($name);
    }

    /**
     * @param string $mode
     *
     * @return bool|resource
     */
    public function getResource($mode = 'rb')
    {
        return fopen($this->getInternalPath(), $mode);
    }

    /**
     * File name with extension
     *
     * @return string
     */
    public function getFileName()
    {
        return $this->name . ($this->ext ? '.' . $this->ext : '');
    }

    /**
     * Formatted file size
     *
     * @return string
     */
    public function getFileSize()
    {
        return str_convert_size($this->size);
    }

    /**
     * Valid size correct and check exist file
     */
    protected function isValidSizeAndFileExists(string $size): bool
    {
        if (in_array($size, ['middle', 'small'], true)) {
            return file_exists(UPLOAD_DIR . '/' . $this->salt . '/' . $size . '/' . $this->getFileName());
        }

        return false;
    }

    /**
     * Return file path
     *
     * @param null|string $size
     *
     * @return string
     */
    public function getDir(string $size = '')
    {
        return UPLOAD_DIR . '/' . $this->salt . ($size && $this->isValidSizeAndFileExists($size) ? '/' . $size : '');
    }

    /**
     * Return file path
     *
     * @param null|string $size
     *
     * @return string
     */
    public function getInternalPath(string $size = '')
    {
        return $this->getDir($size) . '/' . $this->getFileName();
    }

    /**
     * Return public path with salt and hash
     *
     * @return string
     */
    public function getPublicPath(string $size = '')
    {
        static $buf;

        $uuid = $this->uuid->toString();

        if (!isset($buf[$uuid][$size])) {
            if ($this->private) {
                if (str_starts_with($this->type, 'image/')) {
                    $buf[$uuid][$size] = '/file/view/';
                } else {
                    $buf[$uuid][$size] = '/file/get/';
                }

                $buf[$uuid][$size] .= $this->salt . '/' . $this->hash . ($size && $this->isValidSizeAndFileExists($size) ? '/' . $size : '');
            } else {
                $buf[$uuid][$size] = '/uploads/' . $this->salt . ($size && $this->isValidSizeAndFileExists($size) ? '/' . $size : '') . '/' . $this->getFileName();
            }
        }

        return $buf[$uuid][$size];
    }

    public function toArray(): array
    {
        $result = [
            'name' => $this->getFileName(),
            'size' => $this->getSize(),
            'link' => $this->getPublicPath(),
        ];

        if (str_starts_with($this->getType(), 'image/')) {
            $result['path'] = [];

            foreach (['full', 'middle', 'small'] as $size) {
                $result['path'][$size] = $this->getPublicPath($size);
            }
        }

        return array_serialize($result);
    }
}
