<?php declare(strict_types=1);

namespace tests\Domain\Service\Catalog;

use App\Domain\Models\CatalogCategory;
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
            'children' => $this->getFaker()->boolean,
            'hidden' => $this->getFaker()->boolean,
            'title' => implode(' ', $this->getFaker()->words(3)),
            'description' => $this->getFaker()->text(100),
            'address' => $this->getFaker()->word,
            'status' => $this->getFaker()->randomElement(\App\Domain\Casts\Catalog\Status::LIST),
            'pagination' => $this->getFaker()->numberBetween(10, 100),
            'order' => $this->getFaker()->numberBetween(1, 10),
            'sort' => [
                'by' => $this->getFaker()->randomElement(\App\Domain\References\Catalog::ORDER_BY),
                'direction' => $this->getFaker()->randomElement(\App\Domain\References\Catalog::ORDER_DIRECTION),
            ],
            'meta' => [
                'title' => implode(' ', $this->getFaker()->words(3)),
                'description' => $this->getFaker()->text,
                'keywords' => $this->getFaker()->words(5, true),
            ],
            'template' => [
                'category' => $this->getFaker()->word,
                'product' => $this->getFaker()->word,
            ],
            'external_id' => $this->getFaker()->word,
            'export' => $this->getFaker()->word,
            'system' => $this->getFaker()->word,
        ];

        $category = $this->service->create($data);
        $this->assertInstanceOf(CatalogCategory::class, $category);
        $this->assertEquals($data['children'], $category->children);
        $this->assertEquals($data['hidden'], $category->hidden);
        $this->assertEquals($data['title'], $category->title);
        $this->assertEquals($data['description'], $category->description);
        $this->assertEquals($data['address'], $category->address);
        $this->assertEquals($data['status'], $category->status);
        $this->assertEquals($data['pagination'], $category->pagination);
        $this->assertEquals($data['order'], $category->order);
        $this->assertEquals($data['sort'], $category->sort);
        $this->assertEquals($data['meta'], $category->meta);
        $this->assertEquals($data['template'], $category->template);
        $this->assertEquals($data['external_id'], $category->external_id);
        $this->assertEquals($data['export'], $category->export);
        $this->assertEquals($data['system'], $category->system);
    }

    public function testCreateWithMissingTitleValue(): void
    {
        $this->expectException(MissingTitleValueException::class);

        $this->service->create();
    }

    public function testCreateWithAddressExistent1(): void
    {
        $this->expectException(AddressAlreadyExistsException::class);

        $data = [
            'title' => implode(' ', $this->getFaker()->words(3)),
            'address' => implode('-', $this->getFaker()->words(4)),
        ];

        $this->service->create($data);
        $this->service->create($data);
    }

    public function testCreateWithAddressExistent2(): void
    {
        $this->expectException(AddressAlreadyExistsException::class);

        $data = [
            'parent' => $this->service->create(['title' => $this->getFaker()->word]),
            'title' => implode(' ', $this->getFaker()->words(3)),
            'address' => implode('-', $this->getFaker()->words(4)),
        ];

        CatalogCategory::create($data);

        $this->service->create($data);
    }

    public function testReadSuccess1(): void
    {
        $data = [
            'title' => implode(' ', $this->getFaker()->words(3)),
            'address' => implode('-', $this->getFaker()->words(4)),
            'status' => $this->getFaker()->randomElement(\App\Domain\Casts\Catalog\Status::LIST),
        ];

        $this->service->create($data);

        $category = $this->service->read(['title' => $data['title']]);
        $this->assertInstanceOf(CatalogCategory::class, $category);
        $this->assertEquals($data['title'], $category->title);
    }

    public function testReadSuccess2(): void
    {
        $data = [
            'title' => implode(' ', $this->getFaker()->words(3)),
            'address' => implode('-', $this->getFaker()->words(4)),
            'status' => $this->getFaker()->randomElement(\App\Domain\Casts\Catalog\Status::LIST),
        ];

        $this->service->create($data);

        $category = $this->service->read(['status' => $data['status']]);
        $this->assertInstanceOf(Collection::class, $category);
    }

    public function testReadWithCategoryNotFound(): void
    {
        $this->expectException(CategoryNotFoundException::class);

        $this->service->read(['title' => $this->getFaker()->word]);
    }

    public function testUpdate(): void
    {
        $category = $this->service->create([
            'children' => $this->getFaker()->boolean,
            'hidden' => $this->getFaker()->boolean,
            'title' => implode(' ', $this->getFaker()->words(3)),
            'description' => $this->getFaker()->text(100),
            'address' => $this->getFaker()->word,
            'status' => $this->getFaker()->randomElement(\App\Domain\Casts\Catalog\Status::LIST),
            'pagination' => $this->getFaker()->numberBetween(10, 100),
            'order' => $this->getFaker()->numberBetween(1, 10),
            'sort' => [
                'by' => $this->getFaker()->randomElement(\App\Domain\References\Catalog::ORDER_BY),
                'direction' => $this->getFaker()->randomElement(\App\Domain\References\Catalog::ORDER_DIRECTION),
            ],
            'meta' => [
                'title' => implode(' ', $this->getFaker()->words(3)),
                'description' => $this->getFaker()->text,
                'keywords' => $this->getFaker()->words(5, true),
            ],
            'template' => [
                'category' => $this->getFaker()->word,
                'product' => $this->getFaker()->word,
            ],
            'external_id' => $this->getFaker()->word,
            'export' => $this->getFaker()->word,
            'system' => $this->getFaker()->word,
        ]);

        $data = [
            'children' => $this->getFaker()->boolean,
            'hidden' => $this->getFaker()->boolean,
            'title' => implode(' ', $this->getFaker()->words(3)),
            'description' => $this->getFaker()->text(100),
            'address' => $this->getFaker()->word,
            'status' => $this->getFaker()->randomElement(\App\Domain\Casts\Catalog\Status::LIST),
            'pagination' => $this->getFaker()->numberBetween(10, 100),
            'order' => $this->getFaker()->numberBetween(1, 10),
            'sort' => [
                'by' => $this->getFaker()->randomElement(\App\Domain\References\Catalog::ORDER_BY),
                'direction' => $this->getFaker()->randomElement(\App\Domain\References\Catalog::ORDER_DIRECTION),
            ],
            'meta' => [
                'title' => implode(' ', $this->getFaker()->words(3)),
                'description' => $this->getFaker()->text,
                'keywords' => $this->getFaker()->words(5, true),
            ],
            'template' => [
                'category' => $this->getFaker()->word,
                'product' => $this->getFaker()->word,
            ],
            'external_id' => $this->getFaker()->word,
            'export' => $this->getFaker()->word,
            'system' => $this->getFaker()->word,
        ];

        $category = $this->service->update($category, $data);
        $this->assertInstanceOf(CatalogCategory::class, $category);
        $this->assertEquals($data['children'], $category->children);
        $this->assertEquals($data['hidden'], $category->hidden);
        $this->assertEquals($data['title'], $category->title);
        $this->assertEquals($data['description'], $category->description);
        $this->assertEquals($data['address'], $category->address);
        $this->assertEquals($data['status'], $category->status);
        $this->assertEquals($data['pagination'], $category->pagination);
        $this->assertEquals($data['order'], $category->order);
        $this->assertEquals($data['sort'], $category->sort);
        $this->assertEquals($data['meta'], $category->meta);
        $this->assertEquals($data['template'], $category->template);
        $this->assertEquals($data['external_id'], $category->external_id);
        $this->assertEquals($data['export'], $category->export);
        $this->assertEquals($data['system'], $category->system);
    }

    public function testUpdateWithCategoryNotFound(): void
    {
        $this->expectException(CategoryNotFoundException::class);

        $this->service->update(null);
    }

    public function testDeleteSuccess(): void
    {
        $category = $this->service->create([
            'title' => implode(' ', $this->getFaker()->words(3)),
            'address' => implode('-', $this->getFaker()->words(4)),
            'status' => $this->getFaker()->randomElement(\App\Domain\Casts\Catalog\Status::LIST),
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
