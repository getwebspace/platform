<?php declare(strict_types=1);

namespace tests\Domain\Service\Publication;

use App\Domain\Models\Publication;
use App\Domain\Models\PublicationCategory;
use App\Domain\Models\User;
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

    /**
     * @var User
     */
    protected $user;

    public function setUp(): void
    {
        parent::setUp();

        $this->service = $this->getService(PublicationService::class);
        $this->userService = $this->getService(UserService::class);

        $this->category = $this->getService(PublicationCategoryService::class)->create([
            'title' => implode(' ', $this->getFaker()->words(3)),
            'address' => implode('-', $this->getFaker()->words(4)),
            'description' => $this->getFaker()->text(255),
        ]);

        $this->user = $this->userService->create([
            'username' => $this->getFaker()->userName,
            'password' => $this->getFaker()->password,
            'email' => $this->getFaker()->email,
        ]);
    }

    public function testCreateSuccess(): void
    {
        $data = [
            'title' => implode(' ', $this->getFaker()->words(3)),
            'address' => implode('-', $this->getFaker()->words(4)),
            'category_uuid' => $this->category->uuid,
            'user_uuid' => $this->user->uuid,
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
        $this->assertEquals($data['title'], $publication->title);
        $this->assertEquals($data['address'], $publication->address);
        $this->assertEquals($data['content'], $publication->content);
        $this->assertEquals($data['meta'], $publication->meta);
        $this->assertEquals($data['external_id'], $publication->external_id);
        $this->assertEquals($this->user->attributesToArray(), $publication->user->attributesToArray());
        $this->assertEquals($this->category->attributesToArray(), $publication->category->attributesToArray());
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
            'category_uuid' => $this->category->uuid,
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

        $data = [
            'title' => implode(' ', $this->getFaker()->words(3)),
            'address' => implode('-', $this->getFaker()->words(4)),
            'category_uuid' => $this->category->uuid,
            'content' => [
                'short' => $this->getFaker()->text(200),
                'full' => $this->getFaker()->realText(500),
            ],
        ];

        Publication::create($data);

        $this->service->create(array_merge($data, ['title' => implode(' ', $this->getFaker()->words(3))]));
    }

    public function testReadSuccess1(): void
    {
        $data = [
            'title' => implode(' ', $this->getFaker()->words(3)),
            'address' => implode('-', $this->getFaker()->words(4)),
            'category_uuid' => $this->category->uuid,
        ];

        $this->service->create($data);

        $publication = $this->service->read(['title' => $data['title']]);
        $this->assertInstanceOf(Publication::class, $publication);
        $this->assertEquals($data['title'], $publication->title);
        $this->assertEquals($data['address'], $publication->address);
    }

    public function testReadSuccess2(): void
    {
        $data = [
            'title' => implode(' ', $this->getFaker()->words(3)),
            'address' => implode('-', $this->getFaker()->words(4)),
            'description' => $this->getFaker()->text(255),
            'category_uuid' => $this->category->uuid,
        ];

        $this->service->create($data);

        $publication = $this->service->read(['address' => $data['address']]);
        $this->assertInstanceOf(Publication::class, $publication);
        $this->assertEquals($data['title'], $publication->title);
        $this->assertEquals($data['address'], $publication->address);
    }

    public function testReadWithPublicationNotFound(): void
    {
        $this->expectException(PublicationNotFoundException::class);

        $this->service->read(['address' => $this->getFaker()->userName]);
    }

    public function testUpdate(): void
    {
        $publication = $this->service->create([
            'title' => implode(' ', $this->getFaker()->words(3)),
            'address' => implode('-', $this->getFaker()->words(4)),
            'category_uuid' => $this->category->uuid,
            'user_uuid' => $this->user->uuid,
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

        $this->user = $this->userService->create([
            'username' => $this->getFaker()->userName,
            'password' => $this->getFaker()->password,
            'email' => $this->getFaker()->email,
        ]);

        $data = [
            'user_uuid' => $this->user->uuid,
            'title' => implode(' ', $this->getFaker()->words(3)),
            'address' => implode('-', $this->getFaker()->words(4)),
            'category_uuid' => $this->category->uuid,
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
        $this->assertEquals($data['title'], $publication->title);
        $this->assertEquals($data['address'], $publication->address);
        $this->assertEquals($data['content'], $publication->content);
        $this->assertEquals($data['meta'], $publication->meta);
        $this->assertEquals($data['external_id'], $publication->external_id);
        $this->assertEquals($this->user->attributesToArray(), $publication->user->attributesToArray());
        $this->assertEquals($this->category->attributesToArray(), $publication->category->attributesToArray());
    }

    public function testUpdateWithPublicationNotFound(): void
    {
        $this->expectException(PublicationNotFoundException::class);

        $this->service->update(null);
    }

    public function testDeleteSuccess(): void
    {
        $page = $this->service->create([
            'title' => implode(' ', $this->getFaker()->words(3)),
            'address' => implode('-', $this->getFaker()->words(4)),
            'category_uuid' => $this->category->uuid,
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
