<?php declare(strict_types=1);

namespace tests\Domain\Service\Task;

use App\Domain\Entities\Task;
use App\Domain\Repository\TaskRepository;
use App\Domain\Service\Task\Exception\MissingActionValueException;
use App\Domain\Service\Task\Exception\MissingTitleValueException;
use App\Domain\Service\Task\Exception\TaskNotFoundException;
use App\Domain\Service\Task\TaskService;
use Doctrine\ORM\EntityManager;
use tests\TestCase;

class TaskServiceTest extends TestCase
{
    /**
     * @var EntityManager
     */
    protected $em;

    /**
     * @var TaskService
     */
    protected $service;

    public function setUp(): void
    {
        parent::setUp();
        $this->em = $this->getEntityManager();
        $this->service = TaskService::getWithEntityManager($this->em);
    }

    public function testCreateSuccess(): void
    {
        $data = [
            'title' => $this->getFaker()->title,
            'action' => $this->getFaker()->text,
            'progress' => (float) $this->getFaker()->numberBetween(10, 100),
            'status' => \App\Domain\Types\TaskStatusType::STATUS_QUEUE,
            'params' => [
                'test' => $this->getFaker()->numberBetween(0, 1000),
            ],
            'output' => $this->getFaker()->text,
        ];

        $t = $this->service->create($data);
        $this->assertInstanceOf(Task::class, $t);
        $this->assertSame($data['title'], $t->getTitle());
        $this->assertSame($data['action'], $t->getAction());
        $this->assertSame($data['progress'], $t->getProgress());
        $this->assertSame($data['status'], $t->getStatus());
        $this->assertSame($data['params'], $t->getParams());
        $this->assertSame($data['output'], $t->getOutput());

        /** @var TaskRepository $taskRepo */
        $taskRepo = $this->em->getRepository(Task::class);
        $t = $taskRepo->findOneByUuid($t->getUuid());
        $this->assertInstanceOf(Task::class, $t);
        $this->assertSame($data['title'], $t->getTitle());
    }

    public function testCreateWithMissingNameValue(): void
    {
        $this->expectException(MissingTitleValueException::class);

        $this->service->create([]);
    }

    public function testCreateWithMissingEmailValue(): void
    {
        $this->expectException(MissingActionValueException::class);

        $this->service->create([
            'title' => $this->getFaker()->userName,
        ]);
    }

    public function testReadSuccess(): void
    {
        $data = [
            'title' => $this->getFaker()->title,
            'action' => $this->getFaker()->text,
            'params' => [
                'test' => $this->getFaker()->text,
            ],
        ];

        $t = $this->service->create($data);

        $t = $this->service->read(['uuid' => $t->getUuid()]);
        $this->assertInstanceOf(Task::class, $t);
        $this->assertSame($data['title'], $t->getTitle());
        $this->assertSame($data['action'], $t->getAction());
        $this->assertSame($data['params'], $t->getParams());
    }

    public function testReadWithEntryNotFound(): void
    {
        $this->expectException(TaskNotFoundException::class);

        $this->service->read(['uuid' => $this->getFaker()->uuid]);
    }

    public function testUpdateSuccess(): void
    {
        $t = $this->service->create([
            'title' => $this->getFaker()->title,
            'action' => $this->getFaker()->text,
            'params' => [
                'test' => $this->getFaker()->numberBetween(0, 1000),
            ],
            'output' => $this->getFaker()->text,
        ]);

        $data = [
            'title' => $this->getFaker()->title,
            'action' => $this->getFaker()->text,
            'progress' => (float) $this->getFaker()->numberBetween(10, 100),
            'status' => \App\Domain\Types\TaskStatusType::STATUS_WORK,
            'params' => [
                'test' => $this->getFaker()->numberBetween(0, 1000),
            ],
            'output' => $this->getFaker()->text,
        ];

        $t = $this->service->update($t, $data);
        $this->assertSame($data['title'], $t->getTitle());
        $this->assertSame($data['action'], $t->getAction());
        $this->assertSame($data['progress'], $t->getProgress());
        $this->assertSame($data['status'], $t->getStatus());
        $this->assertSame($data['params'], $t->getParams());
        $this->assertSame($data['output'], $t->getOutput());
    }

    public function testUpdateWithTaskNotFound(): void
    {
        $this->expectException(TaskNotFoundException::class);

        $this->service->update(null);
    }

    public function testDeleteSuccess(): void
    {
        $t = $this->service->create([
            'title' => $this->getFaker()->title,
            'action' => $this->getFaker()->text,
        ]);

        $result = $this->service->delete($t);

        $this->assertTrue($result);
    }

    public function testDeleteWithTaskNotFound(): void
    {
        $this->expectException(TaskNotFoundException::class);

        $this->service->delete(null);
    }
}
