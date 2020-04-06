<?php declare(strict_types=1);

namespace App\Domain\Entities;

use Alksily\Entity\Model;
use Alksily\Support\Str;
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
     * @ORM\Column(type="string", options={"default": ""})
     */
    public $name = '';

    /**
     * @ORM\Column(type="string", options={"default": ""})
     */
    public $ext = '';

    /**
     * @ORM\Column(type="string", options={"default": ""})
     */
    public $type = '';

    /**
     * @ORM\Column(type="integer", options={"default": 0})
     */
    public $size = 0;

    /**
     * @ORM\Column(type="string", options={"default": ""})
     */
    public $salt = '';

    /**
     * @ORM\Column(type="string", options={"default": ""})
     */
    public $hash = '';

    /**
     * @ORM\Column(type="boolean")
     */
    public $private = false;

    /**
     * @var DateTime
     * @ORM\Column(type="datetime", options={"default": "CURRENT_TIMESTAMP"})
     */
    public $date = '';

    protected static function prepareFileName($name)
    {
        $entities = ['%20', '%21', '%2A', '%27', '%28', '%29', '%3B', '%3A', '%40', '%26', '%3D', '%2B', '%24', '%2C', '%2F', '%3F', '%25', '%23', '%5B', '%5D'];
        $replacements = [' ', '!', '*', "'", '(', ')', ';', ':', '@', '&', '=', '+', '$', ',', '/', '?', '%', '#', '[', ']'];

        $name = mb_strtolower($name);
        $name = str_replace(array_merge($entities, $replacements), '', urlencode($name));
        $name = \Alksily\Support\Str::translate(mb_strtolower($name));

        return $name;
    }

    /**
     * @param string      $path
     * @param null|string $name_with_ext
     *
     * @throws \RunTracy\Helpers\Profiler\Exception\ProfilerException
     *
     * @return null|static
     */
    public static function getFromPath(string $path, string $name_with_ext = null)
    {
        \RunTracy\Helpers\Profiler\Profiler::start('file:getFromPath (%s)', $path);

        // file is saved ?
        $saved = false;

        // tmp file path
        $tmp = CACHE_DIR . '/tmp_' . uniqid();

        switch (true) {
            case Str::start(['http://', 'https://'], $path):
                $headers = get_headers($path);
                $code = mb_substr($headers[0], 9, 3);

                if ($code === 200) {
                    $file = @file_get_contents($path, false, stream_context_create(['http' => ['timeout' => 15]]));

                    if ($file) {
                        $saved = file_put_contents($tmp, $file);
                    }
                }

                break;
            default:
                $file = @file_get_contents($path);

                if ($file) {
                    $saved = file_put_contents($tmp, $file);
                }

                break;
        }

        if ($saved) {
            $salt = uniqid();
            $dir = UPLOAD_DIR . '/' . $salt;

            if (!is_dir($dir)) {
                mkdir($dir, 0777, true);
            }

            $type = addslashes(@exec('file -bi ' . $tmp));
            $info = pathinfo($name_with_ext ?? $path);
            $name = static::prepareFileName($info['filename']);
            $ext = mb_strtolower($info['extension']);
            $save = $dir . '/' . $name . '.' . $ext;

            if (rename($tmp, $save)) {
                $model = new static([
                    'name' => $name,
                    'ext' => $ext,
                    'type' => $type,
                    'size' => filesize($save),
                    'salt' => $salt,
                    'hash' => sha1_file($save),
                    'date' => new \DateTime(),
                ]);
            }
        }

        \RunTracy\Helpers\Profiler\Profiler::finish('file:getFromPath (%s)', $path);

        return $model ?? null;
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
            'name' => $info['filename'],
            'ext' => $info['extension'],
            'type' => addslashes(@exec('file -bi ' . $path)),
            'size' => filesize($path),
            'hash' => sha1_file($path),
        ];

        \RunTracy\Helpers\Profiler\Profiler::finish('file:info (%s)', $path);

        return $result;
    }

    /**
     * @return bool|resource
     */
    public function getResource()
    {
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
     * @throws \RunTracy\Helpers\Profiler\Exception\ProfilerException
     *
     * @return bool
     */
    protected function isValidSizeAndFileExists(string $size): bool
    {
        if (in_array($size, ['middle', 'small'], true)) {
            return file_exists(UPLOAD_DIR . '/' . $this->salt . '/' . $size . '/' . $this->getName());
        }

        return false;
    }

    /**
     * Return file path
     *
     * @param null|string $size
     *
     * @throws \RunTracy\Helpers\Profiler\Exception\ProfilerException
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
     * @throws \RunTracy\Helpers\Profiler\Exception\ProfilerException
     *
     * @return string
     */
    public function getInternalPath(string $size = '')
    {
        return $this->getDir($size) . '/' . $this->getName();
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
                $buf[$uuid][$size] = '/uploads/' . $this->salt . ($size && $this->isValidSizeAndFileExists($size) ? '/' . $size : '') . '/' . $this->getName();
            }

            \RunTracy\Helpers\Profiler\Profiler::finish('file:getPublicPath (%s)', $size, ['uuid' => $uuid]);
        }

        return $buf[$uuid][$size];
    }

    /**
     * Remove local files
     *
     * @throws \RunTracy\Helpers\Profiler\Exception\ProfilerException
     */
    public function unlink(): void
    {
        @exec('rm -rf ' . $this->getDir());
    }
}
