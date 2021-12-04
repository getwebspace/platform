<?php declare(strict_types=1);

namespace App\Domain\Service\Catalog;

use App\Domain\AbstractService;
use App\Domain\Entities\Catalog\Product;
use App\Domain\Entities\Catalog\ProductAttribute;
use App\Domain\Entities\Catalog\ProductRelation;
use App\Domain\Repository\Catalog\ProductRepository;
use App\Domain\Service\Catalog\Exception\RelationNotFoundException;
use Ramsey\Uuid\UuidInterface as Uuid;

class ProductRelationService extends AbstractService
{
    /**
     * @var ProductRepository
     */
    protected $catalogProductService;

    protected function init(): void
    {
        $this->catalogProductService = $this->entityManager->getRepository(Product::class);
    }

    public function proccess(Product $product, array $relations): array
    {
        foreach ($product->getRelations() as $relation) {
            $this->delete($relation);
        }

        $result = [];

        foreach ($relations as $uuid => $count) {
            if ($count >= 1) {
                $result[] = $this->create([
                    'product' => $product,
                    'related' => $this->catalogProductService->findOneByUuid($uuid),
                    'count' => (float) $count,
                ]);
            }
        }

        return $result;
    }

    /**
     * @return ProductRelation
     */
    public function create(array $data = [])
    {
        $default = [
            'product' => '',
            'related' => '',
            'count' => 1,
        ];
        $data = array_merge($default, $data);

        $productRelation = (new ProductRelation())
            ->setProduct($data['product'])
            ->setRelated($data['related'])
            ->setCount($data['count']);

        $this->entityManager->persist($productRelation);
        $this->entityManager->flush();

        return $productRelation;
    }

    public function read(array $data = []): void
    {
        throw new \RuntimeException('Unused method');
    }

    /**
     * @param       $entity
     */
    public function update($entity, array $data = []): void
    {
        throw new \RuntimeException('Unused method');
    }

    /**
     * @param ProductAttribute|string|Uuid $entity
     *
     * @throws RelationNotFoundException
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

        if (is_object($entity) && is_a($entity, ProductRelation::class)) {
            $this->entityManager->remove($entity);
            $this->entityManager->flush();

            return true;
        }

        throw new RelationNotFoundException();
    }
}
