<?php declare(strict_types=1);

namespace tests\Domain\Service\Catalog;

use App\Domain\Entities\Catalog\Product;
use App\Domain\Repository\Catalog\ProductRepository;
use App\Domain\Service\Catalog\Exception\AddressAlreadyExistsException;
use App\Domain\Service\Catalog\Exception\MissingTitleValueException;
use App\Domain\Service\Catalog\Exception\ProductNotFoundException;
use App\Domain\Service\Catalog\ProductService;
use Doctrine\ORM\EntityManager;
use Illuminate\Support\Collection;
use tests\TestCase;

/**
 * @internal
 * @coversNothing
 */
class ProductServiceTest extends TestCase
{
    /**
     * @var EntityManager
     */
    protected $em;

    /**
     * @var ProductService
     */
    protected $service;

    public function setUp(): void
    {
        parent::setUp();
        $this->em = $this->getEntityManager();
        $this->service = ProductService::getWithEntityManager($this->em);
    }

    public function testCreateSuccess(): void
    {
        $data = [
            'category' => $this->getFaker()->uuid,
            'title' => $this->getFaker()->title,
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
            'special' => $this->getFaker()->boolean(),
            'volume' => $this->getFaker()->randomFloat(),
            'unit' => $this->getFaker()->word,
            'stock' => $this->getFaker()->randomFloat(),
            'field1' => $this->getFaker()->word,
            'field2' => $this->getFaker()->word,
            'field3' => $this->getFaker()->word,
            'field4' => $this->getFaker()->word,
            'field5' => $this->getFaker()->word,
            'status' => $this->getFaker()->randomElement(\App\Domain\Types\Catalog\ProductStatusType::LIST),
            'country' => $this->getFaker()->word,
            'manufacturer' => $this->getFaker()->word,
            'tags' => $this->getFaker()->words(5, false),
            'order' => $this->getFaker()->numberBetween(1, 10),
            'date' => $this->getFaker()->dateTime,
            'meta' => [
                'title' => $this->getFaker()->title,
                'description' => $this->getFaker()->text,
                'keywords' => $this->getFaker()->words(5, true),
            ],
            'external_id' => $this->getFaker()->word,
            'export' => $this->getFaker()->word,
        ];

        $product = $this->service->create($data);
        $this->assertInstanceOf(Product::class, $product);
        $this->assertSame($data['category'], $product->getCategory()->toString());
        $this->assertSame($data['title'], $product->getTitle());
        $this->assertSame($data['type'], $product->getType());
        $this->assertSame($data['description'], $product->getDescription());
        $this->assertSame($data['extra'], $product->getExtra());
        $this->assertSame($data['address'], $product->getAddress());
        $this->assertSame($data['vendorcode'], $product->getVendorCode());
        $this->assertSame($data['barcode'], $product->getBarCode());
        $this->assertSame($data['tax'], $product->getTax());
        $this->assertSame($data['priceFirst'], $product->getPriceFirst());
        $this->assertSame($data['price'], $product->getPrice());
        $this->assertSame($data['priceWholesale'], $product->getPriceWholesale());
        $this->assertSame($data['special'], $product->getSpecial());
        $this->assertSame($data['volume'], $product->getVolume());
        $this->assertSame($data['unit'], $product->getUnit());
        $this->assertSame($data['stock'], $product->getStock());
        $this->assertSame($data['field1'], $product->getField1());
        $this->assertSame($data['field2'], $product->getField2());
        $this->assertSame($data['field3'], $product->getField3());
        $this->assertSame($data['field4'], $product->getField4());
        $this->assertSame($data['field5'], $product->getField5());
        $this->assertSame($data['status'], $product->getStatus());
        $this->assertSame($data['country'], $product->getCountry());
        $this->assertSame($data['manufacturer'], $product->getManufacturer());
        $this->assertEquals($data['tags'], $product->getTags());
        $this->assertSame($data['order'], $product->getOrder());
        $this->assertEquals($data['date'], $product->getDate());
        $this->assertEquals($data['meta'], $product->getMeta());
        $this->assertSame($data['external_id'], $product->getExternalId());
        $this->assertSame($data['export'], $product->getExport());

        /** @var ProductRepository $productRepo */
        $productRepo = $this->em->getRepository(Product::class);
        $p = $productRepo->findOneByTitle($data['title']);
        $this->assertInstanceOf(Product::class, $p);
        $this->assertSame($data['category'], $p->getCategory()->toString());
        $this->assertSame($data['title'], $p->getTitle());
        $this->assertSame($data['type'], $p->getType());
        $this->assertSame($data['description'], $p->getDescription());
        $this->assertSame($data['extra'], $p->getExtra());
        $this->assertSame($data['address'], $p->getAddress());
        $this->assertSame($data['vendorcode'], $p->getVendorCode());
        $this->assertSame($data['barcode'], $p->getBarCode());
        $this->assertSame($data['priceFirst'], $p->getPriceFirst());
        $this->assertSame($data['price'], $p->getPrice());
        $this->assertSame($data['priceWholesale'], $p->getPriceWholesale());
        $this->assertSame($data['volume'], $p->getVolume());
        $this->assertSame($data['unit'], $p->getUnit());
        $this->assertSame($data['stock'], $p->getStock());
        $this->assertSame($data['field1'], $p->getField1());
        $this->assertSame($data['field2'], $p->getField2());
        $this->assertSame($data['field3'], $p->getField3());
        $this->assertSame($data['field4'], $p->getField4());
        $this->assertSame($data['field5'], $p->getField5());
        $this->assertSame($data['status'], $p->getStatus());
        $this->assertSame($data['country'], $p->getCountry());
        $this->assertSame($data['manufacturer'], $p->getManufacturer());
        $this->assertEquals($data['tags'], $p->getTags());
        $this->assertSame($data['order'], $p->getOrder());
        $this->assertEquals($data['date'], $p->getDate());
        $this->assertEquals($data['meta'], $p->getMeta());
        $this->assertSame($data['external_id'], $p->getExternalId());
        $this->assertSame($data['export'], $p->getExport());
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
            'title' => $this->getFaker()->title,
            'address' => 'some-custom-address',
            'date' => 'now',
        ];

        $product = (new Product())
            ->setTitle($data['title'] . '-miss')
            ->setAddress($data['address'])
            ->setDate($data['date']);

        $this->em->persist($product);
        $this->em->flush();

        $this->service->create($data);
    }

    public function testReadSuccess1(): void
    {
        $data = [
            'title' => $this->getFaker()->title,
            'address' => 'some-custom-address',
            'status' => $this->getFaker()->randomElement(\App\Domain\Types\Catalog\ProductStatusType::LIST),
        ];

        $this->service->create($data);

        $product = $this->service->read(['title' => $data['title']]);
        $this->assertInstanceOf(Product::class, $product);
        $this->assertSame($data['title'], $product->getTitle());
    }

    public function testReadSuccess2(): void
    {
        $data = [
            'title' => $this->getFaker()->title,
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

        $this->service->read(['title' => $this->getFaker()->title]);
    }

    public function testUpdate(): void
    {
        $product = $this->service->create([
            'category' => $this->getFaker()->uuid,
            'title' => $this->getFaker()->title,
            'type' => $this->getFaker()->randomElement(\App\Domain\Types\Catalog\ProductTypeType::LIST),
            'description' => $this->getFaker()->text(100),
            'extra' => $this->getFaker()->text(100),
            'address' => $this->getFaker()->word,
            'vendorcode' => $this->getFaker()->word,
            'barcode' => $this->getFaker()->word,
            'priceFirst' => $this->getFaker()->randomFloat(),
            'price' => $this->getFaker()->randomFloat(),
            'priceWholesale' => $this->getFaker()->randomFloat(),
            'volume' => $this->getFaker()->randomFloat(),
            'unit' => $this->getFaker()->word,
            'stock' => $this->getFaker()->randomFloat(),
            'field1' => $this->getFaker()->word,
            'field2' => $this->getFaker()->word,
            'field3' => $this->getFaker()->word,
            'field4' => $this->getFaker()->word,
            'field5' => $this->getFaker()->word,
            'status' => $this->getFaker()->randomElement(\App\Domain\Types\Catalog\ProductStatusType::LIST),
            'country' => $this->getFaker()->word,
            'manufacturer' => $this->getFaker()->word,
            'tags' => $this->getFaker()->words(5, false),
            'order' => $this->getFaker()->numberBetween(1, 10),
            'date' => $this->getFaker()->dateTime,
            'meta' => [
                'title' => $this->getFaker()->title,
                'description' => $this->getFaker()->text,
                'keywords' => $this->getFaker()->words(5, true),
            ],
            'external_id' => $this->getFaker()->word,
            'export' => $this->getFaker()->word,
        ]);

        $data = [
            'category' => $this->getFaker()->uuid,
            'title' => $this->getFaker()->title,
            'type' => $this->getFaker()->randomElement(\App\Domain\Types\Catalog\ProductTypeType::LIST),
            'description' => $this->getFaker()->text(100),
            'extra' => $this->getFaker()->text(100),
            'address' => $this->getFaker()->word,
            'vendorcode' => $this->getFaker()->word,
            'barcode' => $this->getFaker()->word,
            'priceFirst' => $this->getFaker()->randomFloat(),
            'price' => $this->getFaker()->randomFloat(),
            'priceWholesale' => $this->getFaker()->randomFloat(),
            'volume' => $this->getFaker()->randomFloat(),
            'unit' => $this->getFaker()->word,
            'stock' => $this->getFaker()->randomFloat(),
            'field1' => $this->getFaker()->word,
            'field2' => $this->getFaker()->word,
            'field3' => $this->getFaker()->word,
            'field4' => $this->getFaker()->word,
            'field5' => $this->getFaker()->word,
            'status' => $this->getFaker()->randomElement(\App\Domain\Types\Catalog\ProductStatusType::LIST),
            'country' => $this->getFaker()->word,
            'manufacturer' => $this->getFaker()->word,
            'tags' => $this->getFaker()->words(5, false),
            'order' => $this->getFaker()->numberBetween(1, 10),
            'date' => $this->getFaker()->dateTime,
            'meta' => [
                'title' => $this->getFaker()->title,
                'description' => $this->getFaker()->text,
                'keywords' => $this->getFaker()->words(5, true),
            ],
            'external_id' => $this->getFaker()->word,
            'export' => $this->getFaker()->word,
        ];

        $product = $this->service->update($product, $data);
        $this->assertInstanceOf(Product::class, $product);
        $this->assertSame($data['category'], $product->getCategory()->toString());
        $this->assertSame($data['title'], $product->getTitle());
        $this->assertSame($data['type'], $product->getType());
        $this->assertSame($data['description'], $product->getDescription());
        $this->assertSame($data['extra'], $product->getExtra());
        $this->assertSame($data['address'], $product->getAddress());
        $this->assertSame($data['vendorcode'], $product->getVendorCode());
        $this->assertSame($data['barcode'], $product->getBarCode());
        $this->assertSame($data['priceFirst'], $product->getPriceFirst());
        $this->assertSame($data['price'], $product->getPrice());
        $this->assertSame($data['priceWholesale'], $product->getPriceWholesale());
        $this->assertSame($data['volume'], $product->getVolume());
        $this->assertSame($data['unit'], $product->getUnit());
        $this->assertSame($data['stock'], $product->getStock());
        $this->assertSame($data['field1'], $product->getField1());
        $this->assertSame($data['field2'], $product->getField2());
        $this->assertSame($data['field3'], $product->getField3());
        $this->assertSame($data['field4'], $product->getField4());
        $this->assertSame($data['field5'], $product->getField5());
        $this->assertSame($data['status'], $product->getStatus());
        $this->assertSame($data['country'], $product->getCountry());
        $this->assertSame($data['manufacturer'], $product->getManufacturer());
        $this->assertEquals($data['tags'], $product->getTags());
        $this->assertSame($data['order'], $product->getOrder());
        $this->assertEquals($data['date'], $product->getDate());
        $this->assertEquals($data['meta'], $product->getMeta());
        $this->assertSame($data['external_id'], $product->getExternalId());
        $this->assertSame($data['export'], $product->getExport());
    }

    public function testUpdateWithProductNotFound(): void
    {
        $this->expectException(ProductNotFoundException::class);

        $this->service->update(null);
    }

    public function testDeleteSuccess(): void
    {
        $product = $this->service->create([
            'title' => $this->getFaker()->title,
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
