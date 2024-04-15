<?php declare(strict_types=1);

namespace App\Domain\Service\File;

use App\Domain\AbstractService;
use App\Domain\Models\File;
use App\Domain\Service\File\Exception\FileAlreadyExistsException;
use App\Domain\Service\File\Exception\FileNotFoundException;
use Illuminate\Support\Collection;
use Ramsey\Uuid\UuidInterface as Uuid;
use Illuminate\Database\Eloquent\Builder;

class FileService extends AbstractService
{


    public function createFromPath(string $path, string $name_with_ext = null): ?File
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

            if (rename($path, $dir) && chmod($dir, 444)) {
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
                    @exec('rm -rf ' . dirname($dir));

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
     * Get file from url, recursion when redirect
     */
    protected static function getFileFromRemote(string $path): false|string
    {
        $headers = get_headers($path, true);
        $code = (int) mb_substr($headers[0], 9, 3);

        if ($code === 302) {
            $url = parse_url($path);
            $location = $headers['Location'] ?? '';

            return static::getFileFromRemote(($url['scheme'] ?? 'http') . '://' . $url['host'] . '/' . $location);
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
        $file = new File;
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
                $file = File::firstWhere($criteria);

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
                $query = File::query();
                /** @var Builder $query */

                foreach ($criteria as $key => $value) {
                    if (is_array($value)) {
                        $query->orWhereIn($key, $value);
                    } else {
                        $query->orWhere($key, $value);
                    }
                }
                foreach ($data['order'] as $column => $direction) {
                    $query = $query->orderBy($column, $direction);
                }
                if ($data['limit']) {
                    $query = $query->limit($data['limit']);
                }
                if ($data['offset']) {
                    $query = $query->offset($data['offset']);
                }

                return $query->get();
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
