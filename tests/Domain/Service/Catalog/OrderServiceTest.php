<?php declare(strict_types=1);

namespace tests\Domain\Service\Catalog;

use App\Domain\Casts\Reference\Type as ReferenceType;
use App\Domain\Models\CatalogCategory;
use App\Domain\Models\CatalogOrder;
use App\Domain\Models\CatalogProduct;
use App\Domain\Models\Reference;
use App\Domain\Service\Catalog\CategoryService;
use App\Domain\Service\Catalog\Exception\OrderNotFoundException;
use App\Domain\Service\Catalog\OrderService;
use App\Domain\Service\Catalog\ProductService;
use App\Domain\Service\Reference\ReferenceService;
use Illuminate\Support\Collection;
use tests\TestCase;

/**
 * @internal
 *
 * #[CoversNothing]
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
                $this->assertEquals($opts['discount'], abs($op->pivot->discount));
                $this->assertEquals($opts['tax'], $op->pivot->tax);
                $this->assertEquals($opts['tax_included'], $op->pivot->tax_included);
            }
        }
    }

    public function testSanitizeCartProducts(): void
    {
        $product = $this->getRandomProduct();
        $product->update(['priceWholesale' => 5, 'priceWholesaleFrom' => 10]);
        $deleted = $this->getRandomProduct();
        $deleted->update(['status' => \App\Domain\Casts\Catalog\Status::DELETE]);

        $result = $this->service->sanitizeCartProducts([
            $product->uuid => ['count' => '2', 'price_type' => 'price_self', 'price' => 1],
            $deleted->uuid => ['count' => 1],
            'unknown' => ['count' => 1],
        ]);
        $this->assertSame([$product->uuid], array_keys($result));
        $this->assertSame('price', $result[$product->uuid]['price_type'], 'the customer cannot name his price unless the shop allows it');
        $this->assertArrayNotHasKey('price', $result[$product->uuid]);

        // switched on: the posted price is kept, with its discount and tax
        $this->getService(\App\Domain\Service\Parameter\ParameterService::class)->create(['name' => 'catalog_order_price_self', 'value' => 'on']);
        $this->getService(\Illuminate\Cache\ArrayStore::class)->flush();
        $result = $this->service->sanitizeCartProducts([$product->uuid => ['count' => '2', 'price_type' => 'price_self', 'price' => 1, 'tax' => 5]]);
        $this->assertSame('price_self', $result[$product->uuid]['price_type']);
        $this->assertSame(1, $result[$product->uuid]['price']);
        $this->assertSame(5, $result[$product->uuid]['tax']);

        $result = $this->service->sanitizeCartProducts([$product->uuid => ['count' => 10, 'price_type' => 'price']]);
        $this->assertSame('price_wholesale', $result[$product->uuid]['price_type']);

        $this->assertSame([], $this->service->sanitizeCartProducts([$product->uuid => ['count' => -3]]));
    }

    public function testBoughtTogether(): void
    {
        $products = $this->getService(ProductService::class);
        $category = $this->getRandomCategory();
        $make = fn (string $name, array $extra = []) => $products->create($extra + ['title' => $name, 'address' => $name, 'category_uuid' => $category->uuid]);
        [$main, $often, $once, $gone, $service] = [
            $make('main'),
            $make('often'),
            $make('once'),
            $make('gone', ['status' => \App\Domain\Casts\Catalog\Status::DELETE]),
            $make('delivery', ['type' => \App\Domain\Casts\Catalog\Product\Type::SERVICE]),
        ];
        $hidden = $products->create(['title' => 'hidden', 'address' => 'hidden', 'category_uuid' => $this->getService(CategoryService::class)->create(['title' => 'Hidden', 'address' => 'hidden-cat', 'is_hidden' => true])->uuid]);

        $order = fn (array $items) => $this->service->create(['products' => array_combine(
            array_map(fn ($p) => $p->uuid, $items),
            array_map(fn () => ['count' => 1, 'price_type' => 'price'], $items)
        )]);
        $order([$main, $often, $once, $gone, $service, $hidden]);
        $order([$main, $often]);
        $order([$often, $once]); // without the main one

        // a canceled order does not count
        $canceled = $this->getService(ReferenceService::class)->create(['type' => ReferenceType::ORDER_STATUS, 'title' => 'Canceled', 'kind' => 'canceled']);
        $withStatus = $this->service->create(['status_uuid' => $canceled->uuid, 'products' => [
            $main->uuid => ['count' => 1, 'price_type' => 'price'],
            $once->uuid => ['count' => 1, 'price_type' => 'price'],
            $often->uuid => ['count' => 1, 'price_type' => 'price'],
        ]]);

        $result = $products->boughtTogether($main, 10);

        $this->assertSame(['often', 'once'], $result->map(fn ($p) => $p->title)->all(), 'the most frequent first; no services, removed or hidden products, not itself');
        $this->assertSame([2, 1], $result->map(fn ($p) => $p->together)->all(), 'the canceled order is not among the shared ones');
        $this->assertSame(['often'], $products->boughtTogether($main->uuid, 1)->map(fn ($p) => $p->title)->all());
        $this->assertCount(0, $products->boughtTogether('not-a-uuid'));
        $this->assertCount(0, $products->boughtTogether($main, 0));
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
            'date' => datetime('now')->format('Y-m-d H:i:s'),
            'shipping' => datetime('now')->format('Y-m-d H:i:s'),
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

    public function testBoughtTogetherLeavesOutTheVariantsOfTheSameProduct(): void
    {
        $products = $this->getService(ProductService::class);
        $category = $this->getRandomCategory();
        $make = fn (string $title, string $address) => $products->create(['title' => $title, 'address' => $address, 'category_uuid' => $category->uuid, 'external_id' => $title]);
        $main = $make('main', 'shop/main');
        $variant = $make('main blue', 'shop/main');
        $other = $make('other', 'shop/other');

        foreach ([[$main, $variant, $other], [$main, $variant]] as $items) {
            $this->service->create(['products' => array_combine(
                array_map(fn ($p) => $p->uuid, $items),
                array_map(fn () => ['count' => 1, 'price_type' => 'price'], $items)
            )]);
        }

        $this->assertSame(['other'], $products->boughtTogether($main, 10)->map(fn ($p) => $p->title)->all());
    }
}
