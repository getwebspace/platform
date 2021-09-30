<?php declare(strict_types=1);

namespace App\Domain\Service\Catalog;

use App\Domain\AbstractService;
use App\Domain\Entities\Catalog\Order;
use App\Domain\Entities\Catalog\OrderProduct;
use App\Domain\Entities\Catalog\Product;
use App\Domain\Repository\Catalog\ProductRepository;
use App\Domain\Service\Catalog\Exception\RelationNotFoundException;
use Ramsey\Uuid\Uuid;

class OrderProductService extends AbstractService
{
    /**
     * @var ProductRepository
     */
    protected $catalogProductService;

    protected function init(): void
    {
        $this->catalogProductService = $this->entityManager->getRepository(Product::class);
    }

    public function proccess(Order $order, array $products): array
    {
        foreach ($order->getProducts() as $product) {
            $this->delete($product);
        }

        $result = [];

        foreach ($products as $uuid => $count) {
            if ($count > 0) {
                $result[] = $this->create([
                    'order' => $order,
                    'product' => $this->catalogProductService->findOneByUuid($uuid),
                    'count' => (float) $count,
                ]);
            }
        }

        return $result;
    }

    /**
     * @return OrderProduct
     */
    public function create(array $data = [])
    {
        $default = [
            'product' => '',
            'related' => '',
            'count' => 1,
        ];
        $data = array_merge($default, $data);

        $productRelation = (new OrderProduct())
            ->setOrder($data['order'])
            ->setProduct($data['product'])
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
     * @param Product|string|Uuid $entity
     *
     * @throws RelationNotFoundException
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

        if (is_object($entity) && is_a($entity, OrderProduct::class)) {
            $this->entityManager->remove($entity);
            $this->entityManager->flush();

            return true;
        }

        throw new RelationNotFoundException();
    }
}
