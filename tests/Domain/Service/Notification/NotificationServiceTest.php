<?php declare(strict_types=1);

namespace tests\Domain\Service\Notification;

use App\Domain\Entities\Notification;
use App\Domain\Repository\NotificationRepository;
use App\Domain\Service\Notification\Exception\MissingTitleValueException;
use App\Domain\Service\Notification\Exception\MissingUserUuidValueException;
use App\Domain\Service\Notification\Exception\NotificationNotFoundException;
use App\Domain\Service\Notification\NotificationService;
use tests\TestCase;

/**
 * @internal
 * @coversNothing
 */
class NotificationServiceTest extends TestCase
{
    protected NotificationService $service;

    public function setUp(): void
    {
        parent::setUp();

        $this->service = $this->getService(NotificationService::class);
    }

    public function testCreateSuccess(): void
    {
        $data = [
            'user_uuid' => $this->getFaker()->uuid,
            'title' => $this->getFaker()->title,
            'message' => $this->getFaker()->text,
            'params' => [
                'test' => $this->getFaker()->text,
            ],
            'date' => $this->getFaker()->dateTime,
        ];

        $n = $this->service->create($data);
        $this->assertInstanceOf(Notification::class, $n);
        $this->assertSame($data['user_uuid'], $n->getUserUuid()->toString());
        $this->assertSame($data['title'], $n->getTitle());
        $this->assertSame($data['message'], $n->getMessage());
        $this->assertSame($data['params'], $n->getParams());

        /** @var NotificationRepository $notificationRepo */
        $notificationRepo = $this->em->getRepository(Notification::class);
        $n = $notificationRepo->findOneByUuid($n->getUuid());
        $this->assertInstanceOf(Notification::class, $n);
        $this->assertSame($data['title'], $n->getTitle());
    }

    public function testCreateWithMissingUserUuidValue(): void
    {
        $this->expectException(MissingUserUuidValueException::class);

        $this->service->create([
            'user_uuid' => null,
        ]);
    }

    public function testCreateWithMissingTitleValue(): void
    {
        $this->expectException(MissingTitleValueException::class);

        $this->service->create([
            'user_uuid' => $this->getFaker()->uuid,
        ]);
    }

    public function testReadSuccess(): void
    {
        $data = [
            'user_uuid' => $this->getFaker()->uuid,
            'title' => $this->getFaker()->title,
            'message' => $this->getFaker()->text,
        ];

        $n = $this->service->create($data);

        $n = $this->service->read(['uuid' => $n->getUuid()]);
        $this->assertInstanceOf(Notification::class, $n);
        $this->assertSame($data['user_uuid'], $n->getUserUuid()->toString());
        $this->assertSame($data['title'], $n->getTitle());
        $this->assertSame($data['message'], $n->getMessage());
    }

    public function testReadWithNotificationNotFound(): void
    {
        $this->expectException(NotificationNotFoundException::class);

        $this->service->read(['uuid' => $this->getFaker()->uuid]);
    }

    public function testUpdateSuccess(): void
    {
        $n = $this->service->create([
            'user_uuid' => $this->getFaker()->uuid,
            'title' => $this->getFaker()->title,
            'message' => $this->getFaker()->text,
        ]);

        $data = [
            'user_uuid' => $this->getFaker()->uuid,
            'title' => $this->getFaker()->title,
            'message' => $this->getFaker()->text,
            'params' => [
                'test' => $this->getFaker()->text,
            ],
        ];

        $n = $this->service->update($n, $data);
        $this->assertSame($data['user_uuid'], $n->getUserUuid()->toString());
        $this->assertSame($data['title'], $n->getTitle());
        $this->assertSame($data['message'], $n->getMessage());
        $this->assertSame($data['params'], $n->getParams());
    }

    public function testUpdateWithNotificationNotFound(): void
    {
        $this->expectException(NotificationNotFoundException::class);

        $this->service->update(null);
    }

    public function testDeleteSuccess(): void
    {
        $n = $this->service->create([
            'user_uuid' => $this->getFaker()->uuid,
            'title' => $this->getFaker()->title,
            'message' => $this->getFaker()->text,
        ]);

        $result = $this->service->delete($n);

        $this->assertTrue($result);
    }

    public function testDeleteWithNotificationNotFound(): void
    {
        $this->expectException(NotificationNotFoundException::class);

        $this->service->delete(null);
    }
}
