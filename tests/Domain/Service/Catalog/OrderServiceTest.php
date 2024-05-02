<?php declare(strict_types=1);

namespace tests\Domain\Service\Catalog;

use App\Domain\Models\CatalogCategory;
use App\Domain\Models\CatalogOrder;
use App\Domain\Models\CatalogProduct;
use App\Domain\Models\Reference;
use App\Domain\Service\Catalog\CategoryService;
use App\Domain\Service\Catalog\Exception\OrderNotFoundException;
use App\Domain\Service\Catalog\OrderService;
use App\Domain\Service\Catalog\ProductService;
use App\Domain\Service\Reference\ReferenceService;
use App\Domain\Casts\Reference\Type as ReferenceType;
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

    protected function getRandomCategory(): CatalogCategory
    {
        return $this->getService(CategoryService::class)->create([
            'title' => implode(' ', $this->getFaker()->words(3)),
            'address' => implode('-', $this->getFaker()->words(4)),
        ]);
    }

    protected function getRandomProduct(): CatalogProduct
    {
        return $this->getService(ProductService::class)->create([
            'category_uuid' => $this->getRandomCategory()->uuid,
            'title' => implode(' ', $this->getFaker()->words(3)),
            'address' => implode('-', $this->getFaker()->words(4)),
            'description' => $this->getFaker()->text(255),
            'price' => $this->getFaker()->randomFloat(2, 10, 10000),
            'priceWholesale' => $this->getFaker()->randomFloat(2, 10, 10000),
            'priceWholesaleFrom' => $this->getFaker()->randomFloat(1, 10),
        ]);
    }

    protected function getRandomStatus(): Reference
    {
        return $this->getService(ReferenceService::class)->create([
            'type' => ReferenceType::ORDER_STATUS,
            'title' => implode(' ', $this->getFaker()->words(3)),
            'order' => $this->getFaker()->randomDigit(),
        ]);
    }

    protected function getRandomPayment(): Reference
    {
        return $this->getService(ReferenceService::class)->create([
            'type' => ReferenceType::PAYMENT,
            'title' => implode(' ', $this->getFaker()->words(3)),
            'order' => $this->getFaker()->randomDigit(),
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
                'client' => $this->getFaker()->name,
                'address' => $this->getFaker()->words(5),
            ],
            'phone' => $this->getFaker()->e164PhoneNumber,
            'email' => $this->getFaker()->email,
            'status_uuid' => $this->getRandomStatus()->uuid,
            'payment_uuid' => $this->getRandomPayment()->uuid,
            'comment' => $this->getFaker()->text,
            'date' => datetime('now')->format('Y-m-d H:i:s'),
            'shipping' => datetime('now')->format('Y-m-d H:i:s'),
            'external_id' => $this->getFaker()->word,
            'export' => $this->getFaker()->word,
            'system' => $this->getFaker()->text,

            'products' => [],
        ];

        foreach ($products as $i => $product) {
            $data['products'][$product->uuid] = [
                'count' => $this->getFaker()->randomFloat(2, 2, 10),
                'price' => (
                    $i % 2 === 0 ?
                        $product->price :
                        $product->priceWholesale
                ),
                'price_type' => (
                    $i % 2 === 0 ?
                        \App\Domain\References\Catalog::PRODUCT_PRICE_TYPE_PRICE :
                        \App\Domain\References\Catalog::PRODUCT_PRICE_TYPE_PRICE_WHOLESALE
                ),
            ];
        }
        $extra = $this->getRandomProduct();
        $data['products'][$extra->uuid] = [
            'count' => $this->getFaker()->randomFloat(2, 2, 10),
            'price' => $this->getFaker()->randomFloat(2, 2, 10000),
            'price_type' => \App\Domain\References\Catalog::PRODUCT_PRICE_TYPE_PRICE_SELF,
            'discount' => $this->getFaker()->randomFloat(2, 1, $product->price),
            'tax' => $this->getFaker()->randomFloat(2, 1, 100),
            'tax_included' => $this->getFaker()->boolean,
        ];

        $order = $this->service->create($data);
        $this->assertInstanceOf(CatalogOrder::class, $order);
        $this->assertEquals($data['delivery'], $order->delivery);
        $this->assertEquals($data['phone'], $order->phone);
        $this->assertEquals($data['email'], $order->email);
        $this->assertEquals($data['status_uuid'], $order->status->uuid);
        $this->assertEquals($data['payment_uuid'], $order->payment->uuid);
        $this->assertEquals($data['comment'], $order->comment);
        $this->assertEquals($data['shipping'], $order->shipping);
        $this->assertEquals($data['external_id'], $order->external_id);
        $this->assertEquals($data['export'], $order->export);
        $this->assertEquals($data['system'], $order->system);

        foreach ($data['products'] as $uuid => $opts) {
            $op = $order->products->firstWhere('uuid', $uuid);

            $this->assertInstanceOf(CatalogProduct::class, $op);

            $this->assertEquals($uuid, $op->uuid);
            $this->assertEquals($opts['count'], $op->pivot->count);
            $this->assertEquals($opts['price'], $op->pivot->price);
            $this->assertEquals($opts['price_type'], $op->pivot->price_type);

            if ($op->pivot->price_type === \App\Domain\References\Catalog::PRODUCT_PRICE_TYPE_PRICE_SELF) {
                $this->assertEquals($opts['discount'], $op->pivot->discount);
                $this->assertEquals($opts['tax'], $op->pivot->tax);
                $this->assertEquals($opts['tax_included'], $op->pivot->tax_included);
            }
        }
    }

    public function testReadSuccess1(): void
    {
        $data = [
            'delivery' => [
                'client' => $this->getFaker()->name,
                'address' => $this->getFaker()->words(5),
            ],
            'phone' => $this->getFaker()->e164PhoneNumber,
            'email' => $this->getFaker()->email,
            'status_uuid' => $this->getRandomStatus()->uuid,
            'payment_uuid' => $this->getRandomPayment()->uuid,
            'comment' => $this->getFaker()->text,
            'date' => datetime('now')->format('Y-m-d H:i:s'),
            'shipping' => datetime('now')->format('Y-m-d H:i:s'),
            'external_id' => $this->getFaker()->word,
            'export' => $this->getFaker()->word,
        ];

        $this->service->create($data);

        $order = $this->service->read(['external_id' => $data['external_id']]);
        $this->assertInstanceOf(CatalogOrder::class, $order);
        $this->assertEquals($data['external_id'], $order->external_id);
    }

    public function testReadSuccess2(): void
    {
        $data = [
            'delivery' => [
                'client' => $this->getFaker()->name,
                'address' => $this->getFaker()->words(5),
            ],
            'phone' => $this->getFaker()->e164PhoneNumber,
            'email' => $this->getFaker()->email,
            'status_uuid' => $this->getRandomStatus()->uuid,
            'payment_uuid' => $this->getRandomPayment()->uuid,
            'comment' => $this->getFaker()->text,
            'date' => datetime('now')->format('Y-m-d H:i:s'),
            'shipping' => datetime('now')->format('Y-m-d H:i:s'),
            'external_id' => $this->getFaker()->word,
            'export' => $this->getFaker()->word,
        ];

        $order = $this->service->create($data);

        $order = $this->service->read(['serial' => $order->serial]);
        $this->assertInstanceOf(CatalogOrder::class, $order);
    }

    public function testReadSuccess3(): void
    {
        $data = [
            'delivery' => [
                'client' => $this->getFaker()->name,
                'address' => $this->getFaker()->words(5),
            ],
            'phone' => $this->getFaker()->e164PhoneNumber,
            'email' => $this->getFaker()->email,
            'status_uuid' => $this->getRandomStatus()->uuid,
            'payment_uuid' => $this->getRandomPayment()->uuid,
            'comment' => $this->getFaker()->text,
            'date' => datetime('now')->format('Y-m-d H:i:s'),
            'shipping' => datetime('now')->format('Y-m-d H:i:s'),
            'external_id' => $this->getFaker()->word,
            'export' => $this->getFaker()->word,
        ];

        $this->service->create($data);

        $orders = $this->service->read(['status_uuid' => $data['status_uuid']]);
        $this->assertInstanceOf(Collection::class, $orders);
    }

    public function testReadSuccess4(): void
    {
        $data = [
            'delivery' => [
                'client' => $this->getFaker()->name,
                'address' => $this->getFaker()->words(5),
            ],
            'phone' => $this->getFaker()->e164PhoneNumber,
            'email' => $this->getFaker()->email,
            'status_uuid' => $this->getRandomStatus()->uuid,
            'payment_uuid' => $this->getRandomPayment()->uuid,
            'comment' => $this->getFaker()->text,
            'shipping' => $this->getFaker()->dateTime,
            'date' => $this->getFaker()->dateTime,
            'external_id' => $this->getFaker()->word,
            'export' => $this->getFaker()->word,
        ];

        $this->service->create($data);

        $orders = $this->service->read(['payment_uuid' => $data['payment_uuid']]);
        $this->assertInstanceOf(Collection::class, $orders);
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
                'client' => $this->getFaker()->name,
                'address' => $this->getFaker()->words(5),
            ],
            'phone' => $this->getFaker()->e164PhoneNumber,
            'email' => $this->getFaker()->email,
            'status_uuid' => $this->getRandomStatus()->uuid,
            'payment_uuid' => $this->getRandomPayment()->uuid,
            'comment' => $this->getFaker()->text,
            'date' => datetime('now')->format('Y-m-d H:i:s'),
            'shipping' => datetime('now')->format('Y-m-d H:i:s'),
            'external_id' => $this->getFaker()->word,
            'export' => $this->getFaker()->word,
            'system' => $this->getFaker()->text,
        ]);

        $data = [
            'delivery' => [
                'client' => $this->getFaker()->name,
                'address' => $this->getFaker()->words(5),
            ],
            'phone' => $this->getFaker()->e164PhoneNumber,
            'email' => $this->getFaker()->email,
            'status_uuid' => $this->getRandomStatus()->uuid,
            'payment_uuid' => $this->getRandomPayment()->uuid,
            'comment' => $this->getFaker()->text,
            'date' => datetime('now')->format('Y-m-d H:i:s'),
            'shipping' => datetime('now')->format('Y-m-d H:i:s'),
            'external_id' => $this->getFaker()->word,
            'export' => $this->getFaker()->word,
            'system' => $this->getFaker()->text,
        ];

        $order = $this->service->update($order, $data);
        $this->assertInstanceOf(CatalogOrder::class, $order);
        $this->assertEquals($data['delivery'], $order->delivery);
        $this->assertEquals($data['phone'], $order->phone);
        $this->assertEquals($data['email'], $order->email);
        $this->assertEquals($data['status_uuid'], $order->status->uuid);
        $this->assertEquals($data['payment_uuid'], $order->payment->uuid);
        $this->assertEquals($data['comment'], $order->comment);
        $this->assertEquals($data['shipping'], $order->shipping);
        $this->assertEquals($data['external_id'], $order->external_id);
        $this->assertEquals($data['export'], $order->export);
        $this->assertEquals($data['system'], $order->system);
    }

    public function testUpdateWithOrderNotFound(): void
    {
        $this->expectException(OrderNotFoundException::class);

        $this->service->update(null);
    }

    public function testDeleteSuccess(): void
    {
        $order = $this->service->create([
            'delivery' => [
                'client' => $this->getFaker()->name,
                'address' => $this->getFaker()->words(5),
            ],
            'phone' => $this->getFaker()->e164PhoneNumber,
            'email' => $this->getFaker()->email,
            'status_uuid' => $this->getRandomStatus()->uuid,
            'payment_uuid' => $this->getRandomPayment()->uuid,
            'date' => datetime('now')->format('Y-m-d H:i:s'),
            'shipping' => datetime('now')->format('Y-m-d H:i:s'),
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
