<?php declare(strict_types=1);

namespace App\Domain\Service\File;

use App\Domain\AbstractService;
use App\Domain\Models\File;
use App\Domain\Service\File\Exception\FileAlreadyExistsException;
use App\Domain\Service\File\Exception\FileNotFoundException;
use Illuminate\Support\Collection;
use Ramsey\Uuid\UuidInterface as Uuid;

class FileService extends AbstractService
{
    protected static array $search_columns = ['name'];

    private const REMOTE_REDIRECT_CODES = [301, 302, 303, 307, 308];

    private const REMOTE_REDIRECT_LIMIT = 5;

    public function createFromPath(string $path, ?string $name_with_ext = null): ?File
    {
        $saved = false;

        // is file saved?
        switch (true) {
            case str_starts_with($path, 'http://'):
            case str_starts_with($path, 'https://'):
                if (($path = static::getFileFromRemote($path)) !== false) {
                    $saved = true;
                }

                break;

            default:
                if (file_exists($path)) {
                    $saved = true;
                }

                break;
        }

        if ($saved) {
            $salt = uniqid();
            $dir = UPLOAD_DIR . '/' . $salt . '/' . File::prepareName($name_with_ext ?: basename($path));

            if (!is_dir(dirname($dir))) {
                mkdir(dirname($dir), 0o777, true);
            }

            if (rename($path, $dir) && chmod($dir, 0o444)) {
                $info = File::info($dir);

                try {
                    return $this->create([
                        'name' => $info['name'],
                        'ext' => $info['ext'],
                        'type' => $info['type'],
                        'size' => $info['size'],
                        'hash' => $info['hash'],
                        'salt' => $salt,
                    ]);
                } catch (FileAlreadyExistsException $exception) {
                    // remove uploaded temp file
                    static::removeDirectory(dirname($dir));

                    try {
                        return $this->read(['hash' => $info['hash']]);
                    } catch (FileNotFoundException $e) {
                        return null;
                    }
                }
            }
        }

        return null;
    }

    /**
     * Recursively remove directory with content
     */
    protected static function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        foreach (array_diff(scandir($dir) ?: [], ['.', '..']) as $item) {
            $item = $dir . '/' . $item;

            if (is_dir($item)) {
                static::removeDirectory($item);
            } else {
                @unlink($item);
            }
        }

        @rmdir($dir);
    }

    /**
     * Get file from url, recursion when redirect
     */
    protected static function getFileFromRemote(string $path, int $depth = 0): false|string
    {
        if ($depth > self::REMOTE_REDIRECT_LIMIT) {
            return false;
        }

        $headers = @get_headers($path, true);

        if ($headers === false) {
            return false;
        }

        // with followed redirects each entry becomes a list, the last one is actual
        $status = is_array($headers[0] ?? '') ? end($headers[0]) : ($headers[0] ?? '');
        $code = (int) mb_substr((string) $status, 9, 3);

        if (in_array($code, self::REMOTE_REDIRECT_CODES, true)) {
            $location = $headers['Location'] ?? '';

            if (is_array($location)) {
                $location = (string) end($location);
            }
            if (!$location) {
                return false;
            }

            // relative location must be resolved against the current url
            if (!str_contains($location, '://')) {
                $url = parse_url($path);
                $scheme = $url['scheme'] ?? 'http';

                $location = str_starts_with($location, '//')
                    ? $scheme . ':' . $location
                    : $scheme . '://' . ($url['host'] ?? '') . '/' . ltrim($location, '/');
            }

            return static::getFileFromRemote($location, $depth + 1);
        }
        if ($code === 200) {
            $file = @file_get_contents($path, false, stream_context_create(['http' => ['timeout' => 15]]));

            if ($file) {
                $basename = File::prepareName(($t = basename($path)) && mb_strpos($t, '.') ? $t : '/tmp_' . uniqid());
                $path = CACHE_DIR . '/' . $basename;

                if (file_put_contents($path, $file)) {
                    return $path;
                }
            }
        }

        return false;
    }

    /**
     * @throws FileAlreadyExistsException
     */
    public function create(array $data = []): File
    {
        $file = new File();
        $file->fill($data);

        if ($file->hash && File::firstWhere(['hash' => $file->hash]) !== null) {
            throw new FileAlreadyExistsException();
        }

        $file->save();

        return $file;
    }

    /**
     * @throws FileNotFoundException
     *
     * @return Collection|File
     */
    public function read(array $data = [])
    {
        $default = [
            'uuid' => null,
            'hash' => null,
            'name' => null,
            'ext' => null,
            'type' => null,
            'size' => null,
        ];
        $data = array_merge($default, static::$default_read, $data);

        $criteria = [];

        if ($data['uuid'] !== null) {
            $criteria['uuid'] = $data['uuid'];
        }
        if ($data['hash'] !== null) {
            $criteria['hash'] = $data['hash'];
        }
        if ($data['name'] !== null) {
            $criteria['name'] = $data['name'];
        }
        if ($data['ext'] !== null) {
            $criteria['ext'] = $data['ext'];
        }
        if ($data['type'] !== null) {
            $criteria['type'] = $data['type'];
        }
        if ($data['size'] !== null) {
            $criteria['size'] = $data['size'];
        }

        switch (true) {
            case !is_array($data['uuid']) && $data['uuid'] !== null:
            case !is_array($data['hash']) && $data['hash'] !== null:
                /** @var File $file */
                $file = $this->buildQuery(File::query(), $criteria, $data)->first();

                if (empty($file)) {
                    throw new FileNotFoundException();
                }

                return $file;

            case !is_array($data['name']) && $data['name'] !== null && !is_array($data['ext']) && $data['ext'] !== null:
                /** @var File $file */
                $file = File::firstWhere([
                    'name' => $data['name'],
                    'ext' => $data['ext'],
                ]);

                if (empty($file)) {
                    throw new FileNotFoundException();
                }

                return $file;

            default:
                return $this->buildQuery(File::query(), $criteria, $data)->get();
        }
    }

    /**
     * @param File|string|Uuid $entity
     *
     * @throws FileNotFoundException
     */
    public function update($entity, array $data = []): File
    {
        switch (true) {
            case is_string($entity) && \Ramsey\Uuid\Uuid::isValid($entity):
            case is_object($entity) && is_a($entity, Uuid::class):
                $entity = $this->read(['uuid' => $entity]);

                break;
        }

        if (is_object($entity) && is_a($entity, File::class)) {
            $entity->fill($data);
            $entity->save();

            return $entity;
        }

        throw new FileNotFoundException();
    }

    /**
     * @param File|string|Uuid $entity
     *
     * @throws FileNotFoundException
     */
    public function delete($entity): bool
    {
        switch (true) {
            case is_string($entity) && \Ramsey\Uuid\Uuid::isValid($entity):
            case is_object($entity) && is_a($entity, Uuid::class):
                $entity = $this->read(['uuid' => $entity]);

                break;
        }

        if (is_object($entity) && is_a($entity, File::class)) {
            $this->db->table('file_related')->where('file_uuid', $entity->uuid)->delete();
            @exec('rm -rf ' . $entity->dir());
            $entity->delete();

            return true;
        }

        throw new FileNotFoundException();
    }
}
