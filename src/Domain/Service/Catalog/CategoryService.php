<?php declare(strict_types=1);

namespace App\Domain\Service\Catalog;

use App\Domain\AbstractService;
use App\Domain\Entities\Catalog\Category;
use App\Domain\Repository\Catalog\CategoryRepository;
use App\Domain\Service\Catalog\Exception\AddressAlreadyExistsException;
use App\Domain\Service\Catalog\Exception\CategoryNotFoundException;
use App\Domain\Service\Catalog\Exception\MissingTitleValueException;
use App\Domain\Service\Catalog\Exception\TitleAlreadyExistsException;
use Ramsey\Uuid\Uuid;
use Tightenco\Collect\Support\Collection;

class CategoryService extends AbstractService
{
    /**
     * @var CategoryRepository
     */
    protected $service;

    protected function init(): void
    {
        $this->service = $this->entityManager->getRepository(Category::class);
    }

    /**
     * @param array $data
     *
     * @throws TitleAlreadyExistsException
     * @throws MissingTitleValueException
     * @throws AddressAlreadyExistsException
     *
     * @return Category
     */
    public function create(array $data = []): Category
    {
        $default = [
            'parent' => \Ramsey\Uuid\Uuid::NIL,
            'children' => false,
            'title' => '',
            'description' => '',
            'address' => '',
            'field1' => '',
            'field2' => '',
            'field3' => '',
            'product' => [
                'field_1' => '',
                'field_2' => '',
                'field_3' => '',
                'field_4' => '',
                'field_5' => '',
            ],
            'status' => \App\Domain\Types\Catalog\CategoryStatusType::STATUS_WORK,
            'pagination' => 10,
            'order' => 1,
            'meta' => [
                'title' => '',
                'description' => '',
                'keywords' => '',
            ],
            'template' => [
                'category' => '',
                'product' => '',
            ],
            'external_id' => '',
            'export' => 'manual',
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

        $category = (new Category)
            ->setParent($data['parent'])
            ->setChildren($data['children'])
            ->setDescription($data['description'])
            ->setTitle($data['title'])
            ->setAddress($data['address'])
            ->setField1($data['field1'])
            ->setField2($data['field2'])
            ->setField3($data['field3'])
            ->setProduct($data['product'])
            ->setStatus($data['status'])
            ->setPagination((int) $data['pagination'])
            ->setOrder((int) $data['order'])
            ->setMeta($data['meta'])
            ->setTemplate($data['template'])
            ->setExternalId($data['external_id'])
            ->setExport($data['export']);

        $this->entityManager->persist($category);
        $this->entityManager->flush();

        return $category;
    }

    /**
     * @param array $data
     *
     * @throws CategoryNotFoundException
     *
     * @return Category|Collection
     */
    public function read(array $data = [])
    {
        $default = [
            'uuid' => null,
            'parent' => null,
            'children' => null,
            'title' => null,
            'address' => null,
            'field1' => null,
            'field2' => null,
            'field3' => null,
            'status' => null,
            'external_id' => null,
            'export' => null,
        ];
        $data = array_merge($default, static::$default_read, $data);

        $criteria = [];

        if ($data['uuid'] !== null) {
            $criteria['uuid'] = $data['uuid'];
        }
        if ($data['parent'] !== null) {
            $criteria['parent'] = $data['parent'];
        }
        if ($data['children'] !== null) {
            $criteria['children'] = $data['children'];
        }
        if ($data['title'] !== null) {
            $criteria['title'] = $data['title'];
        }
        if ($data['address'] !== null) {
            $criteria['address'] = $data['address'];
        }
        if ($data['field1'] !== null) {
            $criteria['field1'] = $data['field1'];
        }
        if ($data['field2'] !== null) {
            $criteria['field2'] = $data['field2'];
        }
        if ($data['field3'] !== null) {
            $criteria['field3'] = $data['field3'];
        }
        if ($data['status'] !== null && in_array($data['status'], \App\Domain\Types\Catalog\CategoryStatusType::LIST, true)) {
            $criteria['status'] = $data['status'];
        }
        if ($data['external_id'] !== null) {
            $criteria['external_id'] = $data['external_id'];
        }
        if ($data['export'] !== null) {
            $criteria['export'] = $data['export'];
        }

        switch (true) {
            case !is_array($data['uuid']) && $data['uuid'] !== null:
            case !is_array($data['title']) && $data['title'] !== null:
            case !is_array($data['address']) && $data['address'] !== null:
            case !is_array($data['external_id']) && $data['external_id'] !== null:
                $category = $this->service->findOneBy($criteria);

                if (empty($category)) {
                    throw new CategoryNotFoundException();
                }

                return $category;

            default:
                return collect($this->service->findBy($criteria, $data['order'], $data['limit'], $data['offset']));
        }
    }

    /**
     * @param Category|string|Uuid $entity
     * @param array                $data
     *
     * @throws TitleAlreadyExistsException
     * @throws AddressAlreadyExistsException
     * @throws CategoryNotFoundException
     *
     * @return Category
     */
    public function update($entity, array $data = []): Category
    {
        switch (true) {
            case is_string($entity) && Uuid::isValid($entity):
            case is_object($entity) && is_a($entity, Uuid::class):
                $entity = $this->service->findOneByUuid((string) $entity);

                break;
        }

        if (is_object($entity) && is_a($entity, Category::class)) {
            $default = [
                'parent' => null,
                'children' => null,
                'title' => null,
                'description' => null,
                'address' => null,
                'field1' => null,
                'field2' => null,
                'field3' => null,
                'product' => null,
                'status' => null,
                'pagination' => null,
                'order' => null,
                'meta' => null,
                'template' => null,
                'external_id' => null,
                'export' => null,
            ];
            $data = array_merge($default, $data);

            if ($data !== $default) {
                if ($data['parent'] !== null) {
                    $entity->setParent($data['parent']);
                }
                if ($data['children'] !== null) {
                    $entity->setChildren($data['children']);
                }
                if ($data['title'] !== null) {
                    $found = $this->service->findOneByTitle($data['title']);

                    if ($found === null || $found === $entity) {
                        $entity->setTitle($data['title']);
                    } else {
                        throw new TitleAlreadyExistsException();
                    }
                }
                if ($data['description'] !== null) {
                    $entity->setDescription($data['description']);
                }
                if ($data['address'] !== null) {
                    $found = $this->service->findOneByAddress($data['address']);

                    if ($found === null || $found === $entity) {
                        $entity->setAddress($data['address']);
                    } else {
                        throw new AddressAlreadyExistsException();
                    }
                }
                if ($data['field1'] !== null) {
                    $entity->setField1($data['field1']);
                }
                if ($data['field2'] !== null) {
                    $entity->setField2($data['field2']);
                }
                if ($data['field3'] !== null) {
                    $entity->setField3($data['field3']);
                }
                if ($data['product'] !== null) {
                    $entity->setProduct($data['product']);
                }
                if ($data['status'] !== null) {
                    $entity->setStatus($data['status']);
                }
                if ($data['pagination'] !== null) {
                    $entity->setPagination((int) $data['pagination']);
                }
                if ($data['order'] !== null) {
                    $entity->setOrder((int) $data['order']);
                }
                if ($data['meta'] !== null) {
                    $entity->setMeta($data['meta']);
                }
                if ($data['template'] !== null) {
                    $entity->setTemplate($data['template']);
                }
                if ($data['external_id'] !== null) {
                    $entity->setExternalId($data['external_id']);
                }
                if ($data['export'] !== null) {
                    $entity->setExport($data['export']);
                }

                $this->entityManager->flush();
            }

            return $entity;
        }

        throw new CategoryNotFoundException();
    }

    /**
     * @param Category|string|Uuid $entity
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

        if (is_object($entity) && is_a($entity, Category::class)) {
            $this->entityManager->remove($entity);
            $this->entityManager->flush();

            return true;
        }

        throw new CategoryNotFoundException();
    }
}
