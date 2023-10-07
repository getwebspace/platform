<?php declare(strict_types=1);

namespace App\Domain\Service\Catalog;

use App\Domain\AbstractService;
use App\Domain\Entities\Catalog\Category as CatalogCategory;
use App\Domain\Entities\Catalog\Product;
use App\Domain\Repository\Catalog\ProductRepository;
use App\Domain\Service\Catalog\CategoryService as CatalogCategoryService;
use App\Domain\Service\Catalog\Exception\AddressAlreadyExistsException;
use App\Domain\Service\Catalog\Exception\MissingCategoryValueException;
use App\Domain\Service\Catalog\Exception\MissingTitleValueException;
use App\Domain\Service\Catalog\Exception\ProductNotFoundException;
use App\Domain\Service\Catalog\ProductAttributeService as CatalogProductAttributeService;
use App\Domain\Service\Catalog\ProductRelationService as CatalogProductRelationService;
use Illuminate\Support\Collection;
use Ramsey\Uuid\UuidInterface as Uuid;

class ProductService extends AbstractService
{
    /**
     * @var ProductRepository
     */
    protected mixed $service;

    protected CatalogCategoryService $catalogCategoryService;

    protected CatalogProductAttributeService $catalogProductAttributeService;

    protected CatalogProductRelationService $catalogProductRelationService;

    protected function init(): void
    {
        $this->service = $this->entityManager->getRepository(Product::class);
        $this->catalogCategoryService = $this->container->get(CatalogCategoryService::class);
        $this->catalogProductAttributeService = $this->container->get(CatalogProductAttributeService::class);
        $this->catalogProductRelationService = $this->container->get(CatalogProductRelationService::class);
    }

    /**
     * @throws MissingTitleValueException
     * @throws AddressAlreadyExistsException
     */
    public function create(array $data = []): Product
    {
        $default = [
            'category' => null,
            'category_uuid' => null,
            'title' => '',
            'type' => \App\Domain\Types\Catalog\ProductTypeType::TYPE_PRODUCT,
            'description' => '',
            'extra' => '',
            'address' => '',
            'vendorcode' => '',
            'barcode' => '',
            'tax' => 0.0,
            'priceFirst' => 0.0,
            'price' => 0.0,
            'priceWholesale' => 0.0,
            'priceWholesaleFrom' => 0,
            'discount' => 0.0,
            'special' => false,
            'dimension' => [],
            'quantity' => 1.0,
            'quantityMin' => 1.0,
            'stock' => 0.0,
            'status' => \App\Domain\Types\Catalog\ProductStatusType::STATUS_WORK,
            'country' => '',
            'manufacturer' => '',
            'tags' => [],
            'order' => 1,
            'date' => 'now',
            'meta' => [
                'title' => '',
                'description' => '',
                'keywords' => '',
            ],
            'external_id' => '',
            'export' => 'manual',

            'attributes' => [],
            'relation' => [],
        ];
        $data = array_merge($default, $data);

        if (!$data['title']) {
            throw new MissingTitleValueException();
        }
        if (!$data['category'] && !$data['category_uuid']) {
            throw new MissingCategoryValueException();
        }

        // retrieve category by uuid
        if (!is_a($data['category'], CatalogCategory::class) && $data['category_uuid']) {
            $data['category'] = $this->catalogCategoryService->read(['uuid' => $data['category_uuid']]);
        }

        $product = (new Product())
            ->setCategory($data['category'])
            ->setTitle($data['title'])
            ->setType($data['type'])
            ->setDescription($data['description'])
            ->setExtra($data['extra'])
            ->setAddress($data['address'])
            ->setVendorCode($data['vendorcode'])
            ->setBarCode($data['barcode'])
            ->setTax((float) $data['tax'])
            ->setPriceFirst((float) $data['priceFirst'])
            ->setPrice((float) $data['price'])
            ->setPriceWholesale((float) $data['priceWholesale'])
            ->setPriceWholesaleFrom((float) $data['priceWholesaleFrom'])
            ->setDiscount((float) $data['discount'])
            ->setSpecial($data['special'])
            ->setDimension($data['dimension'])
            ->setQuantity((float) $data['quantity'])
            ->setQuantityMin((float) $data['quantityMin'])
            ->setStock((float) $data['stock'])
            ->setStatus($data['status'])
            ->setCountry($data['country'])
            ->setManufacturer($data['manufacturer'])
            ->setTags($data['tags'])
            ->setOrder((int) $data['order'])
            ->setDate($data['date'], $this->parameter('common_timezone', 'UTC'))
            ->setMeta($data['meta'])
            ->setExternalId($data['external_id'])
            ->setExport($data['export']);

        // if address generation is enabled
        if ($this->parameter('common_auto_generate_address', 'no') === 'yes') {
            $product->setAddress(
                implode('/', array_filter(
                    [
                        $product->getCategory()->getAddress(),
                        $product->setAddress('')->getAddress(),
                    ],
                    fn ($el) => (bool) $el
                ))
            );
        }

        /** @var Product $product */
        if (
            $this->service->findOneUnique(
                $product->getCategory()->getUuid()->toString(),
                $product->getAddress(),
                $product->getDimension(),
                $product->getExternalId()
            ) !== null
        ) {
            throw new AddressAlreadyExistsException();
        }

        $this->entityManager->persist($product);

        // add attributes
        $this->catalogProductAttributeService->process($product, $data['attributes']);

        // add relation products
        $this->catalogProductRelationService->process($product, $data['relation']);

        $this->entityManager->flush();

        return $product;
    }

    /**
     * @throws ProductNotFoundException
     *
     * @return Collection|Product
     */
    public function read(array $data = [])
    {
        $default = [
            'uuid' => null,
            'category' => null,
            'title' => null,
            'type' => null,
            'address' => null,
            'vendorcode' => null,
            'barcode' => null,
            'special' => null,
            'status' => null,
            'external_id' => null,
            'export' => null,
        ];
        $data = array_merge($default, static::$default_read, $data);

        $criteria = [];

        if ($data['uuid'] !== null) {
            $criteria['uuid'] = $data['uuid'];
        }
        if ($data['category'] !== null) {
            $criteria['category'] = $data['category'];
        }
        if ($data['title'] !== null) {
            $criteria['title'] = $data['title'];
        }
        if ($data['type'] !== null) {
            $criteria['type'] = $data['type'];
        }
        if ($data['address'] !== null) {
            $criteria['address'] = $data['address'];
        }
        if ($data['vendorcode'] !== null) {
            $criteria['vendorcode'] = $data['vendorcode'];
        }
        if ($data['barcode'] !== null) {
            $criteria['barcode'] = $data['barcode'];
        }
        if ($data['special'] !== null) {
            $criteria['special'] = (bool) $data['special'];
        }
        if ($data['status'] !== null && in_array($data['status'], \App\Domain\Types\Catalog\ProductStatusType::LIST, true)) {
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
                case !is_array($data['address']) && $data['address'] !== null:
                case !is_array($data['vendorcode']) && $data['vendorcode'] !== null:
                case !is_array($data['barcode']) && $data['barcode'] !== null:
                case !is_array($data['external_id']) && $data['external_id'] !== null:
                    $product = $this->service->findOneBy($criteria);

                    if (empty($product)) {
                        throw new ProductNotFoundException();
                    }

                    return $product;

                case !is_array($data['title']) && $data['title'] !== null:
                    return collect($this->service->findByTitle($data['title'], $data['limit'], $data['offset']));

                default:
                    return collect($this->service->findBy($criteria, $data['order'], $data['limit'], $data['offset']));
            }
        } catch (\Doctrine\DBAL\Exception\TableNotFoundException $e) {
            return null;
        }
    }

    /**
     * @param Product|string|Uuid $entity
     *
     * @throws AddressAlreadyExistsException
     * @throws ProductNotFoundException
     */
    public function update($entity, array $data = []): Product
    {
        switch (true) {
            case is_string($entity) && \Ramsey\Uuid\Uuid::isValid($entity):
            case is_object($entity) && is_a($entity, Uuid::class):
                $entity = $this->service->findOneByUuid((string) $entity);

                break;
        }

        if (is_object($entity) && is_a($entity, Product::class)) {
            $default = [
                'category' => null,
                'category_uuid' => null,
                'title' => null,
                'type' => null,
                'description' => null,
                'extra' => null,
                'address' => null,
                'vendorcode' => null,
                'barcode' => null,
                'tax' => null,
                'priceFirst' => null,
                'price' => null,
                'priceWholesale' => null,
                'priceWholesaleFrom' => null,
                'discount' => null,
                'special' => null,
                'dimension' => null,
                'quantity' => null,
                'quantityMin' => null,
                'stock' => null,
                'status' => null,
                'country' => null,
                'manufacturer' => null,
                'tags' => null,
                'order' => null,
                'meta' => null,
                'external_id' => null,
                'export' => null,

                'attributes' => null,
                'relation' => null,
            ];
            $data = array_merge($default, $data);

            if ($data !== $default) {
                if ($data['category'] !== null || $data['category_uuid'] !== null) {
                    // retrieve category by uuid
                    if (!is_a($data['category'], CatalogCategory::class) && $data['category_uuid']) {
                        $data['category'] = $this->catalogCategoryService->read(['uuid' => $data['category_uuid']]);
                    }

                    $entity->setCategory($data['category']);
                }
                if ($data['title'] !== null) {
                    $entity->setTitle($data['title']);
                }
                if ($data['type'] !== null) {
                    $entity->setType($data['type']);
                }
                if ($data['description'] !== null) {
                    $entity->setDescription($data['description']);
                }
                if ($data['extra'] !== null) {
                    $entity->setExtra($data['extra']);
                }
                if ($data['vendorcode'] !== null) {
                    $entity->setVendorCode($data['vendorcode']);
                }
                if ($data['barcode'] !== null) {
                    $entity->setBarCode($data['barcode']);
                }
                if ($data['tax'] !== null) {
                    $entity->setTax((float) $data['tax']);
                }
                if ($data['priceFirst'] !== null) {
                    $entity->setPriceFirst((float) $data['priceFirst']);
                }
                if ($data['price'] !== null) {
                    $entity->setPrice((float) $data['price']);
                }
                if ($data['priceWholesale'] !== null) {
                    $entity->setPriceWholesale((float) $data['priceWholesale']);
                }
                if ($data['priceWholesaleFrom'] !== null) {
                    $entity->setPriceWholesaleFrom((float) $data['priceWholesaleFrom']);
                }
                if ($data['discount'] !== null) {
                    $entity->setDiscount((float) $data['discount']);
                }
                if ($data['special'] !== null) {
                    $entity->setSpecial($data['special']);
                }
                if ($data['dimension'] !== null) {
                    $entity->setDimension($data['dimension']);
                }
                if ($data['quantity'] !== null) {
                    $entity->setQuantity((float) $data['quantity']);
                }
                if ($data['quantityMin'] !== null) {
                    $entity->setQuantityMin((float) $data['quantityMin']);
                }
                if ($data['stock'] !== null) {
                    $entity->setStock((float) $data['stock']);
                }
                if ($data['status'] !== null) {
                    $entity->setStatus($data['status']);
                }
                if ($data['country'] !== null) {
                    $entity->setCountry($data['country']);
                }
                if ($data['manufacturer'] !== null) {
                    $entity->setManufacturer($data['manufacturer']);
                }
                if ($data['tags'] !== null) {
                    $entity->setTags($data['tags']);
                }
                if ($data['order'] !== null) {
                    $entity->setOrder((int) $data['order']);
                }
                if ($data['meta'] !== null) {
                    $entity->setMeta($data['meta']);
                }
                if ($data['external_id'] !== null) {
                    $entity->setExternalId($data['external_id']);
                }
                if ($data['export'] !== null) {
                    $entity->setExport($data['export']);
                }
                if ($data['attributes'] !== null) {
                    // update attributes
                    $this->catalogProductAttributeService->process($entity, $data['attributes']);
                }
                if ($data['relation'] !== null) {
                    // update relation products
                    $this->catalogProductRelationService->process($entity, $data['relation']);
                }
                // if address generation is enabled
                if ($this->parameter('common_auto_generate_address', 'no') === 'yes') {
                    $data['address'] = implode('/', array_filter(
                        [
                            $entity->getCategory()->getAddress(),
                            $entity->setAddress('')->getAddress(),
                        ],
                        fn ($el) => (bool) $el
                    ));
                }
                if ($data['address'] !== null) {
                    $found = $this->service->findOneUnique(
                        $entity->getCategory()->getUuid()->toString(),
                        $entity->getAddress(),
                        $entity->getDimension(),
                        $entity->getExternalId()
                    );

                    if ($found === null || $found === $entity) {
                        $entity->setAddress($data['address']);
                    } else {
                        throw new AddressAlreadyExistsException();
                    }
                }

                $entity->setDate('now', $this->parameter('common_timezone', 'UTC'));

                $this->entityManager->flush();
            }

            return $entity;
        }

        throw new ProductNotFoundException();
    }

    /**
     * @param Product|string|Uuid $entity
     *
     * @throws ProductNotFoundException
     */
    public function delete($entity): bool
    {
        switch (true) {
            case is_string($entity) && \Ramsey\Uuid\Uuid::isValid($entity):
            case is_object($entity) && is_a($entity, Uuid::class):
                $entity = $this->service->findOneByUuid((string) $entity);

                break;
        }

        if (is_object($entity) && is_a($entity, Product::class)) {
            $this->entityManager->remove($entity);
            $this->entityManager->flush();

            return true;
        }

        throw new ProductNotFoundException();
    }
}
