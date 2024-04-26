<?php declare(strict_types=1);

namespace tests\Domain\Service\Publication;

use App\Domain\Models\PublicationCategory;
use App\Domain\Service\Publication\CategoryService as PublicationCategoryService;
use App\Domain\Service\Publication\Exception\AddressAlreadyExistsException;
use App\Domain\Service\Publication\Exception\CategoryNotFoundException;
use App\Domain\Service\Publication\Exception\MissingTitleValueException;
use App\Domain\Service\Publication\Exception\TitleAlreadyExistsException;
use tests\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
class CategoryServiceTest extends TestCase
{
    protected PublicationCategoryService $service;

    public function setUp(): void
    {
        parent::setUp();

        $this->service = $this->getService(PublicationCategoryService::class);
    }

    public function testCreateSuccess(): void
    {
        $data = [
            'title' => implode(' ', $this->getFaker()->words(3)),
            'address' => implode('-', $this->getFaker()->words(4)),
            'description' => $this->getFaker()->text(255),
            'pagination' => $this->getFaker()->numberBetween(10, 1000),
            'is_allow_nested' => $this->getFaker()->boolean,
            'is_public' => $this->getFaker()->boolean,
            'sort' => [
                'by' => $this->getFaker()->randomElement(\App\Domain\References\Publication::ORDER_BY),
                'direction' => $this->getFaker()->randomElement(\App\Domain\References\Publication::ORDER_DIRECTION),
            ],
            'meta' => [
                'title' => $this->getFaker()->word,
                'description' => $this->getFaker()->text,
                'keywords' => $this->getFaker()->text,
            ],
            'template' => [
                'list' => $this->getFaker()->word,
                'short' => $this->getFaker()->text,
                'full' => $this->getFaker()->text,
            ],
        ];

        $publicationCategory = $this->service->create($data);
        $this->assertInstanceOf(PublicationCategory::class, $publicationCategory);
        $this->assertEquals($data['title'], $publicationCategory->title);
        $this->assertEquals($data['address'], $publicationCategory->address);
        $this->assertEquals($data['description'], $publicationCategory->description);
        $this->assertEquals($data['pagination'], $publicationCategory->pagination);
        $this->assertEquals($data['is_allow_nested'], $publicationCategory->is_allow_nested);
        $this->assertEquals($data['is_public'], $publicationCategory->is_public);
        $this->assertEquals($data['sort'], $publicationCategory->sort);
        $this->assertEquals($data['meta'], $publicationCategory->meta);
        $this->assertEquals($data['template'], $publicationCategory->template);
    }

    public function testCreateWithMissingTitleValue(): void
    {
        $this->expectException(MissingTitleValueException::class);

        $this->service->create();
    }

    public function testCreateWithTitleExistent(): void
    {
        $this->expectException(TitleAlreadyExistsException::class);

        $data = [
            'title' => implode(' ', $this->getFaker()->words(3)),
            'address' => implode('-', $this->getFaker()->words(4)),
            'description' => $this->getFaker()->text(255),
        ];

        PublicationCategory::create($data);

        $this->service->create($data);
    }

    public function testCreateWithAddressExistent(): void
    {
        $this->expectException(AddressAlreadyExistsException::class);

        $data = [
            'title' => implode(' ', $this->getFaker()->words(3)),
            'address' => implode('-', $this->getFaker()->words(4)),
            'description' => $this->getFaker()->text(255),
        ];

        PublicationCategory::create($data);

        $this->service->create(array_merge($data, ['title' => implode(' ', $this->getFaker()->words(3))]));
    }

    public function testCreateWithParent(): void
    {
        $parent = $this->service->create([
            'title' => implode(' ', $this->getFaker()->words(3)),
            'address' => implode('-', $this->getFaker()->words(4)),
        ]);
        $publicationCategory = $this->service->create([
            'title' => implode(' ', $this->getFaker()->words(3)),
            'address' => implode('-', $this->getFaker()->words(4)),
            'parent_uuid' => $parent->uuid,
        ]);

        $this->assertInstanceOf(PublicationCategory::class, $publicationCategory);
        $this->assertEquals($publicationCategory->parent_uuid, $parent->uuid);
        $this->assertEquals($publicationCategory->parent->attributesToArray(), $parent->attributesToArray());
        $this->assertEquals($parent->nested(true)->count(), 2);
    }

    public function testReadSuccess1(): void
    {
        $data = [
            'title' => implode(' ', $this->getFaker()->words(3)),
            'address' => implode('-', $this->getFaker()->words(4)),
            'description' => $this->getFaker()->text(255),
        ];

        $this->service->create($data);

        $publicationCategory = $this->service->read(['title' => $data['title']]);
        $this->assertInstanceOf(PublicationCategory::class, $publicationCategory);
        $this->assertEquals($data['title'], $publicationCategory->title);
    }

    public function testReadSuccess2(): void
    {
        $data = [
            'title' => implode(' ', $this->getFaker()->words(3)),
            'address' => implode('-', $this->getFaker()->words(4)),
            'description' => $this->getFaker()->text(255),
        ];

        $this->service->create($data);

        $publicationCategory = $this->service->read(['address' => $data['address']]);
        $this->assertInstanceOf(PublicationCategory::class, $publicationCategory);
        $this->assertEquals($data['address'], $publicationCategory->address);
    }

    public function testReadWithCategoryNotFound(): void
    {
        $this->expectException(CategoryNotFoundException::class);

        $this->service->read(['address' => $this->getFaker()->userName]);
    }

    public function testUpdate(): void
    {
        $publicationCategory = $this->service->create([
            'title' => implode(' ', $this->getFaker()->words(3)),
            'address' => implode('-', $this->getFaker()->words(4)),
            'description' => $this->getFaker()->text(255),
            'pagination' => $this->getFaker()->numberBetween(10, 1000),
            'is_allow_nested' => $this->getFaker()->boolean,
            'is_public' => $this->getFaker()->boolean,
            'sort' => [
                'by' => $this->getFaker()->randomElement(\App\Domain\References\Publication::ORDER_BY),
                'direction' => $this->getFaker()->randomElement(\App\Domain\References\Publication::ORDER_DIRECTION),
            ],
            'meta' => [
                'title' => $this->getFaker()->word,
                'description' => $this->getFaker()->text,
                'keywords' => $this->getFaker()->text,
            ],
            'template' => [
                'list' => $this->getFaker()->word,
                'short' => $this->getFaker()->text,
                'full' => $this->getFaker()->text,
            ],
        ]);

        $data = [
            'title' => implode(' ', $this->getFaker()->words(3)),
            'address' => implode('-', $this->getFaker()->words(4)),
            'description' => $this->getFaker()->text(255),
            'pagination' => $this->getFaker()->numberBetween(10, 1000),
            'is_allow_nested' => $this->getFaker()->boolean,
            'is_public' => $this->getFaker()->boolean,
            'sort' => [
                'by' => $this->getFaker()->randomElement(\App\Domain\References\Publication::ORDER_BY),
                'direction' => $this->getFaker()->randomElement(\App\Domain\References\Publication::ORDER_DIRECTION),
            ],
            'meta' => [
                'title' => $this->getFaker()->word,
                'description' => $this->getFaker()->text,
                'keywords' => $this->getFaker()->text,
            ],
            'template' => [
                'list' => $this->getFaker()->word,
                'short' => $this->getFaker()->text,
                'full' => $this->getFaker()->text,
            ],
        ];

        $publicationCategory = $this->service->update($publicationCategory, $data);
        $this->assertInstanceOf(PublicationCategory::class, $publicationCategory);
        $this->assertEquals($data['title'], $publicationCategory->title);
        $this->assertEquals($data['address'], $publicationCategory->address);
        $this->assertEquals($data['description'], $publicationCategory->description);
        $this->assertEquals($data['pagination'], $publicationCategory->pagination);
        $this->assertEquals($data['is_allow_nested'], $publicationCategory->is_allow_nested);
        $this->assertEquals($data['is_public'], $publicationCategory->is_public);
        $this->assertEquals($data['sort'], $publicationCategory->sort);
        $this->assertEquals($data['meta'], $publicationCategory->meta);
        $this->assertEquals($data['template'], $publicationCategory->template);
    }

    public function testUpdateWithCategoryNotFound(): void
    {
        $this->expectException(CategoryNotFoundException::class);

        $this->service->update(null);
    }

    public function testDeleteSuccess(): void
    {
        $page = $this->service->create([
            'title' => implode(' ', $this->getFaker()->words(3)),
            'address' => implode('-', $this->getFaker()->words(4)),
            'description' => $this->getFaker()->text(255),
        ]);

        $result = $this->service->delete($page);

        $this->assertTrue($result);
    }

    public function testDeleteWithCategoryNotFound(): void
    {
        $this->expectException(CategoryNotFoundException::class);

        $this->service->delete(null);
    }
}
