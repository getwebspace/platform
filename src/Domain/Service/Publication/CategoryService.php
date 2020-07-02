<?php declare(strict_types=1);

namespace App\Domain\Service\Publication;

use App\Domain\AbstractService;
use App\Domain\Entities\Publication\Category as PublicationCategory;
use App\Domain\Repository\Publication\CategoryRepository as PublicationCategoryRepository;
use App\Domain\Service\Publication\Exception\AddressAlreadyExistsException;
use App\Domain\Service\Publication\Exception\CategoryNotFoundException;
use App\Domain\Service\Publication\Exception\MissingTitleValueException;
use App\Domain\Service\Publication\Exception\TitleAlreadyExistsException;
use Ramsey\Uuid\Uuid;
use Tightenco\Collect\Support\Collection;

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

        if ($data['uuid'] || $data['title'] || $data['address']) {
            switch (true) {
                case $data['uuid']:
                    $publicationCategory = $this->service->findOneByUuid((string) $data['uuid']);

                    break;

                case $data['title']:
                    $publicationCategory = $this->service->findOneByTitle($data['title']);

                    break;

                case $data['address']:
                    $publicationCategory = $this->service->findOneByAddress($data['address']);

                    break;
            }

            if (empty($publicationCategory)) {
                throw new CategoryNotFoundException();
            }

            return $publicationCategory;
        }

        $criteria = [];

        if ($data['parent'] !== null) {
            $criteria['parent'] = $data['parent'];
        }

        return collect($this->service->findBy($criteria, $data['order'], $data['limit'], $data['offset']));
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
        if (
            (is_string($entity) && Uuid::isValid($entity)) ||
            (is_object($entity) && is_a($entity, Uuid::class))
        ) {
            $entity = $this->service->findOneByUuid((string) $entity);
        }

        if (is_object($entity) && is_a($entity, PublicationCategory::class)) {
            $this->entityManager->remove($entity);
            $this->entityManager->flush();

            return true;
        }

        throw new CategoryNotFoundException();
    }
}
