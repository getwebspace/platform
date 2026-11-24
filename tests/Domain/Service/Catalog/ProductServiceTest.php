<?php declare(strict_types=1);

namespace tests\Domain\Service\Catalog;

use App\Domain\Models\CatalogCategory;
use App\Domain\Models\CatalogProduct;
use App\Domain\Service\Catalog\CategoryService;
use App\Domain\Service\Catalog\Exception\AddressAlreadyExistsException;
use App\Domain\Service\Catalog\Exception\MissingTitleValueException;
use App\Domain\Service\Catalog\Exception\ProductNotFoundException;
use App\Domain\Service\Catalog\ProductService;
use tests\TestCase;

/**
 * @internal
 *
 * #[CoversNothing]
 */
class ProductServiceTest extends TestCase
{
    protected ProductService $service;

    /**
     * @var CatalogCategory
     */
    protected $category;

    public function setUp(): void
    {
        parent::setUp();

        $this->service = $this->getService(ProductService::class);

        $this->category = $this->getService(CategoryService::class)->create([
            'title' => implode(' ', $this->getFaker()->words(3)),
            'address' => implode('-', $this->getFaker()->words(4)),
            'description' => $this->getFaker()->text(255),
        ]);
    }

    public function testCreateSuccess(): void
    {
        $data = [
            'title' => implode(' ', $this->getFaker()->words(3)),
            'description' => $this->getFaker()->text(100),
            'extra' => $this->getFaker()->text(100),
            'address' => implode('-', $this->getFaker()->words(4)),
            'type' => $this->getFaker()->randomElement(\App\Domain\Casts\Catalog\Product\Type::LIST),
            'category_uuid' => $this->category->uuid,
            'vendorcode' => $this->getFaker()->word,
            'barcode' => $this->getFaker()->word,
            'tax' => $this->getFaker()->randomFloat(),
            'priceFirst' => $this->getFaker()->randomFloat(),
            'price' => $this->getFaker()->randomFloat(),
            'priceWholesale' => $this->getFaker()->randomFloat(),
            'priceWholesaleFrom' => $this->getFaker()->randomFloat(),
            'discount' => -$this->getFaker()->randomFloat(),
            'special' => $this->getFaker()->boolean(),
            'dimension' => [
                'length' => $this->getFaker()->randomFloat(),
                'width' => $this->getFaker()->randomFloat(),
                'height' => $this->getFaker()->randomFloat(),
                'weight' => $this->getFaker()->randomFloat(),
                'length_class' => $this->getFaker()->word,
                'weight_class' => $this->getFaker()->word,
            ],
            'stock' => $this->getFaker()->randomFloat(),
            'status' => $this->getFaker()->randomElement(\App\Domain\Casts\Catalog\Status::LIST),
            'country' => $this->getFaker()->word,
            'manufacturer' => $this->getFaker()->word,
            'tags' => $this->getFaker()->words(5, false),
            'order' => $this->getFaker()->numberBetween(1, 10),
            'date' => $this->getFaker()->dateTime,
            'meta' => [
                'title' => implode(' ', $this->getFaker()->words(3)),
                'description' => $this->getFaker()->text,
                'keywords' => $this->getFaker()->words(5, true),
            ],
            'external_id' => $this->getFaker()->word,
            'export' => $this->getFaker()->word,
        ];

        $product = $this->service->create($data);
        $this->assertInstanceOf(CatalogProduct::class, $product);
        $this->assertEquals($data['title'], $product->title);
        $this->assertEquals($data['description'], $product->description);
        $this->assertEquals($data['extra'], $product->extra);
        $this->assertEquals($data['address'], $product->address);
        $this->assertEquals($data['category_uuid'], $product->category_uuid);
        $this->assertEquals($data['type'], $product->type);
        $this->assertEquals($data['vendorcode'], $product->vendorcode);
        $this->assertEquals($data['barcode'], $product->barcode);
        $this->assertEquals($data['tax'], $product->tax);
        $this->assertEquals($data['priceFirst'], $product->priceFirst);
        $this->assertEquals($data['price'], $product->price);
        $this->assertEquals($data['priceWholesale'], $product->priceWholesale);
        $this->assertEquals($data['priceWholesaleFrom'], $product->priceWholesaleFrom);
        $this->assertEquals($data['discount'], $product->discount);
        $this->assertEquals($data['special'], $product->special);
        $this->assertEquals($data['dimension'], $product->dimension);
        $this->assertEquals($data['stock'], $product->stock);
        $this->assertEquals($data['status'], $product->status);
        $this->assertEquals($data['country'], $product->country);
        $this->assertEquals($data['manufacturer'], $product->manufacturer);
        $this->assertEquals($data['tags'], $product->tags);
        $this->assertEquals($data['order'], $product->order);
        $this->assertEquals($data['date'], $product->date);
        $this->assertEquals($data['meta'], $product->meta);
        $this->assertEquals($data['external_id'], $product->external_id);
        $this->assertEquals($data['export'], $product->export);
    }

    public function testCreateWithMissingTitleValue(): void
    {
        $this->expectException(MissingTitleValueException::class);

        $this->service->create();
    }

    public function testGeneratedAddressKeepsVariantsTogetherAndSeparatesOthers(): void
    {
        $this->getService(\App\Domain\Service\Parameter\ParameterService::class)->create(['name' => 'common_auto_generate_address', 'value' => 'yes']);
        $this->getService(\Illuminate\Cache\ArrayStore::class)->flush();
        $make = fn (string $title, array $extra = []) => $this->service->create($extra + ['title' => $title, 'category_uuid' => $this->category->uuid]);

        $base = $make('Shirt', ['vendorcode' => 'S-1', 'external_id' => '1']);
        // the same title in the same category is a variant: it shares the page of the product
        $blue = $make('Shirt', ['vendorcode' => 'S-2', 'external_id' => '2']);
        $this->assertSame($base->address, $blue->address);

        // another product that would get the same address is told apart
        $other = $make('Shirt!', ['vendorcode' => 'S-3', 'external_id' => '3']);
        $this->assertNotSame($base->address, $other->address);
        $this->assertStringContainsString('s-3', $other->address);
    }

    public function testCreateWithAddressExistent(): void
    {
        $this->expectException(AddressAlreadyExistsException::class);

        $data = [
            'title' => implode(' ', $this->getFaker()->words(3)),
            'address' => implode('-', $this->getFaker()->words(4)),
            'category_uuid' => $this->category->uuid,
            'dimension' => [
                'length' => $this->getFaker()->randomFloat(),
                'width' => $this->getFaker()->randomFloat(),
                'height' => $this->getFaker()->randomFloat(),
                'weight' => $this->getFaker()->randomFloat(),
                'length_class' => $this->getFaker()->word,
                'weight_class' => $this->getFaker()->word,
            ],
        ];

        CatalogProduct::create($data);

        $this->service->create($data);
    }

    public function testReadSuccess1(): void
    {
        $data = [
            'title' => implode(' ', $this->getFaker()->words(3)),
            'address' => implode('-', $this->getFaker()->words(4)),
            'category_uuid' => $this->category->uuid,
        ];

        $this->service->create($data);

        $product = $this->service->read(['address' => $data['address']]);
        $this->assertInstanceOf(CatalogProduct::class, $product);
        $this->assertEquals($data['title'], $product->title);
        $this->assertEquals($data['address'], $product->address);
    }

    public function testReadSuccess2(): void
    {
        $data = [
            'title' => implode(' ', $this->getFaker()->words(3)),
            'address' => implode('-', $this->getFaker()->words(4)),
            'category_uuid' => $this->category->uuid,
            'external_id' => $this->getFaker()->postcode,
        ];

        $this->service->create($data);

        $product = $this->service->read(['external_id' => $data['external_id']]);
        $this->assertInstanceOf(CatalogProduct::class, $product);
        $this->assertEquals($data['title'], $product->title);
        $this->assertEquals($data['address'], $product->address);
        $this->assertEquals($data['external_id'], $product->external_id);
    }

    public function testReadWithProductNotFound(): void
    {
        $this->expectException(ProductNotFoundException::class);

        $this->service->read(['address' => implode('-', $this->getFaker()->words(4))]);
    }

    public function testUpdate(): void
    {
        $product = $this->service->create([
            'title' => implode(' ', $this->getFaker()->words(3)),
            'description' => $this->getFaker()->text(100),
            'extra' => $this->getFaker()->text(100),
            'address' => implode('-', $this->getFaker()->words(4)),
            'type' => $this->getFaker()->randomElement(\App\Domain\Casts\Catalog\Product\Type::LIST),
            'category_uuid' => $this->category->uuid,
            'vendorcode' => $this->getFaker()->word,
            'barcode' => $this->getFaker()->word,
            'priceFirst' => $this->getFaker()->randomFloat(),
            'price' => $this->getFaker()->randomFloat(),
            'priceWholesale' => $this->getFaker()->randomFloat(),
            'priceWholesaleFrom' => $this->getFaker()->randomFloat(),
            'tax' => $this->getFaker()->randomFloat(),
            'discount' => -$this->getFaker()->randomFloat(),
            'special' => $this->getFaker()->boolean(),
            'dimension' => [
                'length' => $this->getFaker()->randomFloat(),
                'width' => $this->getFaker()->randomFloat(),
                'height' => $this->getFaker()->randomFloat(),
                'weight' => $this->getFaker()->randomFloat(),
                'length_class' => $this->getFaker()->word,
                'weight_class' => $this->getFaker()->word,
            ],
            'stock' => $this->getFaker()->randomFloat(),
            'status' => $this->getFaker()->randomElement(\App\Domain\Casts\Catalog\Status::LIST),
            'country' => $this->getFaker()->word,
            'manufacturer' => $this->getFaker()->word,
            'tags' => $this->getFaker()->words(5, false),
            'order' => $this->getFaker()->numberBetween(1, 10),
            'date' => $this->getFaker()->dateTime,
            'meta' => [
                'title' => implode(' ', $this->getFaker()->words(3)),
                'description' => $this->getFaker()->text,
                'keywords' => $this->getFaker()->words(5, true),
            ],
            'external_id' => $this->getFaker()->word,
            'export' => $this->getFaker()->word,
        ]);

        $data = [
            'title' => implode(' ', $this->getFaker()->words(3)),
            'description' => $this->getFaker()->text(100),
            'extra' => $this->getFaker()->text(100),
            'address' => implode('-', $this->getFaker()->words(4)),
            'type' => $this->getFaker()->randomElement(\App\Domain\Casts\Catalog\Product\Type::LIST),
            'category_uuid' => $this->category->uuid,
            'vendorcode' => $this->getFaker()->word,
            'barcode' => $this->getFaker()->word,
            'priceFirst' => $this->getFaker()->randomFloat(),
            'price' => $this->getFaker()->randomFloat(),
            'priceWholesale' => $this->getFaker()->randomFloat(),
            'priceWholesaleFrom' => $this->getFaker()->randomFloat(),
            'tax' => $this->getFaker()->randomFloat(),
            'discount' => -$this->getFaker()->randomFloat(),
            'special' => $this->getFaker()->boolean(),
            'dimension' => [
                'length' => $this->getFaker()->randomFloat(),
                'width' => $this->getFaker()->randomFloat(),
                'height' => $this->getFaker()->randomFloat(),
                'weight' => $this->getFaker()->randomFloat(),
                'length_class' => $this->getFaker()->word,
                'weight_class' => $this->getFaker()->word,
            ],
            'stock' => $this->getFaker()->randomFloat(),
            'status' => $this->getFaker()->randomElement(\App\Domain\Casts\Catalog\Status::LIST),
            'country' => $this->getFaker()->word,
            'manufacturer' => $this->getFaker()->word,
            'tags' => $this->getFaker()->words(5, false),
            'order' => $this->getFaker()->numberBetween(1, 10),
            'date' => $this->getFaker()->dateTime,
            'meta' => [
                'title' => implode(' ', $this->getFaker()->words(3)),
                'description' => $this->getFaker()->text,
                'keywords' => $this->getFaker()->words(5, true),
            ],
            'external_id' => $this->getFaker()->word,
            'export' => $this->getFaker()->word,
        ];

        $product = $this->service->update($product, $data);
        $this->assertInstanceOf(CatalogProduct::class, $product);
        $this->assertEquals($data['title'], $product->title);
        $this->assertEquals($data['description'], $product->description);
        $this->assertEquals($data['extra'], $product->extra);
        $this->assertEquals($data['address'], $product->address);
        $this->assertEquals($data['category_uuid'], $product->category_uuid);
        $this->assertEquals($data['type'], $product->type);
        $this->assertEquals($data['vendorcode'], $product->vendorcode);
        $this->assertEquals($data['barcode'], $product->barcode);
        $this->assertEquals($data['tax'], $product->tax);
        $this->assertEquals($data['priceFirst'], $product->priceFirst);
        $this->assertEquals($data['price'], $product->price);
        $this->assertEquals($data['priceWholesale'], $product->priceWholesale);
        $this->assertEquals($data['priceWholesaleFrom'], $product->priceWholesaleFrom);
        $this->assertEquals($data['discount'], $product->discount);
        $this->assertEquals($data['special'], $product->special);
        $this->assertEquals($data['dimension'], $product->dimension);
        $this->assertEquals($data['stock'], $product->stock);
        $this->assertEquals($data['status'], $product->status);
        $this->assertEquals($data['country'], $product->country);
        $this->assertEquals($data['manufacturer'], $product->manufacturer);
        $this->assertEquals($data['tags'], $product->tags);
        $this->assertEquals($data['order'], $product->order);
        //      $this->assertEquals($data['date'], $product->date);
        $this->assertEquals($data['meta'], $product->meta);
        $this->assertEquals($data['external_id'], $product->external_id);
        $this->assertEquals($data['export'], $product->export);
    }

    public function testUpdateWithProductNotFound(): void
    {
        $this->expectException(ProductNotFoundException::class);

        $this->service->update(null);
    }

    public function testDeleteSuccess(): void
    {
        $product = $this->service->create([
            'title' => implode(' ', $this->getFaker()->words(3)),
            'address' => implode('-', $this->getFaker()->words(4)),
            'category_uuid' => $this->category->uuid,
        ]);

        $result = $this->service->delete($product);

        $this->assertTrue($result);
    }

    public function testDeleteWithProductNotFound(): void
    {
        $this->expectException(ProductNotFoundException::class);

        $this->service->delete(null);
    }

    public function testStockStatusIsARecordOfTheStockStatusReference(): void
    {
        $references = $this->getService(\App\Domain\Service\Reference\ReferenceService::class);
        $status = $references->create(['type' => 'stock_status', 'title' => 'Pre-order']);
        $payment = $references->create(['type' => 'payment', 'title' => 'Card']);
        $make = fn (array $extra) => $this->service->create($extra + ['title' => 'P' . uniqid(), 'address' => 'p' . uniqid(), 'category_uuid' => $this->category->uuid]);

        $product = $make(['stock_status_uuid' => $status->uuid]);
        $this->assertSame($status->uuid, $product->stock_status_uuid);
        $this->assertSame('Pre-order', $product->stockStatus->title);

        // not a stock status, or no such record: no status
        $this->assertNull($make(['stock_status_uuid' => $payment->uuid])->stock_status_uuid);
        $this->assertNull($make(['stock_status_uuid' => '01a20e92-a8f9-7388-beaa-630dc89e85bf'])->stock_status_uuid);
        $this->assertNull($this->service->update($product, ['stock_status_uuid' => ''])->stock_status_uuid);

        // a removed status is taken off the products
        $product = $this->service->update($product, ['stock_status_uuid' => $status->uuid]);
        $references->delete($status);
        $this->assertNull($product->fresh()->stock_status_uuid);
    }

    private function make(string $title, array $extra = []): CatalogProduct
    {
        return $this->service->create($extra + ['title' => $title, 'address' => 'shop/' . uniqid(), 'category_uuid' => $this->category->uuid]);
    }

    public function testUrlOfAProductAndOfAVariant(): void
    {
        CatalogProduct::flushRuntimeCache();
        $alone = $this->make('Alone', ['address' => 'shop/alone', 'external_id' => 'x']);
        $this->assertFalse($alone->has_variants());
        $this->assertSame('/catalog/shop/alone', $alone->url(), 'no ?v= for a product on its own');

        $a = $this->make('A', ['address' => 'shop/group', 'external_id' => 'A 1/2']);
        $b = $this->make('B', ['address' => 'shop/group', 'external_id' => '']);
        CatalogProduct::flushRuntimeCache();

        $this->assertTrue($a->has_variants());
        $this->assertSame('/catalog/shop/group?v=A%201%2F2', $a->url(), 'the external id is encoded');
        $this->assertSame('/catalog/shop/group?v=' . $b->uuid, $b->url(), 'the uuid when there is no external id');
        $this->assertSame('/catalog/shop/group', $a->canonical_url());
        $this->assertSame($a->url(), $a->toArray()['url']);
        $this->assertSame('/catalog/shop/group', $a->toArray()['canonical_url']);

        // the search puts the prefix into the address for the themes: the link does not double it
        $searched = CatalogProduct::find($a->uuid)->prefixAddress();
        $this->assertSame('catalog/shop/group', $searched->address);
        $this->assertSame('/catalog/shop/group?v=A%201%2F2', $searched->url());
        $this->assertTrue($searched->has_variants());

        // a removed one is no variant
        $b->update(['status' => \App\Domain\Casts\Catalog\Status::DELETE]);
        CatalogProduct::flushRuntimeCache();
        $this->assertFalse(CatalogProduct::find($a->uuid)->has_variants());

        // the catalog_address parameter is the prefix
        $this->getService(\App\Domain\Service\Parameter\ParameterService::class)->create(['name' => 'catalog_address', 'value' => 'shop']);
        CatalogProduct::flushRuntimeCache();
        $this->assertSame('/shop/shop/alone', CatalogProduct::find($alone->uuid)->url());
        CatalogProduct::flushRuntimeCache();
    }

    public function testTheLabelOfAVariant(): void
    {
        CatalogProduct::flushRuntimeCache();
        $attributes = $this->getService(\App\Domain\Service\Catalog\AttributeService::class);
        $size = $attributes->create(['title' => 'Size', 'address' => 'size', 'type' => \App\Domain\Casts\Catalog\Attribute\Type::STRING]);
        $brand = $attributes->create(['title' => 'Brand', 'address' => 'brand', 'type' => \App\Domain\Casts\Catalog\Attribute\Type::STRING]);
        $only = $attributes->create(['title' => 'Only', 'address' => 'only', 'type' => \App\Domain\Casts\Catalog\Attribute\Type::STRING]);

        $m = $this->make('T', ['address' => 'shop/t', 'external_id' => '1', 'vendorcode' => 'T-M', 'attributes' => [$size->uuid => 'M', $brand->uuid => 'Acme', $only->uuid => 'x']]);
        $l = $this->make('T', ['address' => 'shop/t', 'external_id' => '2', 'vendorcode' => 'T-L', 'attributes' => [$size->uuid => 'L', $brand->uuid => 'Acme']]);
        $own = $this->make('T', ['address' => 'shop/t', 'external_id' => '3', 'vendorcode' => 'T-S', 'variant_title' => 'Small one', 'attributes' => [$size->uuid => 'S', $brand->uuid => 'Acme']]);
        CatalogProduct::flushRuntimeCache();

        $this->assertSame('Small one', CatalogProduct::find($own->uuid)->variant_label(), 'its own title first');
        $this->assertSame('M', CatalogProduct::find($m->uuid)->variant_label(), 'the attribute filled for all with different values: size, not brand (same) or only (not for all)');
        $this->assertSame('L', CatalogProduct::find($l->uuid)->variant_label());

        $plain = $this->make('P', ['address' => 'shop/p', 'external_id' => '1', 'vendorcode' => 'P-1']);
        $plain2 = $this->make('P', ['address' => 'shop/p', 'external_id' => '2']);
        CatalogProduct::flushRuntimeCache();
        $this->assertSame('P-1', CatalogProduct::find($plain->uuid)->variant_label(), 'the vendor code');
        $this->assertSame('2', CatalogProduct::find($plain2->uuid)->variant_label(), 'the external id');
    }

    public function testTheCardsOfAPageCostOneQueryForTheirGroups(): void
    {
        CatalogProduct::flushRuntimeCache();
        for ($i = 0; $i < 50; ++$i) {
            $this->make('Card ' . $i, ['address' => 'shop/card-' . ($i % 25), 'external_id' => (string) $i]);
        }
        CatalogProduct::flushRuntimeCache();

        $connection = CatalogProduct::query()->getConnection();
        $connection->enableQueryLog();
        $connection->flushQueryLog();

        $products = $this->service->read(['category_uuid' => $this->category->uuid]);
        $afterRead = count($connection->getQueryLog());
        foreach ($products as $product) {
            $product->url();
            $product->has_variants();
        }
        $afterLinks = count($connection->getQueryLog());
        $connection->disableQueryLog();

        $this->assertCount(50, $products);
        $this->assertSame($afterRead, $afterLinks, 'the links of 50 cards ask nothing more');
        $queries = array_column($connection->getQueryLog(), 'query');
        $this->assertCount(1, array_filter($queries, fn (string $q) => str_contains($q, 'group by "address"')), 'the groups of all the cards: one query');
        $this->assertCount(1, array_filter($queries, fn (string $q) => str_contains($q, 'from "params"')), 'the prefix: once');
        CatalogProduct::flushRuntimeCache();
    }
}
