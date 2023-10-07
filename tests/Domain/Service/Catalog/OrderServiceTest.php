<?php declare(strict_types=1);

namespace tests\Domain\Service\Catalog;

use App\Domain\Entities\Catalog\Category;
use App\Domain\Entities\Catalog\Order;
use App\Domain\Entities\Catalog\OrderProduct;
use App\Domain\Entities\Catalog\Product;
use App\Domain\Entities\Reference;
use App\Domain\Repository\Catalog\OrderRepository;
use App\Domain\Service\Catalog\CategoryService;
use App\Domain\Service\Catalog\Exception\OrderNotFoundException;
use App\Domain\Service\Catalog\OrderService;
use App\Domain\Service\Catalog\ProductService;
use App\Domain\Service\Reference\ReferenceService;
use App\Domain\Types\ReferenceTypeType;
use Illuminate\Support\Collection;
use tests\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
class OrderServiceTest extends TestCase
{
    protected OrderService $service;

    public function setUp(): void
    {
        parent::setUp();

        $this->service = $this->getService(OrderService::class);
    }

    protected function getRandomStatus(): Reference
    {
        return $this->getService(ReferenceService::class)->create([
            'type' => ReferenceTypeType::TYPE_ORDER_STATUS,
            'title' => $this->getFaker()->word,
            'order' => $this->getFaker()->randomDigit(),
        ]);
    }

    protected function getRandomCategory(): Category
    {
        return $this->getService(CategoryService::class)->create([
            'title' => implode(' ', $this->getFaker()->words(5)),
        ]);
    }

    protected function getRandomProduct(): Product
    {
        return $this->getService(ProductService::class)->create([
            'category' => $this->getRandomCategory(),
            'title' => $this->getFaker()->word,
            'address' => $this->getFaker()->word,
            'price' => $this->getFaker()->randomFloat(2, 10, 10000),
            'priceWholesale' => $this->getFaker()->randomFloat(2, 10, 10000),
            'priceWholesaleFrom' => $this->getFaker()->randomFloat(1, 10),
        ]);
    }

    public function testCreateSuccess(): void
    {
        $products = [
            $this->getRandomProduct(),
            $this->getRandomProduct(),
            $this->getRandomProduct(),
            $this->getRandomProduct(),
            $this->getRandomProduct(),
            $this->getRandomProduct(),
            $this->getRandomProduct(),
            $this->getRandomProduct(),
            $this->getRandomProduct(),
            $this->getRandomProduct(),
        ];

        $data = [
            'delivery' => [
                'client' => $this->getFaker()->word,
                'address' => $this->getFaker()->text,
            ],
            'phone' => $this->getFaker()->e164PhoneNumber,
            'email' => $this->getFaker()->email,
            'status' => $this->getRandomStatus(),
            'comment' => $this->getFaker()->text,
            'shipping' => $this->getFaker()->dateTime,
            'date' => $this->getFaker()->dateTime,
            'external_id' => $this->getFaker()->word,
            'export' => $this->getFaker()->word,
            'system' => $this->getFaker()->text,

            'products' => [],
        ];

        foreach ($products as $i => $product) {
            $data['products'][$product->getUuid()->toString()] = [
                'count' => $this->getFaker()->randomFloat(2, 2, 10),
                'price' => ($i % 2 === 0 ? $product->getPrice() : $product->getPriceWholesale()),
                'price_type' => ($i % 2 === 0 ? 'price' : 'price_wholesale'),
            ];
        }
        $extra = $this->getRandomProduct();
        $data['products'][$extra->getUuid()->toString()] = [
            'count' => $this->getFaker()->randomFloat(2, 2, 10),
            'price' => $this->getFaker()->randomFloat(2, 2, 10000),
            'price_type' => 'price_self',
        ];

        $order = $this->service->create($data);
        $this->assertInstanceOf(Order::class, $order);
        $this->assertEquals($data['delivery'], $order->getDelivery());
        $this->assertEquals($data['phone'], $order->getPhone());
        $this->assertEquals($data['email'], $order->getEmail());
        $this->assertEquals($data['status'], $order->getStatus());
        $this->assertEquals($data['comment'], $order->getComment());
        $this->assertEquals($data['shipping'], $order->getShipping());
        $this->assertEquals($data['date'], $order->getDate());
        $this->assertEquals($data['external_id'], $order->getExternalId());
        $this->assertEquals($data['export'], $order->getExport());
        $this->assertEquals($data['system'], $order->getSystem());

        foreach ($data['products'] as $uuid => $opts) {
            $op = $order->getProducts()->firstWhere('product.uuid', $uuid);

            $this->assertInstanceOf(OrderProduct::class, $op);
            $this->assertInstanceOf(Product::class, $op->getProduct());

            $this->assertEquals($uuid, $op->getProduct()->getUuid()->toString());
            $this->assertEquals($opts['price'], $op->getPrice());
            $this->assertEquals($opts['price_type'], $op->getPriceType());
        }

        /** @var OrderRepository $orderRepo */
        $orderRepo = $this->em->getRepository(Order::class);
        $o = $orderRepo->findOneByUuid($order->getUuid());
        $this->assertInstanceOf(Order::class, $o);
        $this->assertEquals($data['delivery'], $o->getDelivery());
        $this->assertEquals($data['phone'], $o->getPhone());
        $this->assertEquals($data['email'], $o->getEmail());
        $this->assertEquals($data['status'], $o->getStatus());
        $this->assertEquals($data['comment'], $o->getComment());
        $this->assertEquals($data['shipping'], $o->getShipping());
        $this->assertEquals($data['date'], $o->getDate());
        $this->assertEquals($data['external_id'], $o->getExternalId());
        $this->assertEquals($data['export'], $o->getExport());
        $this->assertEquals($data['system'], $o->getSystem());
    }

    public function testReadSuccess1(): void
    {
        $data = [
            'delivery' => [
                'client' => $this->getFaker()->word,
                'address' => $this->getFaker()->text,
            ],
            'phone' => $this->getFaker()->e164PhoneNumber,
            'email' => $this->getFaker()->email,
            'status' => $this->getRandomStatus(),
            'comment' => $this->getFaker()->text,
            'shipping' => $this->getFaker()->dateTime,
            'date' => $this->getFaker()->dateTime,
            'external_id' => $this->getFaker()->word,
            'export' => $this->getFaker()->word,
        ];

        $this->service->create($data);

        $order = $this->service->read(['external_id' => $data['external_id']]);
        $this->assertInstanceOf(Order::class, $order);
        $this->assertEquals($data['external_id'], $order->getExternalId());
    }

    public function testReadSuccess2(): void
    {
        $data = [
            'delivery' => [
                'client' => $this->getFaker()->word,
                'address' => $this->getFaker()->text,
            ],
            'phone' => $this->getFaker()->e164PhoneNumber,
            'email' => $this->getFaker()->email,
            'status' => $this->getRandomStatus(),
            'comment' => $this->getFaker()->text,
            'shipping' => $this->getFaker()->dateTime,
            'date' => $this->getFaker()->dateTime,
            'external_id' => $this->getFaker()->word,
            'export' => $this->getFaker()->word,
        ];

        $this->service->create($data);

        $order = $this->service->read(['status' => $data['status']]);
        $this->assertInstanceOf(Collection::class, $order);
    }

    public function testReadWithOrderNotFound(): void
    {
        $this->expectException(OrderNotFoundException::class);

        $this->service->read(['uuid' => $this->getFaker()->uuid]);
    }

    public function testUpdate(): void
    {
        $order = $this->service->create([
            'delivery' => [
                'client' => $this->getFaker()->word,
                'address' => $this->getFaker()->text,
            ],
            'phone' => $this->getFaker()->e164PhoneNumber,
            'email' => $this->getFaker()->email,
            'status' => $this->getRandomStatus(),
            'comment' => $this->getFaker()->text,
            'shipping' => $this->getFaker()->dateTime,
            'date' => $this->getFaker()->dateTime,
            'external_id' => $this->getFaker()->word,
            'export' => $this->getFaker()->word,
            'system' => $this->getFaker()->text,
        ]);

        $data = [
            'delivery' => [
                'client' => $this->getFaker()->word,
                'address' => $this->getFaker()->text,
            ],
            'phone' => $this->getFaker()->e164PhoneNumber,
            'email' => $this->getFaker()->email,
            'status' => $this->getRandomStatus(),
            'comment' => $this->getFaker()->text,
            'shipping' => $this->getFaker()->dateTime,
            'date' => $this->getFaker()->dateTime,
            'external_id' => $this->getFaker()->word,
            'export' => $this->getFaker()->word,
            'system' => $this->getFaker()->text,
        ];

        $order = $this->service->update($order, $data);
        $this->assertInstanceOf(Order::class, $order);
        $this->assertEquals($data['delivery'], $order->getDelivery());
        $this->assertEquals($data['phone'], $order->getPhone());
        $this->assertEquals($data['email'], $order->getEmail());
        $this->assertEquals($data['status'], $order->getStatus());
        $this->assertEquals($data['comment'], $order->getComment());
        $this->assertEquals($data['shipping'], $order->getShipping());
        $this->assertEquals($data['date'], $order->getDate());
        $this->assertEquals($data['external_id'], $order->getExternalId());
        $this->assertEquals($data['export'], $order->getExport());
        $this->assertEquals($data['system'], $order->getSystem());
    }

    public function testUpdateWithOrderNotFound(): void
    {
        $this->expectException(OrderNotFoundException::class);

        $this->service->update(null);
    }

    public function testDeleteSuccess(): void
    {
        $order = $this->service->create([
            'title' => $this->getFaker()->word,
            'address' => 'some-custom-address',
            'status' => $this->getRandomStatus(),
        ]);

        $result = $this->service->delete($order);

        $this->assertTrue($result);
    }

    public function testDeleteWithProductNotFound(): void
    {
        $this->expectException(OrderNotFoundException::class);

        $this->service->delete(null);
    }
}
