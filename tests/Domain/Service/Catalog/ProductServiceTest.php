<?php declare(strict_types=1);

namespace tests\Domain\Service\Catalog;

use App\Domain\Models\CatalogCategory;
use App\Domain\Models\CatalogProduct;
use App\Domain\Repository\Catalog\ProductRepository;
use App\Domain\Service\Catalog\CategoryService;
use App\Domain\Service\Catalog\Exception\AddressAlreadyExistsException;
use App\Domain\Service\Catalog\Exception\MissingTitleValueException;
use App\Domain\Service\Catalog\Exception\ProductNotFoundException;
use App\Domain\Service\Catalog\ProductService;
use Illuminate\Support\Collection;
use tests\TestCase;

/**
 * @internal
 *
 * @coversNothing
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
            'type' => $this->getFaker()->randomElement(\App\Domain\Casts\Catalog\ProductType::LIST),
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
            'type' => $this->getFaker()->randomElement(\App\Domain\Casts\Catalog\ProductType::LIST),
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
            'type' => $this->getFaker()->randomElement(\App\Domain\Casts\Catalog\ProductType::LIST),
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
}
