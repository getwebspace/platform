<?php declare(strict_types=1);

namespace App\Domain\Service\Publication;

use App\Domain\AbstractService;
use App\Domain\Entities\Publication;
use App\Domain\Repository\PublicationRepository;
use App\Domain\Service\Publication\CategoryService as PublicationCategoryService;
use App\Domain\Service\Publication\Exception\AddressAlreadyExistsException;
use App\Domain\Service\Publication\Exception\MissingTitleValueException;
use App\Domain\Service\Publication\Exception\PublicationNotFoundException;
use App\Domain\Service\Publication\Exception\TitleAlreadyExistsException;
use Illuminate\Support\Collection;
use Ramsey\Uuid\UuidInterface as Uuid;

class PublicationService extends AbstractService
{
    /**
     * @var PublicationRepository
     */
    protected mixed $service;

    protected function init(): void
    {
        $this->service = $this->entityManager->getRepository(Publication::class);
    }

    /**
     * @throws TitleAlreadyExistsException
     * @throws MissingTitleValueException
     * @throws AddressAlreadyExistsException
     */
    public function create(array $data = []): Publication
    {
        $default = [
            'user' => '',
            'title' => '',
            'address' => '',
            'category' => null,
            'date' => 'now',
            'content' => [
                'short' => '',
                'full' => '',
            ],
            'meta' => [
                'title' => '',
                'description' => '',
                'keywords' => '',
            ],
        ];
        $data = array_merge($default, $data);

        if ($data['title'] && $this->service->findOneByTitle($data['title']) !== null) {
            throw new TitleAlreadyExistsException();
        }
        if (!$data['title']) {
            throw new MissingTitleValueException();
        }
        if ($data['address'] && $this->service->findOneByAddress($data['address']) !== null) {
            throw new AddressAlreadyExistsException();
        }

        $publication = (new Publication())
            ->setUser($data['user'])
            ->setTitle($data['title'])
            ->setAddress($data['address'])
            ->setCategory($data['category'])
            ->setDate($data['date'], $this->parameter('common_timezone', 'UTC'))
            ->setContent($data['content'])
            ->setMeta($data['meta']);

        // if address generation is enabled
        if ($this->parameter('common_auto_generate_address', 'no') === 'yes') {
            $publicationCategoryService = $this->container->get(PublicationCategoryService::class);
            $publicationCategory = $publicationCategoryService->read(['uuid' => $data['category']]);

            // combine address category with publication address
            $publication->setAddress(
                implode('/', [$publicationCategory->getAddress(), $publication->setAddress('')->getAddress()])
            );
        }

        $this->entityManager->persist($publication);
        $this->entityManager->flush();

        return $publication;
    }

    /**
     * @throws PublicationNotFoundException
     *
     * @return Collection|Publication
     */
    public function read(array $data = [])
    {
        $default = [
            'uuid' => null,
            'user' => null,
            'address' => null,
            'title' => null,
            'category' => null,
        ];
        $data = array_merge($default, static::$default_read, $data);

        $criteria = [];

        if ($data['uuid'] !== null) {
            $criteria['uuid'] = $data['uuid'];
        }
        if ($data['user'] !== null) {
            $criteria['user'] = $data['user'];
        }
        if ($data['address'] !== null) {
            $criteria['address'] = $data['address'];
        }
        if ($data['title'] !== null) {
            $criteria['title'] = $data['title'];
        }
        if ($data['category'] !== null) {
            $criteria['category'] = $data['category'];
        }

        try {
            switch (true) {
                case !is_array($data['uuid']) && $data['uuid'] !== null:
                case !is_array($data['title']) && $data['title'] !== null:
                case !is_array($data['address']) && $data['address'] !== null:
                    $publication = $this->service->findOneBy($criteria);

                    if (empty($publication)) {
                        throw new PublicationNotFoundException();
                    }

                    return $publication;

                default:
                    return collect($this->service->findBy($criteria, $data['order'], $data['limit'], $data['offset']));
            }
        } catch (\Doctrine\DBAL\Exception\TableNotFoundException $e) {
            return null;
        }
    }

    /**
     * @param Publication|string|Uuid $entity
     *
     * @throws PublicationNotFoundException
     * @throws TitleAlreadyExistsException
     * @throws AddressAlreadyExistsException
     */
    public function update($entity, array $data = []): Publication
    {
        switch (true) {
            case is_string($entity) && \Ramsey\Uuid\Uuid::isValid($entity):
            case is_object($entity) && is_a($entity, Uuid::class):
                $entity = $this->service->findOneByUuid((string) $entity);

                break;
        }

        if (is_object($entity) && is_a($entity, Publication::class)) {
            $default = [
                'user' => null,
                'title' => null,
                'address' => null,
                'category' => null,
                'date' => null,
                'content' => null,
                'meta' => null,
            ];
            $data = array_merge($default, $data);

            if ($data !== $default) {
                if ($data['user'] !== null) {
                    $entity->setUser($data['user']);
                }
                if ($data['title'] !== null) {
                    $found = $this->service->findOneByTitle($data['title']);

                    if ($found === null || $found === $entity) {
                        $entity->setTitle($data['title']);
                    } else {
                        throw new TitleAlreadyExistsException();
                    }
                }
                if ($data['address'] !== null) {
                    $found = $this->service->findOneByAddress($data['address']);

                    if ($found === null || $found === $entity) {
                        $entity->setAddress($data['address']);
                    } else {
                        throw new AddressAlreadyExistsException();
                    }
                }
                if ($data['category'] !== null) {
                    $entity->setCategory($data['category']);
                }
                if ($data['date'] !== null) {
                    $entity->setDate($data['date'], $this->parameter('common_timezone', 'UTC'));
                }
                if ($data['content'] !== null) {
                    $entity->setContent($data['content']);
                }
                if ($data['meta'] !== null) {
                    $entity->setMeta($data['meta']);
                }

                $this->entityManager->flush();
            }

            return $entity;
        }

        throw new PublicationNotFoundException();
    }

    /**
     * @param Publication|string|Uuid $entity
     *
     * @throws PublicationNotFoundException
     */
    public function delete($entity): bool
    {
        switch (true) {
            case is_string($entity) && \Ramsey\Uuid\Uuid::isValid($entity):
            case is_object($entity) && is_a($entity, Uuid::class):
                $entity = $this->service->findOneByUuid((string) $entity);

                break;
        }

        if (is_object($entity) && is_a($entity, Publication::class)) {
            if (($files = $entity->getFiles()) && $files->isNotEmpty()) {
                $fileService = $this->container->get(\App\Domain\Service\File\FileService::class);

                /**
                 * @var \App\Domain\Entities\File $file
                 */
                foreach ($files as $file) {
                    try {
                        $fileService->delete($file);
                    } catch (\Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException $e) {
                        // nothing, file not found
                    } catch (\App\Domain\Service\File\Exception\FileNotFoundException $e) {
                        // nothing, file not found
                    }
                }
            }

            $this->entityManager->remove($entity);
            $this->entityManager->flush();

            return true;
        }

        throw new PublicationNotFoundException();
    }
}
