<?php declare(strict_types=1);

namespace App\Domain\Service\Catalog;

use App\Domain\AbstractService;
use App\Domain\Entities\Catalog\Order;
use App\Domain\Entities\Catalog\OrderProduct;
use App\Domain\Entities\Catalog\Product;
use App\Domain\Repository\Catalog\ProductRepository;
use App\Domain\Service\Catalog\Exception\RelationNotFoundException;
use App\Domain\Service\Catalog\ProductService as CatalogProductService;
use Ramsey\Uuid\UuidInterface as Uuid;

class OrderProductService extends AbstractService
{
    /**
     * @var ProductRepository
     */
    protected mixed $service;

    protected CatalogProductService $catalogProductService;

    protected function init(): void
    {
        $this->service = $this->entityManager->getRepository(OrderProduct::class);
        $this->catalogProductService = $this->container->get(CatalogProductService::class);
    }

    public function process(Order $order, array $products): void
    {
        foreach ($order->getProducts() as $product) {
            $this->delete($product);
        }

        foreach ($products as $uuid => $opts) {
            $type = ($opts['price_type'] ?? 'price');
            $count = (float) ($opts['count'] ?? 0);
            $price = (float) ($opts['price'] ?? 0);

            if ($count > 0) {
                try {
                    $product = $this->catalogProductService->read(['uuid' => $uuid]);

                    $price = match ($type) {
                        \App\Domain\References\Catalog::PRODUCT_PRICE_TYPE_PRICE => $product->getPrice(),
                        \App\Domain\References\Catalog::PRODUCT_PRICE_TYPE_PRICE_WHOLESALE => $product->getPriceWholesale(),
                        \App\Domain\References\Catalog::PRODUCT_PRICE_TYPE_PRICE_SELF => $price,
                    };

                    $order->addProduct(
                        $this->create([
                            'order' => $order,
                            'product' => $product,
                            'price' => $price,
                            'price_type' => $type,
                            'count' => $count,
                            'discount' => $product->getDiscount(),
                            'tax' => $product->getTax(),
                        ])
                    );
                } catch (Exception\ProductNotFoundException $e) {
                    // skip
                }
            }
        }
    }

    public function create(array $data = []): OrderProduct
    {
        $default = [
            'order' => '',
            'product' => '',
            'price' => 0.0,
            'price_type' => 'price',
            'count' => 1,
            'discount' => 0.0,
            'tax' => 0.0,
        ];
        $data = array_merge($default, $data);

        $productRelation = (new OrderProduct())
            ->setOrder($data['order'])
            ->setProduct($data['product'])
            ->setPrice($data['price'])
            ->setPriceType($data['price_type'])
            ->setCount($data['count'])
            ->setDiscount($data['discount'])
            ->setTax($data['tax']);

        // trigger populate fields
        $productRelation->_populate_fields();

        $this->entityManager->persist($productRelation);
        $this->entityManager->flush();

        return $productRelation;
    }

    public function read(array $data = []): void
    {
        throw new \RuntimeException('Unused method');
    }

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
    public function delete($entity): true
    {
        switch (true) {
            case is_string($entity) && \Ramsey\Uuid\Uuid::isValid($entity):
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
