<?php declare(strict_types=1);

namespace App\Domain\Service\Publication;

use App\Domain\AbstractService;
use App\Domain\Entities\Publication;
use App\Domain\Entities\Publication\Category as PublicationCategory;
use App\Domain\Repository\PublicationRepository;
use App\Domain\Service\Publication\CategoryService as PublicationCategoryService;
use App\Domain\Service\Publication\Exception\AddressAlreadyExistsException;
use App\Domain\Service\Publication\Exception\MissingCategoryValueException;
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

    /**
     * @var PublicationCategoryService
     */
    protected mixed $publicationCategoryService;

    protected function init(): void
    {
        $this->service = $this->entityManager->getRepository(Publication::class);
        $this->publicationCategoryService = $this->container->get(PublicationCategoryService::class);
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
            'category_uuid' => null,
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
            'external_id' => '',
        ];
        $data = array_merge($default, $data);

        if ($data['title'] && $this->service->findOneByTitle($data['title']) !== null) {
            throw new TitleAlreadyExistsException();
        }
        if (!$data['title']) {
            throw new MissingTitleValueException();
        }
        if (!$data['category'] && !$data['category_uuid']) {
            throw new MissingCategoryValueException();
        }

        // retrieve category by uuid
        if (!is_a($data['category'], PublicationCategory::class) && $data['category_uuid']) {
            $data['category'] = $this->publicationCategoryService->read(['uuid' => $data['category_uuid']]);
        }

        $publication = (new Publication())
            ->setUser($data['user'])
            ->setTitle($data['title'])
            ->setAddress($data['address'])
            ->setCategory($data['category'])
            ->setDate($data['date'], $this->parameter('common_timezone', 'UTC'))
            ->setContent($data['content'])
            ->setMeta($data['meta'])
            ->setExternalId($data['external_id']);

        // if address generation is enabled
        if ($this->parameter('common_auto_generate_address', 'no') === 'yes') {
            $publication->setAddress(
                implode('/', array_filter(
                    [
                        $publication->getCategory()->getAddress(),
                        $publication->setAddress('')->getAddress(),
                    ],
                    fn ($el) => (bool) $el
                ))
            );
        }

        $found = $this->service->findOneByAddress($publication->getAddress());
        if ($found !== null) {
            throw new AddressAlreadyExistsException();
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
            'category_uuid' => null,
            'external_id' => null,
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
        if ($data['category_uuid'] !== null) {
            $criteria['category_uuid'] = $data['category_uuid'];
        }
        if ($data['external_id'] !== null) {
            $criteria['external_id'] = $data['external_id'];
        }

        try {
            switch (true) {
                case !is_array($data['uuid']) && $data['uuid'] !== null:
                case !is_array($data['title']) && $data['title'] !== null:
                case !is_array($data['address']) && $data['address'] !== null:
                case !is_array($data['external_id']) && $data['external_id'] !== null:
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
                'category_uuid' => null,
                'date' => null,
                'content' => null,
                'meta' => null,
                'external_id' => null,
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
                if ($data['category'] !== null || $data['category_uuid'] !== null) {
                    // retrieve category by uuid
                    if (!is_a($data['category'], PublicationCategory::class) && $data['category_uuid']) {
                        $data['category'] = $this->publicationCategoryService->read(['uuid' => $data['category_uuid']]);
                    }

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
                if ($data['external_id'] !== null) {
                    $entity->setExternalId($data['external_id']);
                }
                // if address generation is enabled
                if ($this->parameter('common_auto_generate_address', 'no') === 'yes') {
                    $data['address'] = implode('/', array_filter(
                        [
                            $entity->getCategory()?->getAddress(),
                            $entity->setAddress('')->getAddress(),
                        ],
                        fn ($el) => (bool) $el
                    ));
                }
                if ($data['address'] !== null) {
                    $found = $this->service->findOneByAddress($data['address']);

                    if ($found === null || $found === $entity) {
                        $entity->setAddress($data['address']);
                    } else {
                        throw new AddressAlreadyExistsException();
                    }
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
