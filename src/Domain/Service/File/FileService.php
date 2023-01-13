<?php declare(strict_types=1);

namespace App\Domain\Service\File;

use App\Domain\AbstractService;
use App\Domain\Entities\File;
use App\Domain\Repository\FileRepository;
use App\Domain\Service\File\Exception\FileAlreadyExistsException;
use App\Domain\Service\File\Exception\FileNotFoundException;
use Illuminate\Support\Collection;
use Ramsey\Uuid\UuidInterface as Uuid;

class FileService extends AbstractService
{
    /**
     * @var FileRepository
     */
    protected mixed $service;

    /**
     * @var FileRelationService
     */
    protected $serviceFileRelation;

    protected function init(): void
    {
        $this->service = $this->entityManager->getRepository(File::class);
        $this->serviceFileRelation = $this->container->get(FileRelationService::class);
    }

    /**
     * @return null|File
     */
    public function createFromPath(string $path, string $name_with_ext = null)
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
            $dir = UPLOAD_DIR . '/' . $salt . '/' . File::prepareName($name_with_ext ? $name_with_ext : basename($path));

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
     * Get file from url
     * recursion when redirect
     *
     * @return bool|string
     */
    protected static function getFileFromRemote($path)
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
        $default = [
            'name' => '',
            'ext' => '',
            'type' => '',
            'size' => '',
            'hash' => '',
            'salt' => uniqid(),
            'date' => 'now',
        ];
        $data = array_merge($default, $data);

        if ($data['hash'] && $this->service->findOneByHash($data['hash']) !== null) {
            throw new FileAlreadyExistsException();
        }

        $file = (new File())
            ->setName($data['name'])
            ->setExt($data['ext'])
            ->setType($data['type'])
            ->setSize($data['size'])
            ->setSalt($data['salt'])
            ->setHash($data['hash'])
            ->setDate($data['date'], $this->parameter('common_timezone', 'UTC'));

        $this->entityManager->persist($file);
        $this->entityManager->flush();

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

        try {
            switch (true) {
                case !is_array($data['uuid']) && $data['uuid'] !== null:
                case !is_array($data['hash']) && $data['hash'] !== null:
                    $file = $this->service->findOneBy($criteria);

                    if (empty($file)) {
                        throw new FileNotFoundException();
                    }

                    return $file;

                case !is_array($data['name']) && $data['name'] !== null && !is_array($data['ext']) && $data['ext'] !== null:
                    $file = $this->service->findOneByFilename($data['name'], $data['ext']);

                    if (empty($file)) {
                        throw new FileNotFoundException();
                    }

                    return $file;

                default:
                    return collect($this->service->findBy($criteria, $data['order'], $data['limit'], $data['offset']));
            }
        } catch (\Doctrine\DBAL\Exception\TableNotFoundException $e) {
            return null;
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
                $entity = $this->service->findOneByUuid((string) $entity);

                break;
        }

        if (is_object($entity) && is_a($entity, File::class)) {
            $default = [
                'name' => null,
                'ext' => null,
                'type' => null,
                'size' => null,
                'hash' => null,
                'salt' => null,
                'date' => null,
            ];
            $data = array_merge($default, $data);

            if ($data !== $default) {
                if ($data['hash'] !== null) {
                    $found = $this->service->findOneByTitle($data['hash']);

                    if ($found === null || $found === $entity) {
                        $entity->setHash($data['hash']);
                    } else {
                        throw new FileAlreadyExistsException();
                    }
                }
                if ($data['name'] !== null) {
                    $entity->setName($data['name']);
                }
                if ($data['ext'] !== null) {
                    $entity->setExt($data['ext']);
                }
                if ($data['type'] !== null) {
                    $entity->setType($data['type']);
                }
                if ($data['size'] !== null) {
                    $entity->setSize($data['size']);
                }
                if ($data['salt'] !== null) {
                    $entity->setSalt($data['salt']);
                }
                if ($data['date'] !== null) {
                    $entity->setDate($data['date'], $this->parameter('common_timezone', 'UTC'));
                }

                $this->entityManager->flush();
            }

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
                $entity = $this->service->findOneByUuid((string) $entity);

                break;
        }

        if (is_object($entity) && is_a($entity, File::class)) {
            $relations = $this->serviceFileRelation->read(['file_uuid' => $entity->getUuid()]);

            foreach ($relations as $relation) {
                $this->serviceFileRelation->delete($relation);
            }

            @exec('rm -rf ' . $entity->getDir());

            $this->entityManager->remove($entity);
            $this->entityManager->flush();

            return true;
        }

        throw new FileNotFoundException();
    }
}
