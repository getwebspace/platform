<?php declare(strict_types=1);

namespace App\Domain\Service\Catalog;

use App\Domain\AbstractService;
use App\Domain\Entities\Catalog\Attribute;
use App\Domain\Entities\Catalog\Product;
use App\Domain\Entities\Catalog\ProductAttribute;
use App\Domain\Repository\Catalog\AttributeRepository;
use App\Domain\Service\Catalog\Exception\AttributeNotFoundException;
use Ramsey\Uuid\Uuid;

class ProductAttributeService extends AbstractService
{
    /**
     * @var AttributeRepository
     */
    protected $catalogAttributeService;

    protected function init(): void
    {
        $this->catalogAttributeService = $this->entityManager->getRepository(Attribute::class);
    }

    public function proccess(Product $product, array $attributes): Product
    {
        foreach ($product->getAttributes() as $attribute) {
            $this->delete($attribute);
        }

        foreach ($attributes as $unique => $value) {
            if ($value) {
                $this->create([
                    'product' => $product,
                    'attribute' => Uuid::isValid($unique) ? $this->catalogAttributeService->findOneByUuid($unique) : $this->catalogAttributeService->findOneByAddress($unique),
                    'value' => $value,
                ]);
            }
        }

        return $product;
    }

    /**
     * @param array $data
     *
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

        $productAttribute = (new ProductAttribute)
            ->setProduct($data['product'])
            ->setAttribute($data['attribute'])
            ->setValue($data['value']);

        $this->entityManager->persist($productAttribute);
        $this->entityManager->flush();

        return $productAttribute;
    }

    public function read(array $data = []): void
    {
        throw new \RuntimeException('Unused method');
    }

    /**
     * @param       $entity
     * @param array $data
     */
    public function update($entity, array $data = []): void
    {
        throw new \RuntimeException('Unused method');
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
            case is_string($entity) && Uuid::isValid($entity):
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
