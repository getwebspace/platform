<?php declare(strict_types=1);

namespace tests\Domain\Service\Task;

use App\Domain\Models\Task;
use App\Domain\Service\Task\Exception\MissingActionValueException;
use App\Domain\Service\Task\Exception\MissingTitleValueException;
use App\Domain\Service\Task\Exception\TaskNotFoundException;
use App\Domain\Service\Task\TaskService;
use tests\TestCase;

/**
 * @internal
 *
 * #[CoversNothing]
 */
class TaskServiceTest extends TestCase
{
    protected TaskService $service;

    public function setUp(): void
    {
        parent::setUp();

        $this->service = $this->getService(TaskService::class);
    }

    public function testCreateSuccess(): void
    {
        $data = [
            'title' => implode(' ', $this->getFaker()->words(3)),
            'action' => $this->getFaker()->word,
            'progress' => (float) $this->getFaker()->numberBetween(10, 100),
            'status' => $this->getFaker()->randomElement(\App\Domain\Casts\Task\Status::LIST),
            'params' => [
                'test' => $this->getFaker()->numberBetween(0, 1000),
            ],
            'output' => $this->getFaker()->text(200),
        ];

        $t = $this->service->create($data);
        $this->assertInstanceOf(Task::class, $t);
        $this->assertEquals($data['title'], $t->title);
        $this->assertEquals($data['action'], $t->action);
        $this->assertEquals($data['progress'], $t->progress);
        $this->assertEquals($data['status'], $t->status);
        $this->assertEquals($data['params'], $t->params);
        $this->assertEquals($data['output'], $t->output);
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
            'title' => implode(' ', $this->getFaker()->words(3)),
            'action' => $this->getFaker()->text,
            'params' => [
                'test' => $this->getFaker()->text,
            ],
        ];

        $t = $this->service->create($data);

        $t = $this->service->read(['uuid' => $t->uuid]);
        $this->assertInstanceOf(Task::class, $t);
        $this->assertEquals($data['title'], $t->title);
        $this->assertEquals($data['action'], $t->action);
        $this->assertEquals($data['params'], $t->params);
    }

    public function testReadWithEntryNotFound(): void
    {
        $this->expectException(TaskNotFoundException::class);

        $this->service->read(['uuid' => $this->getFaker()->uuid]);
    }

    public function testUpdateSuccess(): void
    {
        $t = $this->service->create([
            'title' => implode(' ', $this->getFaker()->words(3)),
            'action' => $this->getFaker()->text,
            'progress' => (float) $this->getFaker()->numberBetween(10, 100),
            'status' => $this->getFaker()->randomElement(\App\Domain\Casts\Task\Status::LIST),
            'params' => [
                'test' => $this->getFaker()->numberBetween(0, 1000),
            ],
            'output' => $this->getFaker()->text,
        ]);

        $data = [
            'title' => implode(' ', $this->getFaker()->words(3)),
            'action' => $this->getFaker()->text,
            'progress' => (float) $this->getFaker()->numberBetween(10, 100),
            'status' => $this->getFaker()->randomElement(\App\Domain\Casts\Task\Status::LIST),
            'params' => [
                'test' => $this->getFaker()->numberBetween(0, 1000),
            ],
            'output' => $this->getFaker()->text,
        ];

        $t = $this->service->update($t, $data);
        $this->assertInstanceOf(Task::class, $t);
        $this->assertEquals($data['title'], $t->title);
        $this->assertEquals($data['action'], $t->action);
        $this->assertEquals($data['progress'], $t->progress);
        $this->assertEquals($data['status'], $t->status);
        $this->assertEquals($data['params'], $t->params);
        $this->assertEquals($data['output'], $t->output);
    }

    public function testUpdateWithTaskNotFound(): void
    {
        $this->expectException(TaskNotFoundException::class);

        $this->service->update(null);
    }

    public function testDeleteSuccess(): void
    {
        $t = $this->service->create([
            'title' => implode(' ', $this->getFaker()->words(3)),
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
