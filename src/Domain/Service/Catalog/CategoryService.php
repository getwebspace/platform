<?php declare(strict_types=1);

namespace App\Domain\Service\Catalog;

use App\Domain\AbstractService;
use App\Domain\Entities\Catalog\Category;
use App\Domain\Repository\Catalog\CategoryRepository;
use App\Domain\Service\Catalog\Exception\AddressAlreadyExistsException;
use App\Domain\Service\Catalog\Exception\CategoryNotFoundException;
use App\Domain\Service\Catalog\Exception\MissingTitleValueException;
use Illuminate\Support\Collection;
use Ramsey\Uuid\UuidInterface as Uuid;

class CategoryService extends AbstractService
{
    /**
     * @var CategoryRepository
     */
    protected mixed $service;

    protected function init(): void
    {
        $this->service = $this->entityManager->getRepository(Category::class);
    }

    /**
     * @throws MissingTitleValueException
     * @throws AddressAlreadyExistsException
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
            'attributes' => [],
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
                'category' => '',
                'product' => '',
            ],
            'external_id' => '',
            'export' => 'manual',
        ];
        $data = array_merge($default, $data);

        if (!$data['title']) {
            throw new MissingTitleValueException();
        }

        $category = (new Category())
            ->setParent($data['parent'])
            ->setChildren($data['children'])
            ->setDescription($data['description'])
            ->setTitle($data['title'])
            ->setAddress($data['address'])
            ->setField1($data['field1'])
            ->setField2($data['field2'])
            ->setField3($data['field3'])
            ->setProduct($data['product'])
            ->setAttributes($data['attributes'])
            ->setStatus($data['status'])
            ->setPagination((int) $data['pagination'])
            ->setOrder((int) $data['order'])
            ->setSort($data['sort'])
            ->setMeta($data['meta'])
            ->setTemplate($data['template'])
            ->setExternalId($data['external_id'])
            ->setExport($data['export']);

        // if address generation is enabled
        if (!$data['address'] && $this->parameter('common_auto_generate_address', 'no') === 'yes' && \Ramsey\Uuid\Uuid::isValid((string) $data['parent']) && $data['parent'] !== \Ramsey\Uuid\Uuid::NIL) {
            try {
                $parent = $this->read(['uuid' => $data['parent']]);

                // combine address category with parent category
                $category->setAddress(
                    implode('/', [$parent->getAddress(), $category->setAddress('')->getAddress()])
                );
            } catch (CategoryNotFoundException $e) {
                // nothing
            }
        }

        /** @var Category $category */
        if ($this->service->findOneUnique($category->getParent()->toString(), $category->getAddress(), $category->getExternalId()) !== null) {
            throw new AddressAlreadyExistsException();
        }

        $this->entityManager->persist($category);
        $this->entityManager->flush();

        return $category;
    }

    /**
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

        try {
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
        } catch (\Doctrine\DBAL\Exception\TableNotFoundException $e) {
            return null;
        }
    }

    /**
     * @param Category|string|Uuid $entity
     *
     * @throws AddressAlreadyExistsException
     * @throws CategoryNotFoundException
     */
    public function update($entity, array $data = []): Category
    {
        switch (true) {
            case is_string($entity) && \Ramsey\Uuid\Uuid::isValid($entity):
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
                'attributes' => null,
                'product' => null,
                'status' => null,
                'pagination' => null,
                'order' => null,
                'sort' => null,
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
                    $entity->setTitle($data['title']);
                }
                if ($data['description'] !== null) {
                    $entity->setDescription($data['description']);
                }
                if ($data['address'] !== null) {
                    $found = $this->service->findOneUnique(
                        $data['parent'] ?? $entity->getParent()->toString(),
                        $data['address'] ?? $entity->getAddress(),
                        $data['external_id'] ?? $entity->getExternalId()
                    );

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
                if ($data['attributes'] !== null) {
                    $entity->setAttributes($data['attributes']);
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
                if ($data['sort'] !== null) {
                    $entity->setSort($data['sort']);
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
     */
    public function delete($entity): bool
    {
        switch (true) {
            case is_string($entity) && \Ramsey\Uuid\Uuid::isValid($entity):
            case is_object($entity) && is_a($entity, Uuid::class):
                $entity = $this->service->findOneByUuid((string) $entity);

                break;
        }

        if (is_object($entity) && is_a($entity, Category::class)) {
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

        throw new CategoryNotFoundException();
    }
}
