<?php declare(strict_types=1);

namespace tests\Domain\Tasks;

use App\Domain\AbstractTask;
use App\Domain\Casts\Task\Status;
use App\Domain\Models\Task;
use App\Domain\Tasks\SearchIndexTask;
use App\Domain\Tasks\SendJSONTask;
use App\Application\PubSub;
use tests\Concerns\RunsTasks;
use tests\TestCase;

/**
 * What every background task shares: being queued, run, reporting progress
 * and leaving a trace
 *
 * @internal
 *
 * #[CoversNothing]
 */
class TaskLifecycleTest extends TestCase
{
    use RunsTasks;

    public function testExecuteOnlyQueuesTheTask(): void
    {
        $task = $this->queueTask(SendJSONTask::class, ['url' => 'http://127.0.0.1/forbidden', 'data' => ['a' => 1]]);

        $this->assertEquals(Status::QUEUE, $task->status);
        $this->assertEquals(SendJSONTask::class, $task->action);
        $this->assertEquals('Sending JSON', $task->title);
        $this->assertSame('http://127.0.0.1/forbidden', $task->params['url']);
        $this->assertSame(['a' => 1], $task->params['data']);
        $this->assertSame([], $task->params['files'], 'the defaults are filled in');
    }

    public function testAQueuedTaskCannotBeQueuedAgain(): void
    {
        $entity = $this->queueTask(SearchIndexTask::class);

        $this->expectException(\RuntimeException::class);

        (new SearchIndexTask($this->taskContainerForTest(), $entity))->execute();
    }

    public function testRunningMovesTheTaskToItsFinalStatus(): void
    {
        $task = $this->runTask(SearchIndexTask::class);

        $this->assertEquals(Status::DONE, $task->status);
    }

    public function testRunAnnouncesItsStartAndFinish(): void
    {
        $events = [];
        $pubSub = $this->getService(PubSub::class);
        $pubSub->subscribe(SearchIndexTask::class . ':start', function () use (&$events): void {
            $events[] = 'start';
        });
        $pubSub->subscribe(SearchIndexTask::class . ':finish', function () use (&$events): void {
            $events[] = 'finish';
        });

        $this->runTask(SearchIndexTask::class);

        $this->assertSame(['start', 'finish'], $events);
    }

    public function testProgressIsAPercentageOfTheCount(): void
    {
        $entity = $this->queueTask(SearchIndexTask::class);
        $task = new SearchIndexTask($this->taskContainerForTest(), $entity);

        $task->setProgress(1, 4);
        $this->assertEquals(25, Task::find($entity->uuid)->progress);

        $task->setProgress(9, 4);
        $this->assertEquals(100, Task::find($entity->uuid)->progress, 'never over a hundred');

        $task->setProgress(40);
        $this->assertEquals(40, Task::find($entity->uuid)->progress, 'without a count the value is the percentage');
    }

    public function testEveryFinalStatusIsStoredWithItsOutput(): void
    {
        foreach ([
            'setStatusDone' => Status::DONE,
            'setStatusFail' => Status::FAIL,
            'setStatusCancel' => Status::CANCEL,
            'setStatusDelete' => Status::DELETE,
        ] as $method => $status) {
            $entity = $this->queueTask(SearchIndexTask::class);
            $task = new SearchIndexTask($this->taskContainerForTest(), $entity);

            $task->{$method}('because ' . $method);

            $stored = Task::find($entity->uuid);
            $this->assertEquals($status, $stored->status, $method);
            $this->assertSame('because ' . $method, $stored->output, $method);
            $this->assertEquals($status, $task->getStatus(), $method);
        }
    }

    public function testOnlyDoneIsReportedAsSuccess(): void
    {
        $task = new SearchIndexTask($this->taskContainerForTest(), $this->queueTask(SearchIndexTask::class));

        $this->assertTrue($task->setStatusWork());
        $this->assertTrue($task->setStatusDone());
        $this->assertFalse($task->setStatusFail());
        $this->assertFalse($task->setStatusCancel());
        $this->assertFalse($task->setStatusDelete());
    }

    public function testTheWorkerIsNotStartedUnderTest(): void
    {
        // would otherwise fork a php process that holds the shared database
        $this->assertNull(AbstractTask::worker(SearchIndexTask::class));
        $this->assertFalse(AbstractTask::workerHasPidFile('tests-worker'));
    }

    public function testPidFileTracksARunningWorker(): void
    {
        $action = 'Tests\\Worker';
        $pidFile = VAR_DIR . '/tests-worker.worker.pid';

        try {
            AbstractTask::workerCreatePidFile($action);
            $this->assertFileExists($pidFile);
            $this->assertTrue(AbstractTask::workerHasPidFile($action), 'this very process is running');

            AbstractTask::workerRemovePidFile($action);
            $this->assertFileDoesNotExist($pidFile);
            $this->assertFalse(AbstractTask::workerHasPidFile($action));
        } finally {
            @unlink($pidFile);
        }
    }

    public function testAStalePidFileIsRemoved(): void
    {
        $action = 'Tests\\Stale';
        $pidFile = VAR_DIR . '/tests-stale.worker.pid';

        try {
            // a pid that cannot belong to a live process
            file_put_contents($pidFile, '99999999');

            $this->assertFalse(AbstractTask::workerHasPidFile($action));
            $this->assertFileDoesNotExist($pidFile, 'the leftover is cleaned up');
        } finally {
            @unlink($pidFile);
        }
    }

    private function taskContainerForTest(): \Psr\Container\ContainerInterface
    {
        $method = new \ReflectionMethod($this, 'taskContainer');

        return $method->invoke($this);
    }
}
