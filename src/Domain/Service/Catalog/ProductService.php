<?php declare(strict_types=1);

namespace App\Domain\Service\Catalog;

use App\Domain\AbstractService;
use App\Domain\Entities\Catalog\Product;
use App\Domain\Repository\Catalog\ProductRepository;
use App\Domain\Service\Catalog\CategoryService as CatalogCategoryService;
use App\Domain\Service\Catalog\Exception\AddressAlreadyExistsException;
use App\Domain\Service\Catalog\Exception\CategoryNotFoundException;
use App\Domain\Service\Catalog\Exception\MissingTitleValueException;
use App\Domain\Service\Catalog\Exception\ProductNotFoundException;
use Illuminate\Support\Collection;
use Ramsey\Uuid\Uuid;

class ProductService extends AbstractService
{
    /**
     * @var ProductRepository
     */
    protected $service;

    protected function init(): void
    {
        $this->service = $this->entityManager->getRepository(Product::class);
    }

    /**
     * @param array $data
     *
     * @throws MissingTitleValueException
     * @throws AddressAlreadyExistsException
     *
     * @return Product
     */
    public function create(array $data = []): Product
    {
        $default = [
            'category' => \Ramsey\Uuid\Uuid::NIL,
            'title' => '',
            'type' => \App\Domain\Types\Catalog\ProductTypeType::TYPE_PRODUCT,
            'description' => '',
            'extra' => '',
            'address' => '',
            'vendorcode' => '',
            'barcode' => '',
            'priceFirst' => 0.0,
            'price' => 0.0,
            'priceWholesale' => 0.0,
            'volume' => 0.0,
            'unit' => '',
            'stock' => 0.0,
            'field1' => '',
            'field2' => '',
            'field3' => '',
            'field4' => '',
            'field5' => '',
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
        ];
        $data = array_merge($default, $data);

        if (!$data['title']) {
            throw new MissingTitleValueException();
        }

        $product = (new Product)
            ->setCategory($data['category'])
            ->setTitle($data['title'])
            ->setType($data['type'])
            ->setDescription($data['description'])
            ->setExtra($data['extra'])
            ->setAddress($data['address'])
            ->setVendorCode($data['vendorcode'])
            ->setBarCode($data['barcode'])
            ->setPriceFirst((float) $data['priceFirst'])
            ->setPrice((float) $data['price'])
            ->setPriceWholesale((float) $data['priceWholesale'])
            ->setVolume((float) $data['volume'])
            ->setUnit($data['unit'])
            ->setStock((float) $data['stock'])
            ->setField1($data['field1'])
            ->setField2($data['field2'])
            ->setField3($data['field3'])
            ->setField4($data['field4'])
            ->setField5($data['field5'])
            ->setStatus($data['status'])
            ->setCountry($data['country'])
            ->setManufacturer($data['manufacturer'])
            ->setTags($data['tags'])
            ->setOrder((int) $data['order'])
            ->setDate($data['date'])
            ->setMeta($data['meta'])
            ->setExternalId($data['external_id'])
            ->setExport($data['export']);

        // if address generation is enabled
        if ($this->parameter('common_auto_generate_address', 'no') === 'yes' && Uuid::isValid($data['category'])) {
            try {
                $catalogCategoryService = CatalogCategoryService::getWithContainer($this->container);
                $catalogCategory = $catalogCategoryService->read(['uuid' => $data['category']]);

                // combine address category with product address
                $product->setAddress(
                    implode('/', [$catalogCategory->getAddress(), $product->setAddress('')->getAddress()])
                );
            } catch (CategoryNotFoundException $e) {
                // nothing
            }
        }

        if ($this->service->findOneBy(['category' => $product->getCategory(), 'address' => $product->getAddress()]) !== null) {
            throw new AddressAlreadyExistsException();
        }

        $this->entityManager->persist($product);
        $this->entityManager->flush();

        return $product;
    }

    /**
     * @param array $data
     *
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
            'field1' => null,
            'field2' => null,
            'field3' => null,
            'field4' => null,
            'field5' => null,
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
        if ($data['field1'] !== null) {
            $criteria['field1'] = $data['field1'];
        }
        if ($data['field2'] !== null) {
            $criteria['field2'] = $data['field2'];
        }
        if ($data['field3'] !== null) {
            $criteria['field3'] = $data['field3'];
        }
        if ($data['field4'] !== null) {
            $criteria['field4'] = $data['field4'];
        }
        if ($data['field5'] !== null) {
            $criteria['field5'] = $data['field5'];
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
                case !is_array($data['title']) && $data['title'] !== null:
                case !is_array($data['address']) && $data['address'] !== null:
                case !is_array($data['vendorcode']) && $data['vendorcode'] !== null:
                case !is_array($data['barcode']) && $data['barcode'] !== null:
                case !is_array($data['external_id']) && $data['external_id'] !== null:
                    $product = $this->service->findOneBy($criteria);

                    if (empty($product)) {
                        throw new ProductNotFoundException();
                    }

                    return $product;

                default:
                    return collect($this->service->findBy($criteria, $data['order'], $data['limit'], $data['offset']));
            }
        } catch (\Doctrine\DBAL\Exception\TableNotFoundException $e) {
            return null;
        }
    }

    /**
     * @param Product|string|Uuid $entity
     * @param array               $data
     *
     * @throws AddressAlreadyExistsException
     * @throws ProductNotFoundException
     *
     * @return Product
     */
    public function update($entity, array $data = []): Product
    {
        switch (true) {
            case is_string($entity) && Uuid::isValid($entity):
            case is_object($entity) && is_a($entity, Uuid::class):
                $entity = $this->service->findOneByUuid((string) $entity);

                break;
        }

        if (is_object($entity) && is_a($entity, Product::class)) {
            $default = [
                'category' => null,
                'title' => null,
                'type' => null,
                'description' => null,
                'extra' => null,
                'address' => null,
                'vendorcode' => null,
                'barcode' => null,
                'priceFirst' => null,
                'price' => null,
                'priceWholesale' => null,
                'volume' => null,
                'unit' => null,
                'stock' => null,
                'field1' => null,
                'field2' => null,
                'field3' => null,
                'field4' => null,
                'field5' => null,
                'status' => null,
                'country' => null,
                'manufacturer' => null,
                'tags' => null,
                'order' => null,
                'date' => null,
                'meta' => null,
                'external_id' => null,
                'export' => null,
            ];
            $data = array_merge($default, $data);

            if ($data !== $default) {
                if ($data['category'] !== null) {
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
                if ($data['address'] !== null) {
                    $found = $this->service->findOneByAddress($data['address']);

                    if ($found === null || $found === $entity) {
                        $entity->setAddress($data['address']);
                    } else {
                        throw new AddressAlreadyExistsException();
                    }
                }
                if ($data['vendorcode'] !== null) {
                    $entity->setVendorCode($data['vendorcode']);
                }
                if ($data['barcode'] !== null) {
                    $entity->setBarCode($data['barcode']);
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
                if ($data['volume'] !== null) {
                    $entity->setVolume((float) $data['volume']);
                }
                if ($data['unit'] !== null) {
                    $entity->setUnit($data['unit']);
                }
                if ($data['stock'] !== null) {
                    $entity->setStock((float) $data['stock']);
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
                if ($data['field4'] !== null) {
                    $entity->setField4($data['field4']);
                }
                if ($data['field5'] !== null) {
                    $entity->setField5($data['field5']);
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
                if ($data['date'] !== null) {
                    $entity->setDate($data['date']);
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

        if (is_object($entity) && is_a($entity, Product::class)) {
            $this->entityManager->remove($entity);
            $this->entityManager->flush();

            return true;
        }

        throw new ProductNotFoundException();
    }
}
