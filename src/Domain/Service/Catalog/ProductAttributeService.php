<?php declare(strict_types=1);

namespace App\Domain\Service\Catalog;

use App\Domain\AbstractService;
use App\Domain\Entities\Catalog\Attribute;
use App\Domain\Entities\Catalog\Product;
use App\Domain\Entities\Catalog\ProductAttribute;
use App\Domain\Repository\Catalog\AttributeRepository;
use App\Domain\Service\Catalog\Exception\AttributeNotFoundException;
use Ramsey\Uuid\UuidInterface as Uuid;

class ProductAttributeService extends AbstractService
{
    /**
     * @var AttributeRepository
     */
    protected mixed $service;

    protected function init(): void
    {
        $this->service = $this->entityManager->getRepository(Attribute::class);
    }

    public function process(Product $product, array $attributes, bool $update_only = false): Product
    {
        if ($update_only === false) {
            foreach ($product->getAttributes() as $attribute) {
                $this->delete($attribute);
            }

            foreach ($attributes as $unique => $value) {
                if ($value) {
                    $this->create([
                        'product' => $product,
                        'attribute' => \Ramsey\Uuid\Uuid::isValid((string) $unique) ? $this->service->findOneByUuid($unique) : $this->service->findOneByAddress($unique),
                        'value' => $value,
                    ]);
                }
            }
        } else {
            foreach ($attributes as $unique => $value) {
                $attribute = $product->getAttributes()->firstWhere('address', $unique) ?? $product->getAttributes()->firstWhere('uuid', $unique) ?? null;

                if ($attribute) {
                    $this->update($attribute, ['value' => $value]);
                } else {
                    $this->create([
                        'product' => $product,
                        'attribute' => \Ramsey\Uuid\Uuid::isValid((string) $unique) ? $this->service->findOneByUuid($unique) : $this->service->findOneByAddress($unique),
                        'value' => $value,
                    ]);
                }
            }
        }

        return $product;
    }

    /**
     * @return ProductAttribute
     */
    public function create(array $data = [])
    {
        $default = [
            'product' => '',
            'attribute' => '',
            'value' => '',
        ];
        $data = array_merge($default, $data);

        $productAttribute = (new ProductAttribute())
            ->setProduct($data['product'])
            ->setAttribute($data['attribute'])
            ->setValue($data['value']);

        // trigger populate fields
        $productAttribute->_populate_fields();

        $this->entityManager->persist($productAttribute);
        $this->entityManager->flush();

        return $productAttribute;
    }

    public function read(array $data = []): void
    {
        throw new \RuntimeException('Unused method');
    }

    /**
     * @param mixed $entity
     *
     * @throws AttributeNotFoundException
     */
    public function update($entity, array $data = []): ProductAttribute
    {
        switch (true) {
            case is_string($entity) && \Ramsey\Uuid\Uuid::isValid($entity):
            case is_object($entity) && is_a($entity, Uuid::class):
                $entity = $this->service->findOneByUuid((string) $entity);

                break;
        }

        if (is_object($entity) && is_a($entity, ProductAttribute::class)) {
            $default = [
                'product' => null,
                'attribute' => null,
                'value' => null,
            ];
            $data = array_merge($default, $data);

            if ($data !== $default) {
                if ($data['product'] !== null) {
                    $entity->setProduct($data['product']);
                }
                if ($data['attribute'] !== null) {
                    $entity->setAttribute($data['attribute']);
                }
                if ($data['value'] !== null) {
                    $entity->setValue($data['value']);
                }

                $this->entityManager->flush();
            }

            return $entity;
        }

        throw new AttributeNotFoundException();
    }

    /**
     * @param ProductAttribute|string|Uuid $entity
     *
     * @throws AttributeNotFoundException
     *
     * @return bool
     */
    public function delete($entity)
    {
        switch (true) {
            case is_string($entity) && \Ramsey\Uuid\Uuid::isValid($entity):
            case is_object($entity) && is_a($entity, Uuid::class):
                $entity = $this->service->findOneByUuid((string) $entity);

                break;
        }

        if (is_object($entity) && is_a($entity, ProductAttribute::class)) {
            $this->entityManager->remove($entity);
            $this->entityManager->flush();

            return true;
        }

        throw new AttributeNotFoundException();
    }
}
