<?php declare(strict_types=1);

namespace tests\Domain\Service\Publication;

use App\Domain\Entities\Publication;
use App\Domain\Entities\Publication\Category as PublicationCategory;
use App\Domain\Repository\PublicationRepository;
use App\Domain\Service\Publication\CategoryService as PublicationCategoryService;
use App\Domain\Service\Publication\Exception\AddressAlreadyExistsException;
use App\Domain\Service\Publication\Exception\MissingTitleValueException;
use App\Domain\Service\Publication\Exception\PublicationNotFoundException;
use App\Domain\Service\Publication\Exception\TitleAlreadyExistsException;
use App\Domain\Service\Publication\PublicationService;
use App\Domain\Service\User\UserService;
use tests\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
class PublicationServiceTest extends TestCase
{
    protected PublicationService $service;

    /**
     * @var PublicationCategory
     */
    protected $category;

    /**
     * @var UserService
     */
    protected $userService;

    public function setUp(): void
    {
        parent::setUp();

        $this->service = $this->getService(PublicationService::class);
        $this->userService = $this->getService(UserService::class);

        $this->category = $this->getService(PublicationCategoryService::class)->create([
            'title' => $this->getFaker()->word,
            'address' => 'category-custom-address',
            'description' => $this->getFaker()->text(255),
        ]);
    }

    public function testCreateSuccess(): void
    {
        $data = [
            'user' => $this->userService->create([
                'username' => $this->getFaker()->userName,
                'password' => $this->getFaker()->password,
                'email' => $this->getFaker()->email,
            ]),
            'title' => $this->getFaker()->word,
            'address' => 'publication-custom-address',
            'category' => $this->category,
            'date' => new \DateTime(),
            'content' => [
                'short' => $this->getFaker()->text(200),
                'full' => $this->getFaker()->realText(500),
            ],
            'meta' => [
                'title' => $this->getFaker()->text(150),
                'description' => $this->getFaker()->text(150),
                'keywords' => $this->getFaker()->text(150),
            ],
            'external_id' => $this->getFaker()->word,
        ];

        $publication = $this->service->create($data);
        $this->assertInstanceOf(Publication::class, $publication);
        $this->assertEquals($data['user'], $publication->getUser());
        $this->assertEquals($data['title'], $publication->getTitle());
        $this->assertEquals($data['address'], $publication->getAddress());
        $this->assertEquals($data['content'], $publication->getContent());
        $this->assertEquals($data['meta'], $publication->getMeta());
        $this->assertEquals($data['external_id'], $publication->getExternalId());

        /** @var PublicationRepository $publicationRepo */
        $publicationRepo = $this->em->getRepository(Publication::class);
        $p = $publicationRepo->findOneByTitle($data['title']);
        $this->assertInstanceOf(Publication::class, $p);
        $this->assertEquals($data['user'], $p->getUser());
        $this->assertEquals($data['title'], $p->getTitle());
        $this->assertEquals($data['address'], $p->getAddress());
        $this->assertEquals($data['content'], $p->getContent());
        $this->assertEquals($data['meta'], $p->getMeta());
        $this->assertEquals($data['external_id'], $p->getExternalId());
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
            'category' => $this->category,
            'content' => [
                'short' => $this->getFaker()->text(200),
                'full' => $this->getFaker()->realText(500),
            ],
        ];

        $this->service->create($data);
        $this->service->create($data);
    }

    public function testCreateWithAddressExistent(): void
    {
        $this->expectException(AddressAlreadyExistsException::class);

        $this->service->create([
            'title' => $this->getFaker()->word,
            'address' => 'publication-custom-address-two',
            'category' => $this->category,
            'content' => [
                'short' => $this->getFaker()->text(200),
                'full' => $this->getFaker()->realText(500),
            ],
        ]);

        $this->service->create([
            'title' => $this->getFaker()->word,
            'address' => 'publication-custom-address-two',
            'category' => $this->category,
            'content' => [
                'short' => $this->getFaker()->text(200),
                'full' => $this->getFaker()->realText(500),
            ],
        ]);
    }

    public function testReadSuccess1(): void
    {
        $data = [
            'title' => $this->getFaker()->word,
            'description' => $this->getFaker()->text(255),
            'category' => $this->category,
        ];

        $this->service->create($data);

        $publication = $this->service->read(['title' => $data['title']]);
        $this->assertInstanceOf(Publication::class, $publication);
        $this->assertEquals($data['title'], $publication->getTitle());
    }

    public function testReadSuccess2(): void
    {
        $data = [
            'title' => $this->getFaker()->word,
            'address' => 'publication-custom-address',
            'description' => $this->getFaker()->text(255),
            'category' => $this->category,
        ];

        $this->service->create($data);

        $publication = $this->service->read(['address' => $data['address']]);
        $this->assertInstanceOf(Publication::class, $publication);
        $this->assertEquals($data['address'], $publication->getAddress());
    }

    public function testReadWithPublicationNotFound(): void
    {
        $this->expectException(PublicationNotFoundException::class);

        $this->service->read(['address' => $this->getFaker()->userName]);
    }

    public function testUpdate(): void
    {
        $publication = $this->service->create([
            'user' => $this->userService->create([
                'username' => $this->getFaker()->userName,
                'password' => $this->getFaker()->password,
                'email' => $this->getFaker()->email,
            ]),
            'title' => $this->getFaker()->word,
            'address' => 'publication-custom-address',
            'category' => $this->category,
            'date' => new \DateTime(),
            'content' => [
                'short' => $this->getFaker()->text(200),
                'full' => $this->getFaker()->realText(500),
            ],
            'meta' => [
                'title' => $this->getFaker()->text(150),
                'description' => $this->getFaker()->text(150),
                'keywords' => $this->getFaker()->text(150),
            ],
            'external_id' => $this->getFaker()->word,
        ]);

        $data = [
            'user' => $this->userService->create([
                'username' => $this->getFaker()->userName,
                'password' => $this->getFaker()->password,
                'email' => $this->getFaker()->email,
            ]),
            'title' => $this->getFaker()->word,
            'address' => 'publication-custom-address',
            'category' => $this->category,
            'content' => [
                'short' => $this->getFaker()->text(200),
                'full' => $this->getFaker()->realText(500),
            ],
            'meta' => [
                'title' => $this->getFaker()->text(150),
                'description' => $this->getFaker()->text(150),
                'keywords' => $this->getFaker()->text(150),
            ],
            'external_id' => $this->getFaker()->word,
        ];

        $publication = $this->service->update($publication, $data);
        $this->assertInstanceOf(Publication::class, $publication);
        $this->assertEquals($data['user'], $publication->getUser());
        $this->assertEquals($data['title'], $publication->getTitle());
        $this->assertEquals($data['address'], $publication->getAddress());
        $this->assertEquals($data['category'], $publication->getCategory());
        $this->assertEquals($data['content'], $publication->getContent());
        $this->assertEquals($data['meta'], $publication->getMeta());
        $this->assertEquals($data['external_id'], $publication->getExternalId());
    }

    public function testUpdateWithPublicationNotFound(): void
    {
        $this->expectException(PublicationNotFoundException::class);

        $this->service->update(null);
    }

    public function testDeleteSuccess(): void
    {
        $page = $this->service->create([
            'title' => $this->getFaker()->word,
            'description' => $this->getFaker()->text(255),
            'category' => $this->category,
        ]);

        $result = $this->service->delete($page);

        $this->assertTrue($result);
    }

    public function testDeleteWithPublicationNotFound(): void
    {
        $this->expectException(PublicationNotFoundException::class);

        $this->service->delete(null);
    }
}
