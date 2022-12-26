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
            'title' => $this->getFaker()->title,
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
                'title' => $this->getFaker()->title,
                'description' => $this->getFaker()->text,
                'keywords' => $this->getFaker()->text,
            ],
            'template' => [
                'list' => $this->getFaker()->title,
                'short' => $this->getFaker()->text,
                'full' => $this->getFaker()->text,
            ],
        ];

        $publicationCategory = $this->service->create($data);
        $this->assertInstanceOf(PublicationCategory::class, $publicationCategory);
        $this->assertSame($data['title'], $publicationCategory->getTitle());
        $this->assertSame($data['address'], $publicationCategory->getAddress());
        $this->assertSame($data['description'], $publicationCategory->getDescription());
        $this->assertSame($data['pagination'], $publicationCategory->getPagination());
        $this->assertSame($data['children'], $publicationCategory->getChildren());
        $this->assertSame($data['public'], $publicationCategory->getPublic());
        $this->assertSame($data['sort'], $publicationCategory->getSort());
        $this->assertSame($data['meta'], $publicationCategory->getMeta());
        $this->assertSame($data['template'], $publicationCategory->getTemplate());

        /** @var PublicationCategoryRepository $publicationCategoryRepo */
        $publicationCategoryRepo = $this->em->getRepository(PublicationCategory::class);
        $pc = $publicationCategoryRepo->findOneByTitle($data['title']);
        $this->assertInstanceOf(PublicationCategory::class, $pc);
        $this->assertSame($data['title'], $pc->getTitle());
        $this->assertSame($data['address'], $pc->getAddress());
        $this->assertSame($data['description'], $pc->getDescription());
        $this->assertSame($data['pagination'], $pc->getPagination());
        $this->assertSame($data['children'], $pc->getChildren());
        $this->assertSame($data['public'], $pc->getPublic());
        $this->assertSame($data['sort'], $pc->getSort());
        $this->assertSame($data['meta'], $pc->getMeta());
        $this->assertSame($data['template'], $pc->getTemplate());
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
            'title' => $this->getFaker()->title,
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
            'title' => $this->getFaker()->title,
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
            'title' => $this->getFaker()->title,
            'description' => $this->getFaker()->text(255),
        ];

        $this->service->create($data);

        $publicationCategory = $this->service->read(['title' => $data['title']]);
        $this->assertInstanceOf(PublicationCategory::class, $publicationCategory);
        $this->assertSame($data['title'], $publicationCategory->getTitle());
    }

    public function testReadSuccess2(): void
    {
        $data = [
            'title' => $this->getFaker()->title,
            'address' => 'some-custom-address',
            'description' => $this->getFaker()->text(255),
        ];

        $this->service->create($data);

        $publicationCategory = $this->service->read(['address' => $data['address']]);
        $this->assertInstanceOf(PublicationCategory::class, $publicationCategory);
        $this->assertSame($data['address'], $publicationCategory->getAddress());
    }

    public function testReadWithCategoryNotFound(): void
    {
        $this->expectException(CategoryNotFoundException::class);

        $this->service->read(['address' => $this->getFaker()->userName]);
    }

    public function testUpdate(): void
    {
        $publicationCategory = $this->service->create([
            'title' => $this->getFaker()->title,
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
                'title' => $this->getFaker()->title,
                'description' => $this->getFaker()->text,
                'keywords' => $this->getFaker()->text,
            ],
            'template' => [
                'list' => $this->getFaker()->title,
                'short' => $this->getFaker()->text,
                'full' => $this->getFaker()->text,
            ],
        ]);

        $data = [
            'title' => $this->getFaker()->title,
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
                'title' => $this->getFaker()->title,
                'description' => $this->getFaker()->text,
                'keywords' => $this->getFaker()->text,
            ],
            'template' => [
                'list' => $this->getFaker()->title,
                'short' => $this->getFaker()->text,
                'full' => $this->getFaker()->text,
            ],
        ];

        $publicationCategory = $this->service->update($publicationCategory, $data);
        $this->assertInstanceOf(PublicationCategory::class, $publicationCategory);
        $this->assertSame($data['title'], $publicationCategory->getTitle());
        $this->assertSame($data['address'], $publicationCategory->getAddress());
        $this->assertSame($data['description'], $publicationCategory->getDescription());
        $this->assertSame($data['pagination'], $publicationCategory->getPagination());
        $this->assertSame($data['children'], $publicationCategory->getChildren());
        $this->assertSame($data['public'], $publicationCategory->getPublic());
        $this->assertSame($data['sort'], $publicationCategory->getSort());
        $this->assertSame($data['meta'], $publicationCategory->getMeta());
        $this->assertSame($data['template'], $publicationCategory->getTemplate());
    }

    public function testUpdateWithCategoryNotFound(): void
    {
        $this->expectException(CategoryNotFoundException::class);

        $this->service->update(null);
    }

    public function testDeleteSuccess(): void
    {
        $page = $this->service->create([
            'title' => $this->getFaker()->title,
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
