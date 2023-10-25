<?php declare(strict_types=1);

namespace App\Domain\Service\Catalog;

use App\Domain\AbstractService;
use App\Domain\Entities\Catalog\Category as CatalogCategory;
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
        $this->service = $this->entityManager->getRepository(CatalogCategory::class);
    }

    /**
     * @throws MissingTitleValueException
     * @throws AddressAlreadyExistsException
     */
    public function create(array $data = []): CatalogCategory
    {
        $default = [
            'parent' => null,
            'parent_uuid' => null,
            'children' => false,
            'hidden' => false,
            'title' => '',
            'description' => '',
            'address' => '',
            'attributes' => [],
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
            'system' => '',
        ];
        $data = array_merge($default, $data);

        if (!$data['title']) {
            throw new MissingTitleValueException();
        }

        // retrieve category by uuid
        if (!is_a($data['parent'], CatalogCategory::class) && $data['parent_uuid']) {
            $data['parent'] = $this->read(['uuid' => $data['parent_uuid']]);

            // copy attributes from parent
            if ($data['parent']->hasAttributes()) {
                $data['attributes'] = array_merge(
                    from_service_to_array($data['parent']->getAttributes()),
                    $data['attributes']
                );
            }
        }

        $category = (new CatalogCategory())
            ->setParent($data['parent'])
            ->setChildren($data['children'])
            ->setHidden($data['hidden'])
            ->setDescription($data['description'])
            ->setTitle($data['title'])
            ->setAddress($data['address'])
            ->setAttributes($data['attributes'])
            ->setStatus($data['status'])
            ->setPagination((int) $data['pagination'])
            ->setOrder((int) $data['order'])
            ->setSort($data['sort'])
            ->setMeta($data['meta'])
            ->setTemplate($data['template'])
            ->setExternalId($data['external_id'])
            ->setExport($data['export'])
            ->setSystem($data['system']);

        // if address generation is enabled
        if ($this->parameter('common_auto_generate_address', 'no') === 'yes') {
            $category->setAddress(
                implode('/', array_filter(
                    [
                        $category->getParent()?->getAddress(),
                        $category->setAddress('')->getAddress(),
                    ],
                    fn ($el) => (bool) $el
                ))
            );
        }

        $found = $this->service->findOneUnique(
            $category->getParent()?->getUuid()->toString(),
            $category->getAddress(),
            $category->getExternalId()
        );
        if ($found !== null) {
            throw new AddressAlreadyExistsException();
        }

        $this->entityManager->persist($category);
        $this->entityManager->flush();

        return $category;
    }

    /**
     * @throws CategoryNotFoundException
     *
     * @return CatalogCategory|Collection
     */
    public function read(array $data = [])
    {
        $default = [
            'parent_uuid' => '',
            'uuid' => null,
            'children' => null,
            'hidden' => null,
            'title' => null,
            'address' => null,
            'status' => null,
            'external_id' => null,
            'export' => null,
        ];
        $data = array_merge($default, static::$default_read, $data);

        $criteria = [];

        if ($data['parent_uuid'] !== '') {
            $criteria['parent_uuid'] = $data['parent_uuid'];
        }
        if ($data['uuid'] !== null) {
            $criteria['uuid'] = $data['uuid'];
        }
        if ($data['children'] !== null) {
            $criteria['children'] = $data['children'];
        }
        if ($data['hidden'] !== null) {
            $criteria['hidden'] = $data['hidden'];
        }
        if ($data['title'] !== null) {
            $criteria['title'] = $data['title'];
        }
        if ($data['address'] !== null) {
            $criteria['address'] = $data['address'];
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
     * @param CatalogCategory|string|Uuid $entity
     *
     * @throws AddressAlreadyExistsException
     * @throws CategoryNotFoundException
     */
    public function update($entity, array $data = []): CatalogCategory
    {
        switch (true) {
            case is_string($entity) && \Ramsey\Uuid\Uuid::isValid($entity):
            case is_object($entity) && is_a($entity, Uuid::class):
                $entity = $this->service->findOneByUuid((string) $entity);

                break;
        }

        if (is_object($entity) && is_a($entity, CatalogCategory::class)) {
            $default = [
                'parent' => null,
                'parent_uuid' => null,
                'children' => null,
                'hidden' => null,
                'title' => null,
                'description' => null,
                'address' => null,
                'attributes' => null,
                'status' => null,
                'pagination' => null,
                'order' => null,
                'sort' => null,
                'meta' => null,
                'template' => null,
                'external_id' => null,
                'export' => null,
                'system' => null,
            ];
            $data = array_merge($default, $data);

            if ($data !== $default) {
                if ($data['parent'] !== null || $data['parent_uuid'] !== null) {
                    // retrieve category by uuid
                    if (!is_a($data['parent'], CatalogCategory::class) && $data['parent_uuid']) {
                        $data['parent'] = $this->read(['uuid' => $data['parent_uuid']]);
                    }

                    $entity->setParent($data['parent']);
                }
                if ($data['children'] !== null) {
                    $entity->setChildren($data['children']);
                }
                if ($data['hidden'] !== null) {
                    $entity->setHidden($data['hidden']);
                }
                if ($data['title'] !== null) {
                    $entity->setTitle($data['title']);
                }
                if ($data['description'] !== null) {
                    $entity->setDescription($data['description']);
                }
                if ($data['attributes'] !== null) {
                    $entity->setAttributes($data['attributes']);
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
                if ($data['system'] !== null) {
                    $entity->setSystem($data['system']);
                }
                // if address generation is enabled
                if ($this->parameter('common_auto_generate_address', 'no') === 'yes') {
                    $data['address'] = implode('/', array_filter(
                        [
                            $entity->getParent()?->getAddress(),
                            $entity->setAddress('')->getAddress(),
                        ],
                        fn ($el) => (bool) $el
                    ));
                }
                if ($data['address'] !== null) {
                    $found = $this->service->findOneUnique(
                        $entity->getParent()?->getUuid()->toString(),
                        $entity->getAddress(),
                        $entity->getExternalId()
                    );

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

        throw new CategoryNotFoundException();
    }

    /**
     * @param CatalogCategory|string|Uuid $entity
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

        if (is_object($entity) && is_a($entity, CatalogCategory::class)) {
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
