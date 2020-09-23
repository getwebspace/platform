<?php declare(strict_types=1);

namespace App\Domain\Service\Publication;

use App\Domain\AbstractService;
use App\Domain\Entities\Publication\Category as PublicationCategory;
use App\Domain\Repository\Publication\CategoryRepository as PublicationCategoryRepository;
use App\Domain\Service\Publication\Exception\AddressAlreadyExistsException;
use App\Domain\Service\Publication\Exception\CategoryNotFoundException;
use App\Domain\Service\Publication\Exception\MissingTitleValueException;
use App\Domain\Service\Publication\Exception\TitleAlreadyExistsException;
use Illuminate\Support\Collection;
use Ramsey\Uuid\Uuid;

class CategoryService extends AbstractService
{
    /**
     * @var PublicationCategoryRepository
     */
    protected $service;

    protected function init(): void
    {
        $this->service = $this->entityManager->getRepository(PublicationCategory::class);
    }

    /**
     * @param array $data
     *
     * @throws TitleAlreadyExistsException
     * @throws MissingTitleValueException
     * @throws AddressAlreadyExistsException
     *
     * @return PublicationCategory
     */
    public function create(array $data = []): PublicationCategory
    {
        $default = [
            'title' => '',
            'address' => '',
            'description' => '',
            'parent' => '',
            'pagination' => 10,
            'children' => false,
            'public' => true,
            'sort' => [
                'by' => '',
                'direction' => '',
            ],
            'meta' => [
                'title' => '',
                'description' => '',
                'keywords' => '',
            ],
            'template' => [
                'list' => '',
                'short' => '',
                'full' => '',
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

        $publicationCategory = (new PublicationCategory)
            ->setTitle($data['title'])
            ->setAddress($data['address'])
            ->setDescription($data['description'])
            ->setParent($data['parent'])
            ->setPagination((int) $data['pagination'])
            ->setChildren($data['children'])
            ->setPublic($data['public'])
            ->setSort($data['sort'])
            ->setMeta($data['meta'])
            ->setTemplate($data['template']);

        $this->entityManager->persist($publicationCategory);
        $this->entityManager->flush();

        return $publicationCategory;
    }

    /**
     * @param array $data
     *
     * @throws CategoryNotFoundException
     *
     * @return Collection|PublicationCategory
     */
    public function read(array $data = [])
    {
        $default = [
            'uuid' => null,
            'title' => null,
            'address' => null,
            'parent' => null,
        ];
        $data = array_merge($default, static::$default_read, $data);

        $criteria = [];

        if ($data['uuid'] !== null) {
            $criteria['uuid'] = $data['uuid'];
        }
        if ($data['title'] !== null) {
            $criteria['title'] = $data['title'];
        }
        if ($data['address'] !== null) {
            $criteria['address'] = $data['address'];
        }
        if ($data['parent'] !== null) {
            $criteria['parent'] = $data['parent'];
        }

        try {
            switch (true) {
                case !is_array($data['uuid']) && $data['uuid'] !== null:
                case !is_array($data['title']) && $data['title'] !== null:
                case !is_array($data['address']) && $data['address'] !== null:
                    $publicationCategory = $this->service->findOneBy($criteria);

                    if (empty($publicationCategory)) {
                        throw new CategoryNotFoundException();
                    }

                    return $publicationCategory;

                default:
                    return collect($this->service->findBy($criteria, $data['order'], $data['limit'], $data['offset']));
            }
        } catch (\Doctrine\DBAL\Exception\TableNotFoundException $e) {
            return null;
        }
    }

    /**
     * @param PublicationCategory|string|Uuid $entity
     * @param array                           $data
     *
     * @throws TitleAlreadyExistsException
     * @throws AddressAlreadyExistsException
     * @throws CategoryNotFoundException
     *
     * @return PublicationCategory
     */
    public function update($entity, array $data = []): PublicationCategory
    {
        switch (true) {
            case is_string($entity) && Uuid::isValid($entity):
            case is_object($entity) && is_a($entity, Uuid::class):
                $entity = $this->service->findOneByUuid((string) $entity);

                break;
        }

        if (is_object($entity) && is_a($entity, PublicationCategory::class)) {
            $default = [
                'title' => null,
                'address' => null,
                'description' => null,
                'parent' => null,
                'pagination' => null,
                'children' => null,
                'public' => null,
                'sort' => null,
                'meta' => null,
                'template' => null,
            ];
            $data = array_merge($default, $data);

            if ($data !== $default) {
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
                if ($data['description'] !== null) {
                    $entity->setDescription($data['description']);
                }
                if ($data['parent'] !== null) {
                    $entity->setParent($data['parent']);
                }
                if ($data['pagination'] !== null) {
                    $entity->setPagination((int) $data['pagination']);
                }
                if ($data['children'] !== null) {
                    $entity->setChildren($data['children']);
                }
                if ($data['public'] !== null) {
                    $entity->setPublic($data['public']);
                }
                if ($data['sort'] !== null) {
                    $entity->setSort($data['sort']);
                }
                if ($data['meta'] !== null) {
                    $entity->setMeta($data['meta']);
                }
                if ($data['template'] !== null) {
                    $entity->setTemplate($data['template']);
                }

                $this->entityManager->flush();
            }

            return $entity;
        }

        throw new CategoryNotFoundException();
    }

    /**
     * @param PublicationCategory|string|Uuid $entity
     *
     * @throws CategoryNotFoundException
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

        if (is_object($entity) && is_a($entity, PublicationCategory::class)) {
            if (($files = $entity->getFiles()) && $files->isNotEmpty()) {
                $fileService = \App\Domain\Service\File\FileService::getWithContainer($this->container);

                /**
                 * @var \App\Domain\Entities\File $file
                 */
                foreach ($files as $file) {
                    try {
                        $fileService->delete($file);
                    } catch (\App\Domain\Service\File\Exception\FileNotFoundException $e) {
                        // nothing, file not found
                    }
                }
            }

            $this->entityManager->remove($entity);
            $this->entityManager->flush();

            return true;
        }

        throw new CategoryNotFoundException();
    }
}
