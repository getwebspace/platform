<?php declare(strict_types=1);

namespace tests\Domain\Service\User;

use App\Domain\Models\UserGroup;
use App\Domain\Service\User\Exception\MissingTitleValueException;
use App\Domain\Service\User\Exception\TitleAlreadyExistsException;
use App\Domain\Service\User\Exception\UserGroupNotFoundException;
use App\Domain\Service\User\GroupService as UserGroupService;
use tests\TestCase;

/**
 * @internal
 *
 * #[CoversNothing]
 */
class GroupServiceTest extends TestCase
{
    protected UserGroupService $service;

    public function setUp(): void
    {
        parent::setUp();

        $this->service = $this->getService(UserGroupService::class);
    }

    public function testCreateSuccess(): void
    {
        $data = [
            'title' => $this->getFaker()->word,
            'description' => $this->getFaker()->text,
            'access' => explode(' ', $this->getFaker()->text),
        ];

        $userGroup = $this->service->create($data);
        $this->assertInstanceOf(UserGroup::class, $userGroup);
        $this->assertEquals($data['title'], $userGroup->title);
        $this->assertEquals($data['description'], $userGroup->description);
        $this->assertEquals($data['access'], $userGroup->access);
    }

    public function testCreateWithMissingTitle(): void
    {
        $this->expectException(MissingTitleValueException::class);

        $this->service->create();
    }

    public function testCreateWithTitleExistent(): void
    {
        $this->expectException(TitleAlreadyExistsException::class);

        $data = [
            'title' => $this->getFaker()->word,
            'description' => $this->getFaker()->text,
            'access' => explode(' ', $this->getFaker()->text),
        ];

        UserGroup::create($data);

        $this->service->create($data);
    }

    public function testReadSuccess(): void
    {
        $data = [
            'title' => $this->getFaker()->word,
            'description' => $this->getFaker()->text,
            'access' => explode(' ', $this->getFaker()->text),
        ];

        $this->service->create($data);

        $userGroup = $this->service->read(['title' => $data['title']]);
        $this->assertInstanceOf(UserGroup::class, $userGroup);
        $this->assertEquals($data['title'], $userGroup->title);
        $this->assertEquals($data['description'], $userGroup->description);
        $this->assertEquals($data['access'], $userGroup->access);
    }

    public function testReadWithUserGroupNotFound(): void
    {
        $this->expectException(UserGroupNotFoundException::class);

        $this->service->read(['title' => $this->getFaker()->uuid]);
    }

    public function testUpdate(): void
    {
        $userGroup = $this->service->create([
            'title' => $this->getFaker()->word,
            'description' => $this->getFaker()->text,
            'access' => explode(' ', $this->getFaker()->text),
        ]);

        $data = [
            'title' => $this->getFaker()->word,
            'description' => $this->getFaker()->text,
            'access' => explode(' ', $this->getFaker()->text),
        ];

        $userGroup = $this->service->update($userGroup, $data);
        $this->assertEquals($data['title'], $userGroup->title);
        $this->assertEquals($data['description'], $userGroup->description);
        $this->assertEquals($data['access'], $userGroup->access);
    }

    public function testUpdateWithUserGroupNotFound(): void
    {
        $this->expectException(UserGroupNotFoundException::class);

        $this->service->update(null);
    }

    public function testDelete(): void
    {
        $page = $this->service->create([
            'title' => $this->getFaker()->word,
            'description' => $this->getFaker()->text,
        ]);

        $result = $this->service->delete($page);

        $this->assertTrue($result);
    }

    public function testDeleteWithNotFound(): void
    {
        $this->expectException(UserGroupNotFoundException::class);

        $this->service->delete(null);
    }
}
