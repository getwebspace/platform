<?php declare(strict_types=1);

namespace tests\Domain\Service\Catalog;

use App\Domain\Entities\Catalog\Category;
use App\Domain\Repository\Catalog\CategoryRepository;
use App\Domain\Service\Catalog\CategoryService;
use App\Domain\Service\Catalog\Exception\AddressAlreadyExistsException;
use App\Domain\Service\Catalog\Exception\CategoryNotFoundException;
use App\Domain\Service\Catalog\Exception\MissingTitleValueException;
use Illuminate\Support\Collection;
use tests\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
class CategoryServiceTest extends TestCase
{
    protected CategoryService $service;

    public function setUp(): void
    {
        parent::setUp();

        $this->service = $this->getService(CategoryService::class);
    }

    public function testCreateSuccess(): void
    {
        $data = [
            'parent' => $this->getFaker()->uuid,
            'children' => $this->getFaker()->boolean,
            'title' => $this->getFaker()->title,
            'description' => $this->getFaker()->text(100),
            'address' => $this->getFaker()->word,
            'field1' => $this->getFaker()->text,
            'field2' => $this->getFaker()->text,
            'field3' => $this->getFaker()->text,
            'product' => [
                'field_1' => $this->getFaker()->word,
                'field_2' => $this->getFaker()->word,
                'field_3' => $this->getFaker()->word,
                'field_4' => $this->getFaker()->word,
                'field_5' => $this->getFaker()->word,
            ],
            'status' => $this->getFaker()->randomElement(\App\Domain\Types\Catalog\CategoryStatusType::LIST),
            'pagination' => $this->getFaker()->numberBetween(10, 100),
            'order' => $this->getFaker()->numberBetween(1, 10),
            'sort' => [
                'by' => $this->getFaker()->randomElement(\App\Domain\References\Catalog::ORDER_BY),
                'direction' => $this->getFaker()->randomElement(\App\Domain\References\Catalog::ORDER_DIRECTION),
            ],
            'meta' => [
                'title' => $this->getFaker()->title,
                'description' => $this->getFaker()->text,
                'keywords' => $this->getFaker()->words(5, true),
            ],
            'template' => [
                'category' => $this->getFaker()->word,
                'product' => $this->getFaker()->word,
            ],
            'external_id' => $this->getFaker()->word,
            'export' => $this->getFaker()->word,
        ];

        $category = $this->service->create($data);
        $this->assertInstanceOf(Category::class, $category);
        $this->assertSame($data['parent'], $category->getParent()->toString());
        $this->assertSame($data['children'], $category->getChildren());
        $this->assertSame($data['title'], $category->getTitle());
        $this->assertSame($data['description'], $category->getDescription());
        $this->assertSame($data['address'], $category->getAddress());
        $this->assertSame($data['field1'], $category->getField1());
        $this->assertSame($data['field2'], $category->getField2());
        $this->assertSame($data['field3'], $category->getField3());
        $this->assertEquals($data['product'], $category->getProduct());
        $this->assertSame($data['status'], $category->getStatus());
        $this->assertSame($data['pagination'], $category->getPagination());
        $this->assertSame($data['order'], $category->getOrder());
        $this->assertEquals($data['sort'], $category->getSort());
        $this->assertEquals($data['meta'], $category->getMeta());
        $this->assertEquals($data['template'], $category->getTemplate());
        $this->assertSame($data['external_id'], $category->getExternalId());
        $this->assertSame($data['export'], $category->getExport());

        /** @var CategoryRepository $categoryRepo */
        $categoryRepo = $this->em->getRepository(Category::class);
        $c = $categoryRepo->findOneByTitle($data['title']);
        $this->assertInstanceOf(Category::class, $c);
        $this->assertSame($data['parent'], $c->getParent()->toString());
        $this->assertSame($data['children'], $c->getChildren());
        $this->assertSame($data['title'], $c->getTitle());
        $this->assertSame($data['description'], $c->getDescription());
        $this->assertSame($data['address'], $c->getAddress());
        $this->assertSame($data['field1'], $c->getField1());
        $this->assertSame($data['field2'], $c->getField2());
        $this->assertSame($data['field3'], $c->getField3());
        $this->assertEquals($data['product'], $c->getProduct());
        $this->assertSame($data['status'], $c->getStatus());
        $this->assertSame($data['pagination'], $c->getPagination());
        $this->assertSame($data['order'], $c->getOrder());
        $this->assertEquals($data['sort'], $c->getSort());
        $this->assertEquals($data['meta'], $c->getMeta());
        $this->assertEquals($data['template'], $c->getTemplate());
        $this->assertSame($data['external_id'], $c->getExternalId());
        $this->assertSame($data['export'], $c->getExport());
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
        ];

        $category = (new Category())
            ->setTitle($data['title'] . '-miss')
            ->setAddress($data['address']);

        $this->em->persist($category);
        $this->em->flush();

        $this->service->create($data);
    }

    public function testReadSuccess1(): void
    {
        $data = [
            'title' => $this->getFaker()->title,
            'address' => 'some-custom-address',
            'status' => $this->getFaker()->randomElement(\App\Domain\Types\Catalog\CategoryStatusType::LIST),
        ];

        $this->service->create($data);

        $category = $this->service->read(['title' => $data['title']]);
        $this->assertInstanceOf(Category::class, $category);
        $this->assertSame($data['title'], $category->getTitle());
    }

    public function testReadSuccess2(): void
    {
        $data = [
            'title' => $this->getFaker()->title,
            'address' => 'some-custom-address',
            'status' => $this->getFaker()->randomElement(\App\Domain\Types\Catalog\CategoryStatusType::LIST),
        ];

        $this->service->create($data);

        $category = $this->service->read(['status' => $data['status']]);
        $this->assertInstanceOf(Collection::class, $category);
    }

    public function testReadWithCategoryNotFound(): void
    {
        $this->expectException(CategoryNotFoundException::class);

        $this->service->read(['title' => $this->getFaker()->title]);
    }

    public function testUpdate(): void
    {
        $category = $this->service->create([
            'parent' => $this->getFaker()->uuid,
            'children' => $this->getFaker()->boolean,
            'title' => $this->getFaker()->title,
            'description' => $this->getFaker()->text(100),
            'address' => $this->getFaker()->word,
            'field1' => $this->getFaker()->text,
            'field2' => $this->getFaker()->text,
            'field3' => $this->getFaker()->text,
            'product' => [
                'field_1' => $this->getFaker()->word,
                'field_2' => $this->getFaker()->word,
                'field_3' => $this->getFaker()->word,
                'field_4' => $this->getFaker()->word,
                'field_5' => $this->getFaker()->word,
            ],
            'status' => $this->getFaker()->randomElement(\App\Domain\Types\Catalog\CategoryStatusType::LIST),
            'pagination' => $this->getFaker()->numberBetween(10, 100),
            'order' => $this->getFaker()->numberBetween(1, 10),
            'sort' => [
                'by' => $this->getFaker()->randomElement(\App\Domain\References\Catalog::ORDER_BY),
                'direction' => $this->getFaker()->randomElement(\App\Domain\References\Catalog::ORDER_DIRECTION),
            ],
            'meta' => [
                'title' => $this->getFaker()->title,
                'description' => $this->getFaker()->text,
                'keywords' => $this->getFaker()->words(5, true),
            ],
            'template' => [
                'category' => $this->getFaker()->word,
                'product' => $this->getFaker()->word,
            ],
            'external_id' => $this->getFaker()->word,
            'export' => $this->getFaker()->word,
        ]);

        $data = [
            'parent' => $this->getFaker()->uuid,
            'children' => $this->getFaker()->boolean,
            'title' => $this->getFaker()->title,
            'description' => $this->getFaker()->text(100),
            'address' => $this->getFaker()->word,
            'field1' => $this->getFaker()->text,
            'field2' => $this->getFaker()->text,
            'field3' => $this->getFaker()->text,
            'product' => [
                'field_1' => $this->getFaker()->word,
                'field_2' => $this->getFaker()->word,
                'field_3' => $this->getFaker()->word,
                'field_4' => $this->getFaker()->word,
                'field_5' => $this->getFaker()->word,
            ],
            'status' => $this->getFaker()->randomElement(\App\Domain\Types\Catalog\CategoryStatusType::LIST),
            'pagination' => $this->getFaker()->numberBetween(10, 100),
            'order' => $this->getFaker()->numberBetween(1, 10),
            'sort' => [
                'by' => $this->getFaker()->randomElement(\App\Domain\References\Catalog::ORDER_BY),
                'direction' => $this->getFaker()->randomElement(\App\Domain\References\Catalog::ORDER_DIRECTION),
            ],
            'meta' => [
                'title' => $this->getFaker()->title,
                'description' => $this->getFaker()->text,
                'keywords' => $this->getFaker()->words(5, true),
            ],
            'template' => [
                'category' => $this->getFaker()->word,
                'product' => $this->getFaker()->word,
            ],
            'external_id' => $this->getFaker()->word,
            'export' => $this->getFaker()->word,
        ];

        $category = $this->service->update($category, $data);
        $this->assertInstanceOf(Category::class, $category);
        $this->assertSame($data['parent'], $category->getParent()->toString());
        $this->assertSame($data['children'], $category->getChildren());
        $this->assertSame($data['title'], $category->getTitle());
        $this->assertSame($data['description'], $category->getDescription());
        $this->assertSame($data['address'], $category->getAddress());
        $this->assertSame($data['field1'], $category->getField1());
        $this->assertSame($data['field2'], $category->getField2());
        $this->assertSame($data['field3'], $category->getField3());
        $this->assertEquals($data['product'], $category->getProduct());
        $this->assertSame($data['status'], $category->getStatus());
        $this->assertSame($data['pagination'], $category->getPagination());
        $this->assertSame($data['order'], $category->getOrder());
        $this->assertEquals($data['sort'], $category->getSort());
        $this->assertEquals($data['meta'], $category->getMeta());
        $this->assertEquals($data['template'], $category->getTemplate());
        $this->assertSame($data['external_id'], $category->getExternalId());
        $this->assertSame($data['export'], $category->getExport());
    }

    public function testUpdateWithCategoryNotFound(): void
    {
        $this->expectException(CategoryNotFoundException::class);

        $this->service->update(null);
    }

    public function testDeleteSuccess(): void
    {
        $category = $this->service->create([
            'title' => $this->getFaker()->title,
            'address' => 'some-custom-address',
            'status' => $this->getFaker()->randomElement(\App\Domain\Types\Catalog\CategoryStatusType::LIST),
        ]);

        $result = $this->service->delete($category);

        $this->assertTrue($result);
    }

    public function testDeleteWithCategoryNotFound(): void
    {
        $this->expectException(CategoryNotFoundException::class);

        $this->service->delete(null);
    }
}
