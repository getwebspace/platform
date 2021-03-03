<?php declare(strict_types=1);

namespace App\Domain\Entities;

use App\Domain\AbstractEntity;
use DateTime;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;

/**
 * @ORM\Entity
 * @ORM\Table(name="file")
 */
class File extends AbstractEntity
{
    /**
     * @var Uuid
     * @ORM\Id
     * @ORM\Column(type="uuid")
     * @ORM\GeneratedValue(strategy="CUSTOM")
     * @ORM\CustomIdGenerator(class="Ramsey\Uuid\Doctrine\UuidGenerator")
     */
    protected Uuid $uuid;

    /**
     * @return Uuid
     */
    public function getUuid(): Uuid
    {
        return $this->uuid;
    }

    /**
     * @ORM\Column(type="string", options={"default": ""})
     */
    protected string $name = '';

    /**
     * @param string $name
     *
     * @return $this
     */
    public function setName(string $name)
    {
        if ($this->checkStrLenMax($name, 255)) {
            $this->name = $name;
        }

        return $this;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @ORM\Column(type="string", options={"default": ""})
     */
    protected string $ext = '';

    /**
     * @param string $ext
     *
     * @return $this
     */
    public function setExt(string $ext)
    {
        if ($this->checkStrLenMax($ext, 32)) {
            $this->ext = $ext;
        }

        return $this;
    }

    /**
     * @return string
     */
    public function getExt(): string
    {
        return $this->ext;
    }

    /**
     * @ORM\Column(type="string", options={"default": ""})
     */
    protected string $type = '';

    /**
     * @param string $type
     *
     * @return $this
     */
    public function setType(string $type)
    {
        if ($this->checkStrLenMax($type, 255)) {
            $this->type = $type;
        }

        return $this;
    }

    /**
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @ORM\Column(type="integer", options={"default": 0})
     */
    protected int $size = 0;

    /**
     * @param int $size
     *
     * @return $this
     */
    public function setSize(int $size)
    {
        $this->size = $size;

        return $this;
    }

    /**
     * @return string
     */
    public function getSize(): int
    {
        return $this->size;
    }

    /**
     * @ORM\Column(type="string", options={"default": ""})
     */
    protected string $salt = '';

    /**
     * @param string $salt
     *
     * @return $this
     */
    public function setSalt(string $salt)
    {
        if ($this->checkStrLenMax($salt, 50)) {
            $this->salt = $salt;
        }

        return $this;
    }

    /**
     * @return string
     */
    public function getSalt(): string
    {
        return $this->salt;
    }

    /**
     * @ORM\Column(type="string", options={"default": ""})
     */
    protected string $hash = '';

    /**
     * @param string $hash
     *
     * @return $this
     */
    public function setHash(string $hash)
    {
        if ($this->checkStrLenMax($hash, 50)) {
            $this->hash = $hash;
        }

        return $this;
    }

    /**
     * @return string
     */
    public function getHash(): string
    {
        return $this->hash;
    }

    /**
     * @ORM\Column(type="boolean", options={"default": false})
     */
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

    /**
     * @return bool
     */
    public function getPrivate(): bool
    {
        return $this->private;
    }

    /**
     * @var DateTime
     * @ORM\Column(type="datetime", options={"default": "CURRENT_TIMESTAMP"})
     */
    protected DateTime $date;

    /**
     * @param $date
     *
     * @throws \Exception
     *
     * @return $this
     */
    public function setDate($date)
    {
        $this->date = $this->getDateTimeByValue($date);

        return $this;
    }

    /**
     * @return DateTime
     */
    public function getDate()
    {
        return $this->date;
    }

    /**
     * File details by path
     *
     * @param $path
     *
     * @throws \RunTracy\Helpers\Profiler\Exception\ProfilerException
     *
     * @return array
     */
    public static function info($path): array
    {
        \RunTracy\Helpers\Profiler\Profiler::start('file:info (%s)', $path);

        $info = pathinfo($path);
        $result = [
            'dir' => $info['dirname'],
            'name' => isset($info['filename']) ? static::prepareName($info['filename']) : '',
            'ext' => isset($info['extension']) ? mb_strtolower($info['extension']) : '',
            'type' => addslashes(@exec('file -bi "' . $path . '"')),
            'size' => filesize($path),
            'hash' => sha1_file($path),
        ];

        \RunTracy\Helpers\Profiler\Profiler::finish('file:info (%s)', $path);

        return $result;
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
        $name = str_translate($name);
        $name = mb_strtolower($name);

        return $name;
    }

    /**
     * @return bool|resource
     */
    public function getResource()
    {
        return fopen($this->getInternalPath(), 'rb');
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
     *
     * @param string $size
     *
     * @return bool
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
     * @param string $size
     *
     * @throws \RunTracy\Helpers\Profiler\Exception\ProfilerException
     *
     * @return string
     */
    public function getPublicPath(string $size = '')
    {
        static $buf;

        $uuid = $this->uuid->toString();

        if (!isset($buf[$uuid][$size])) {
            \RunTracy\Helpers\Profiler\Profiler::start('file:getPublicPath (%s)', $size);

            if ($this->private) {
                $buf[$uuid][$size] = '/file/get/' . $this->salt . '/' . $this->hash . ($size && $this->isValidSizeAndFileExists($size) ? '/' . $size : '');
            } else {
                $buf[$uuid][$size] = '/uploads/' . $this->salt . ($size && $this->isValidSizeAndFileExists($size) ? '/' . $size : '') . '/' . $this->getFileName();
            }

            \RunTracy\Helpers\Profiler\Profiler::finish('file:getPublicPath (%s)', $size, ['uuid' => $uuid]);
        }

        return $buf[$uuid][$size];
    }

    public function toArray(): array
    {
        $result = [
            'name' => $this->getFileName(),
            'size' => $this->getSize(),
            'path' => [],
        ];

        foreach (['full', 'middle', 'small'] as $size) {
            $result['path'][$size] = $this->getPublicPath($size);
        }

        return $result;
    }
}
