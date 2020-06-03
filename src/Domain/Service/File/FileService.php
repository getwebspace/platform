<?php declare(strict_types=1);

namespace App\Domain\Service\File;

use Alksily\Entity\Collection;
use App\Domain\AbstractService;
use App\Domain\Entities\File;
use App\Domain\Repository\FileRepository;
use App\Domain\Service\File\Exception\FileAlreadyExistsException;
use App\Domain\Service\File\Exception\FileNotFoundException;
use Doctrine\ORM\EntityManager;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;

class FileService extends AbstractService
{
    /**
     * @var FileRepository
     */
    protected $service;

    public function __construct(EntityManager $entityManager, LoggerInterface $logger = null)
    {
        parent::__construct($entityManager, $logger);

        $this->service = $this->entityManager->getRepository(File::class);
    }

    /**
     * @param string      $path
     * @param null|string $name_with_ext
     *
     * @throws FileAlreadyExistsException
     *
     * @return null|File
     */
    public function createFromPath(string $path, string $name_with_ext = null)
    {
        \RunTracy\Helpers\Profiler\Profiler::start('file:getFromPath (%s)', $path);

        $saved = false;

        // is file saved?
        switch (true) {
            case str_starts_with(['http://', 'https://'], $path):
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
            $dir = UPLOAD_DIR . '/' . $salt;

            if (!is_dir($dir)) {
                mkdir($dir, 0777, true);
            }

            $info = File::info($path);

            if (rename($path, $dir . '/' . $info['name'] . '.' . $info['ext'])) {
                return $this->create([
                    'name' => $info['name'],
                    'ext' => $info['ext'],
                    'type' => $info['type'],
                    'size' => $info['size'],
                    'hash' => $info['hash'],
                    'salt' => $salt,
                ]);
            }
        }

        return null;
    }

    /**
     * Get file from url
     * recursion when redirect
     *
     * @param $path
     *
     * @return bool|string
     */
    protected static function getFileFromRemote($path)
    {
        $headers = get_headers($path, 1);
        $code = (int) mb_substr($headers[0], 9, 3);

        if ($code === 302) {
            $url = parse_url($path);
            $location = $headers['Location'];

            return static::getFileFromRemote(($url['scheme'] ?? 'http') . '://' . $url['host'] . '/' . $location);
        }
        if ($code === 200) {
            $file = @file_get_contents($path, false, stream_context_create(['http' => ['timeout' => 15]]));

            if ($file) {
                $basename = ($t = basename($path)) && mb_strpos($t, '.') ? $t : '/tmp_' . uniqid();
                $path = CACHE_DIR . '/' . $basename;

                if (file_put_contents($path, $file)) {
                    return $path;
                }
            }
        }

        return false;
    }

    /**
     * @param array $data
     *
     * @throws FileAlreadyExistsException
     *
     * @return File
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

        $file = (new File)
            ->setName($data['name'])
            ->setExt($data['ext'])
            ->setType($data['type'])
            ->setSize($data['size'])
            ->setSalt($data['salt'])
            ->setHash($data['hash'])
            ->setDate($data['date']);

        $this->entityManager->persist($file);
        $this->entityManager->flush();

        return $file;
    }

    /**
     * @param array $data
     *
     * @throws FileNotFoundException
     *
     * @return Collection|File
     */
    public function read(array $data = [])
    {
        $default = [
            'uuid' => '',
            'hash' => '',
            'ext' => '',
            'type' => '',
            'size' => '',
        ];
        $data = array_merge($default, static::$default_read, $data);

        if ($data['uuid'] || $data['hash']) {
            switch (true) {
                case $data['uuid']:
                    $file = $this->service->findOneByUuid((string) $data['uuid']);

                    break;

                case $data['hash']:
                    $file = $this->service->findOneByHash($data['hash']);

                    break;
            }

            if (empty($file)) {
                throw new FileNotFoundException();
            }

            return $file;
        }

        $criteria = [];

        if ($data['ext'] !== '') {
            $criteria['ext'] = $data['ext'];
        }
        if ($data['type'] !== '') {
            $criteria['type'] = $data['type'];
        }
        if ($data['size'] !== '') {
            $criteria['size'] = $data['size'];
        }

        return collect($this->service->findBy($criteria, $data['order'], $data['limit'], $data['offset']));
    }

    /**
     * @param File|string|Uuid $entity
     * @param array            $data
     *
     * @throws FileNotFoundException
     *
     * @return File
     */
    public function update($entity, array $data = []): File
    {
        switch (true) {
            case is_string($entity) && Uuid::isValid($entity):
            case is_object($entity) && is_a($entity, Uuid::class):
                $entity = $this->service->findOneByUuid((string) $entity);

                break;
        }

        if (is_object($entity) && is_a($entity, File::class)) {
            $default = [
                'name' => '',
                'ext' => '',
                'type' => '',
                'size' => '',
                'hash' => '',
                'salt' => '',
                'date' => '',
            ];
            $data = array_merge($default, $data);

            if ($data !== $default) {
                if ($data['hash']) {
                    $found = $this->service->findOneByTitle($data['hash']);

                    if ($found === null || $found === $entity) {
                        $entity->setHash($data['hash']);
                    } else {
                        throw new FileAlreadyExistsException();
                    }
                }
                if ($data['name']) {
                    $entity->setName($data['name']);
                }
                if ($data['ext']) {
                    $entity->setExt($data['ext']);
                }
                if ($data['type']) {
                    $entity->setType($data['type']);
                }
                if ($data['size']) {
                    $entity->setSize($data['size']);
                }
                if ($data['salt']) {
                    $entity->setSalt($data['salt']);
                }
                if ($data['date']) {
                    $entity->setDate($data['date']);
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
     *
     * @return bool
     */
    public function delete($entity): bool
    {
        switch (true) {
            case is_string($entity) && Uuid::isValid($entity):
            case is_object($entity) && is_a($entity, Uuid::class):
                $entity = $this->service->findOneByUuid((string) $entity);

                break;
        }

        if (is_object($entity) && is_a($entity, File::class)) {
            @exec('rm -rf ' . $entity->getDir());

            $this->entityManager->remove($entity);
            $this->entityManager->flush();

            return true;
        }

        throw new FileNotFoundException();
    }
}
