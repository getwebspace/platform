<?php declare(strict_types=1);

namespace tests\Domain\Service\Publication;

use App\Domain\Entities\Publication\Category as PublicationCategory;
use App\Domain\Repository\Publication\CategoryRepository as PublicationCategoryRepository;
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
            'title' => $this->getFaker()->word,
            'address' => 'some-custom-address',
            'description' => $this->getFaker()->text(255),
            'pagination' => $this->getFaker()->numberBetween(10, 1000),
            'children' => $this->getFaker()->boolean,
            'public' => $this->getFaker()->boolean,
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
        $this->assertEquals($data['title'], $publicationCategory->getTitle());
        $this->assertEquals($data['address'], $publicationCategory->getAddress());
        $this->assertEquals($data['description'], $publicationCategory->getDescription());
        $this->assertEquals($data['pagination'], $publicationCategory->getPagination());
        $this->assertEquals($data['children'], $publicationCategory->getChildren());
        $this->assertEquals($data['public'], $publicationCategory->getPublic());
        $this->assertEquals($data['sort'], $publicationCategory->getSort());
        $this->assertEquals($data['meta'], $publicationCategory->getMeta());
        $this->assertEquals($data['template'], $publicationCategory->getTemplate());

        /** @var PublicationCategoryRepository $publicationCategoryRepo */
        $publicationCategoryRepo = $this->em->getRepository(PublicationCategory::class);
        $pc = $publicationCategoryRepo->findOneByTitle($data['title']);
        $this->assertInstanceOf(PublicationCategory::class, $pc);
        $this->assertEquals($data['title'], $pc->getTitle());
        $this->assertEquals($data['address'], $pc->getAddress());
        $this->assertEquals($data['description'], $pc->getDescription());
        $this->assertEquals($data['pagination'], $pc->getPagination());
        $this->assertEquals($data['children'], $pc->getChildren());
        $this->assertEquals($data['public'], $pc->getPublic());
        $this->assertEquals($data['sort'], $pc->getSort());
        $this->assertEquals($data['meta'], $pc->getMeta());
        $this->assertEquals($data['template'], $pc->getTemplate());
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
            'title' => $this->getFaker()->word,
            'description' => $this->getFaker()->text(255),
        ];

        $publicationCategory = (new PublicationCategory())
            ->setTitle($data['title'])
            ->setDescription($data['description']);

        $this->em->persist($publicationCategory);
        $this->em->flush();

        $this->service->create($data);
    }

    public function testCreateWithAddressExistent(): void
    {
        $this->expectException(AddressAlreadyExistsException::class);

        $data = [
            'title' => $this->getFaker()->word,
            'address' => 'some-custom-address',
            'description' => $this->getFaker()->text(255),
        ];

        $publicationCategory = (new PublicationCategory())
            ->setTitle($data['title'] . '-miss')
            ->setAddress($data['address'])
            ->setDescription($data['description']);

        $this->em->persist($publicationCategory);
        $this->em->flush();

        $this->service->create($data);
    }

    public function testReadSuccess1(): void
    {
        $data = [
            'title' => $this->getFaker()->word,
            'description' => $this->getFaker()->text(255),
        ];

        $this->service->create($data);

        $publicationCategory = $this->service->read(['title' => $data['title']]);
        $this->assertInstanceOf(PublicationCategory::class, $publicationCategory);
        $this->assertEquals($data['title'], $publicationCategory->getTitle());
    }

    public function testReadSuccess2(): void
    {
        $data = [
            'title' => $this->getFaker()->word,
            'address' => 'some-custom-address',
            'description' => $this->getFaker()->text(255),
        ];

        $this->service->create($data);

        $publicationCategory = $this->service->read(['address' => $data['address']]);
        $this->assertInstanceOf(PublicationCategory::class, $publicationCategory);
        $this->assertEquals($data['address'], $publicationCategory->getAddress());
    }

    public function testReadWithCategoryNotFound(): void
    {
        $this->expectException(CategoryNotFoundException::class);

        $this->service->read(['address' => $this->getFaker()->userName]);
    }

    public function testUpdate(): void
    {
        $publicationCategory = $this->service->create([
            'title' => $this->getFaker()->word,
            'address' => 'some-custom-address',
            'description' => $this->getFaker()->text(255),
            'pagination' => $this->getFaker()->numberBetween(10, 1000),
            'children' => $this->getFaker()->boolean,
            'public' => $this->getFaker()->boolean,
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
            'title' => $this->getFaker()->word,
            'address' => 'some-custom-address',
            'description' => $this->getFaker()->text(255),
            'pagination' => $this->getFaker()->numberBetween(10, 1000),
            'children' => $this->getFaker()->boolean,
            'public' => $this->getFaker()->boolean,
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
        $this->assertEquals($data['title'], $publicationCategory->getTitle());
        $this->assertEquals($data['address'], $publicationCategory->getAddress());
        $this->assertEquals($data['description'], $publicationCategory->getDescription());
        $this->assertEquals($data['pagination'], $publicationCategory->getPagination());
        $this->assertEquals($data['children'], $publicationCategory->getChildren());
        $this->assertEquals($data['public'], $publicationCategory->getPublic());
        $this->assertEquals($data['sort'], $publicationCategory->getSort());
        $this->assertEquals($data['meta'], $publicationCategory->getMeta());
        $this->assertEquals($data['template'], $publicationCategory->getTemplate());
    }

    public function testUpdateWithCategoryNotFound(): void
    {
        $this->expectException(CategoryNotFoundException::class);

        $this->service->update(null);
    }

    public function testDeleteSuccess(): void
    {
        $page = $this->service->create([
            'title' => $this->getFaker()->word,
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
