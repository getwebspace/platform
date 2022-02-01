<?php declare(strict_types=1);

namespace tests\Domain\Service\User;

use App\Domain\Entities\User\Group as UserGroup;
use App\Domain\Repository\User\GroupRepository as GroupServiceRepository;
use App\Domain\Service\User\Exception\MissingTitleValueException;
use App\Domain\Service\User\Exception\TitleAlreadyExistsException;
use App\Domain\Service\User\Exception\UserGroupNotFoundException;
use App\Domain\Service\User\GroupService as UserGroupService;
use tests\TestCase;

/**
 * @internal
 * @coversNothing
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
            'title' => $this->getFaker()->title,
            'description' => $this->getFaker()->text,
            'access' => explode(' ', $this->getFaker()->text),
        ];

        $userGroup = $this->service->create($data);
        $this->assertInstanceOf(UserGroup::class, $userGroup);
        $this->assertSame($data['title'], $userGroup->getTitle());
        $this->assertSame($data['description'], $userGroup->getDescription());
        $this->assertSame($data['access'], $userGroup->getAccess());

        /** @var GroupServiceRepository $userGroupRepo */
        $userGroupRepo = $this->em->getRepository(UserGroup::class);
        $ug = $userGroupRepo->findOneByTitle($data['title']);
        $this->assertInstanceOf(UserGroup::class, $ug);
        $this->assertSame($data['title'], $ug->getTitle());
        $this->assertSame($data['description'], $ug->getDescription());
        $this->assertSame($data['access'], $ug->getAccess());
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
            'title' => $this->getFaker()->title,
            'description' => $this->getFaker()->text,
            'access' => explode(' ', $this->getFaker()->text),
        ];

        $userGroup = (new UserGroup())
            ->setTitle($data['title'])
            ->setDescription($data['description'])
            ->setAccess($data['access']);

        $this->em->persist($userGroup);
        $this->em->flush();

        $this->service->create($data);
    }

    public function testReadSuccess(): void
    {
        $data = [
            'title' => $this->getFaker()->title,
            'description' => $this->getFaker()->text,
            'access' => explode(' ', $this->getFaker()->text),
        ];

        $this->service->create($data);

        $userGroup = $this->service->read(['title' => $data['title']]);
        $this->assertInstanceOf(UserGroup::class, $userGroup);
        $this->assertSame($data['title'], $userGroup->getTitle());
        $this->assertSame($data['description'], $userGroup->getDescription());
        $this->assertSame($data['access'], $userGroup->getAccess());
    }

    public function testReadWithUserGroupNotFound(): void
    {
        $this->expectException(UserGroupNotFoundException::class);

        $this->service->read(['title' => $this->getFaker()->uuid]);
    }

    public function testUpdate(): void
    {
        $userGroup = $this->service->create([
            'title' => $this->getFaker()->title,
            'description' => $this->getFaker()->text,
            'access' => explode(' ', $this->getFaker()->text),
        ]);

        $data = [
            'title' => $this->getFaker()->title,
            'description' => $this->getFaker()->text,
            'access' => explode(' ', $this->getFaker()->text),
        ];

        $userGroup = $this->service->update($userGroup, $data);
        $this->assertSame($data['title'], $userGroup->getTitle());
        $this->assertSame($data['description'], $userGroup->getDescription());
        $this->assertSame($data['access'], $userGroup->getAccess());
    }

    public function testUpdateWithUserGroupNotFound(): void
    {
        $this->expectException(UserGroupNotFoundException::class);

        $this->service->update(null);
    }

    public function testDelete(): void
    {
        $page = $this->service->create([
            'title' => $this->getFaker()->title,
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
