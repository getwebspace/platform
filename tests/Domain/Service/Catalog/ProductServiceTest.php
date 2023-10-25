<?php declare(strict_types=1);

namespace tests\Domain\Service\Catalog;

use App\Domain\Entities\Catalog\Product;
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

    protected CategoryService $categoryService;

    public function setUp(): void
    {
        parent::setUp();

        $this->service = $this->getService(ProductService::class);
        $this->categoryService = $this->getService(CategoryService::class);
    }

    public function testCreateSuccess(): void
    {
        $data = [
            'category' => $this->categoryService->create(['title' => $this->getFaker()->word]),
            'title' => $this->getFaker()->word,
            'type' => $this->getFaker()->randomElement(\App\Domain\Types\Catalog\ProductTypeType::LIST),
            'description' => $this->getFaker()->text(100),
            'extra' => $this->getFaker()->text(100),
            'address' => $this->getFaker()->word,
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
            'status' => $this->getFaker()->randomElement(\App\Domain\Types\Catalog\ProductStatusType::LIST),
            'country' => $this->getFaker()->word,
            'manufacturer' => $this->getFaker()->word,
            'tags' => $this->getFaker()->words(5, false),
            'order' => $this->getFaker()->numberBetween(1, 10),
            'date' => $this->getFaker()->dateTime,
            'meta' => [
                'title' => $this->getFaker()->word,
                'description' => $this->getFaker()->text,
                'keywords' => $this->getFaker()->words(5, true),
            ],
            'external_id' => $this->getFaker()->word,
            'export' => $this->getFaker()->word,
        ];

        $product = $this->service->create($data);
        $this->assertInstanceOf(Product::class, $product);
        $this->assertEquals($data['category'], $product->getCategory());
        $this->assertEquals($data['title'], $product->getTitle());
        $this->assertEquals($data['type'], $product->getType());
        $this->assertEquals($data['description'], $product->getDescription());
        $this->assertEquals($data['extra'], $product->getExtra());
        $this->assertEquals($data['address'], $product->getAddress());
        $this->assertEquals($data['vendorcode'], $product->getVendorCode());
        $this->assertEquals($data['barcode'], $product->getBarCode());
        $this->assertEquals($data['tax'], $product->getTax());
        $this->assertEquals($data['priceFirst'], $product->getPriceFirst());
        $this->assertEquals($data['price'], $product->getPrice());
        $this->assertEquals($data['priceWholesale'], $product->getPriceWholesale());
        $this->assertEquals($data['priceWholesaleFrom'], $product->getPriceWholesaleFrom());
        $this->assertEquals($data['discount'], $product->getDiscount());
        $this->assertEquals($data['special'], $product->getSpecial());
        $this->assertEquals($data['dimension'], $product->getDimension());
        $this->assertEquals($data['stock'], $product->getStock());
        $this->assertEquals($data['status'], $product->getStatus());
        $this->assertEquals($data['country'], $product->getCountry());
        $this->assertEquals($data['manufacturer'], $product->getManufacturer());
        $this->assertEquals($data['tags'], $product->getTags());
        $this->assertEquals($data['order'], $product->getOrder());
        $this->assertEquals($data['date'], $product->getDate());
        $this->assertEquals($data['meta'], $product->getMeta());
        $this->assertEquals($data['external_id'], $product->getExternalId());
        $this->assertEquals($data['export'], $product->getExport());

        /** @var ProductRepository $productRepo */
        $productRepo = $this->em->getRepository(Product::class);
        $p = $productRepo->findOneByTitle($data['title']);
        $this->assertInstanceOf(Product::class, $p);
        $this->assertEquals($data['category'], $p->getCategory());
        $this->assertEquals($data['title'], $p->getTitle());
        $this->assertEquals($data['type'], $p->getType());
        $this->assertEquals($data['description'], $p->getDescription());
        $this->assertEquals($data['extra'], $p->getExtra());
        $this->assertEquals($data['address'], $p->getAddress());
        $this->assertEquals($data['vendorcode'], $p->getVendorCode());
        $this->assertEquals($data['barcode'], $p->getBarCode());
        $this->assertEquals($data['priceFirst'], $p->getPriceFirst());
        $this->assertEquals($data['price'], $p->getPrice());
        $this->assertEquals($data['priceWholesale'], $p->getPriceWholesale());
        $this->assertEquals($data['priceWholesaleFrom'], $p->getPriceWholesaleFrom());
        $this->assertEquals($data['discount'], $p->getDiscount());
        $this->assertEquals($data['special'], $p->getSpecial());
        $this->assertEquals($data['stock'], $p->getStock());
        $this->assertEquals($data['status'], $p->getStatus());
        $this->assertEquals($data['country'], $p->getCountry());
        $this->assertEquals($data['manufacturer'], $p->getManufacturer());
        $this->assertEquals($data['tags'], $p->getTags());
        $this->assertEquals($data['order'], $p->getOrder());
        $this->assertEquals($data['date'], $p->getDate());
        $this->assertEquals($data['meta'], $p->getMeta());
        $this->assertEquals($data['external_id'], $p->getExternalId());
        $this->assertEquals($data['export'], $p->getExport());
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
            'title' => $this->getFaker()->word,
            'category' => $this->categoryService->create(['title' => $this->getFaker()->word]),
            'address' => 'some-custom-address',
            'date' => 'now',
            'external_id' => $this->getFaker()->word,
        ];

        $this->service->create($data);
        $this->service->create($data);
    }

    public function testReadSuccess1(): void
    {
        $data = [
            'title' => $this->getFaker()->word,
            'category' => $this->categoryService->create(['title' => $this->getFaker()->word]),
            'address' => 'some-custom-address',
            'status' => $this->getFaker()->randomElement(\App\Domain\Types\Catalog\ProductStatusType::LIST),
        ];

        $this->service->create($data);

        $product = $this->service->read(['address' => $data['address']]);
        $this->assertInstanceOf(Product::class, $product);
        $this->assertEquals($data['title'], $product->getTitle());
        $this->assertEquals($data['address'], $product->getAddress());
        $this->assertEquals($data['status'], $product->getStatus());
    }

    public function testReadSuccess2(): void
    {
        $data = [
            'title' => $this->getFaker()->word,
            'category' => $this->categoryService->create(['title' => $this->getFaker()->word]),
            'address' => 'some-custom-address',
            'status' => $this->getFaker()->randomElement(\App\Domain\Types\Catalog\ProductStatusType::LIST),
            'external_id' => $this->getFaker()->postcode,
        ];

        $this->service->create($data);

        $product = $this->service->read(['external_id' => $data['external_id']]);
        $this->assertInstanceOf(Product::class, $product);
        $this->assertEquals($data['title'], $product->getTitle());
        $this->assertEquals($data['address'], $product->getAddress());
        $this->assertEquals($data['status'], $product->getStatus());
        $this->assertEquals($data['external_id'], $product->getExternalId());
    }

    public function testReadSuccess3(): void
    {
        $data = [
            'title' => $this->getFaker()->word,
            'category' => $this->categoryService->create(['title' => $this->getFaker()->word]),
            'address' => 'some-custom-address',
            'status' => $this->getFaker()->randomElement(\App\Domain\Types\Catalog\ProductStatusType::LIST),
        ];

        $this->service->create($data);

        $product = $this->service->read(['status' => $data['status']]);
        $this->assertInstanceOf(Collection::class, $product);
    }

    public function testReadWithProductNotFound(): void
    {
        $this->expectException(ProductNotFoundException::class);

        $this->service->read(['address' => 'some-custom-address']);
    }

    public function testUpdate(): void
    {
        $product = $this->service->create([
            'category' => $this->categoryService->create(['title' => $this->getFaker()->word]),
            'title' => $this->getFaker()->word,
            'type' => $this->getFaker()->randomElement(\App\Domain\Types\Catalog\ProductTypeType::LIST),
            'description' => $this->getFaker()->text(100),
            'extra' => $this->getFaker()->text(100),
            'address' => $this->getFaker()->word,
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
            'status' => $this->getFaker()->randomElement(\App\Domain\Types\Catalog\ProductStatusType::LIST),
            'country' => $this->getFaker()->word,
            'manufacturer' => $this->getFaker()->word,
            'tags' => $this->getFaker()->words(5, false),
            'order' => $this->getFaker()->numberBetween(1, 10),
            'date' => $this->getFaker()->dateTime,
            'meta' => [
                'title' => $this->getFaker()->word,
                'description' => $this->getFaker()->text,
                'keywords' => $this->getFaker()->words(5, true),
            ],
            'external_id' => $this->getFaker()->word,
            'export' => $this->getFaker()->word,
        ]);

        $data = [
            'category' => $this->categoryService->create(['title' => $this->getFaker()->word]),
            'title' => $this->getFaker()->word,
            'type' => $this->getFaker()->randomElement(\App\Domain\Types\Catalog\ProductTypeType::LIST),
            'description' => $this->getFaker()->text(100),
            'extra' => $this->getFaker()->text(100),
            'address' => $this->getFaker()->word,
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
            'status' => $this->getFaker()->randomElement(\App\Domain\Types\Catalog\ProductStatusType::LIST),
            'country' => $this->getFaker()->word,
            'manufacturer' => $this->getFaker()->word,
            'tags' => $this->getFaker()->words(5, false),
            'order' => $this->getFaker()->numberBetween(1, 10),
            'date' => $this->getFaker()->dateTime,
            'meta' => [
                'title' => $this->getFaker()->word,
                'description' => $this->getFaker()->text,
                'keywords' => $this->getFaker()->words(5, true),
            ],
            'external_id' => $this->getFaker()->word,
            'export' => $this->getFaker()->word,
        ];

        $product = $this->service->update($product, $data);
        $this->assertInstanceOf(Product::class, $product);
        $this->assertEquals($data['category'], $product->getCategory());
        $this->assertEquals($data['title'], $product->getTitle());
        $this->assertEquals($data['type'], $product->getType());
        $this->assertEquals($data['description'], $product->getDescription());
        $this->assertEquals($data['extra'], $product->getExtra());
        $this->assertEquals($data['address'], $product->getAddress());
        $this->assertEquals($data['vendorcode'], $product->getVendorCode());
        $this->assertEquals($data['barcode'], $product->getBarCode());
        $this->assertEquals($data['priceFirst'], $product->getPriceFirst());
        $this->assertEquals($data['price'], $product->getPrice());
        $this->assertEquals($data['priceWholesale'], $product->getPriceWholesale());
        $this->assertEquals($data['priceWholesaleFrom'], $product->getPriceWholesaleFrom());
        $this->assertEquals($data['tax'], $product->getTax());
        $this->assertEquals($data['discount'], $product->getDiscount());
        $this->assertEquals($data['special'], $product->getSpecial());
        $this->assertEquals($data['dimension'], $product->getDimension());
        $this->assertEquals($data['stock'], $product->getStock());
        $this->assertEquals($data['status'], $product->getStatus());
        $this->assertEquals($data['country'], $product->getCountry());
        $this->assertEquals($data['manufacturer'], $product->getManufacturer());
        $this->assertEquals($data['tags'], $product->getTags());
        $this->assertEquals($data['order'], $product->getOrder());
        $this->assertNotEquals($data['date'], $product->getDate());
        $this->assertEquals($data['meta'], $product->getMeta());
        $this->assertEquals($data['external_id'], $product->getExternalId());
        $this->assertEquals($data['export'], $product->getExport());
    }

    public function testUpdateWithProductNotFound(): void
    {
        $this->expectException(ProductNotFoundException::class);

        $this->service->update(null);
    }

    public function testDeleteSuccess(): void
    {
        $product = $this->service->create([
            'title' => $this->getFaker()->word,
            'category' => $this->categoryService->create(['title' => $this->getFaker()->word]),
            'address' => 'some-custom-address',
            'status' => $this->getFaker()->randomElement(\App\Domain\Types\Catalog\ProductStatusType::LIST),
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
