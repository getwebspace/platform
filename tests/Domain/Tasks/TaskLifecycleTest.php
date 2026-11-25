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
    }

    public function testOnlyOneWorkerHoldsTheLock(): void
    {
        $action = 'Tests\\Worker';
        $lockFile = VAR_DIR . '/tests-worker.worker.lock';

        // docker for mac does not enforce flock between processes on a bind mount
        $probe = VAR_DIR . '/tests-probe.lock';
        $held = fopen($probe, 'c');
        flock($held, LOCK_EX | LOCK_NB);
        exec(escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg('exit(flock(fopen(' . var_export($probe, true) . ', "c"), LOCK_EX | LOCK_NB) ? 0 : 1);'), $o, $enforced);
        fclose($held);
        @unlink($probe);
        if ($enforced !== 1) {
            $this->markTestSkipped('flock is not enforced between processes on this filesystem');
        }

        try {
            $this->assertTrue(AbstractTask::workerLock($action));

            // a second process cannot take it
            $code = 'require ' . var_export(__DIR__ . '/../../../vendor/autoload.php', true) . ';'
                . 'exit(\\App\\Domain\\AbstractTask::workerLock(' . var_export($action, true) . ') ? 0 : 1);';
            exec(escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg($code) . ' 2>&1', $out, $status);
            $this->assertSame(1, $status, implode("\n", $out));

            AbstractTask::workerUnlock($action);
            $this->assertFalse(AbstractTask::workerIsRunning($action));
            $this->assertTrue(AbstractTask::workerLock($action), 'free again after unlock');
        } finally {
            AbstractTask::workerUnlock($action);
            @unlink($lockFile);
        }
    }

    public function testATaskIsStuckByItsLastSignOfLife(): void
    {
        $now = new \DateTime('2026-01-05 12:00:00');
        $task = new \App\Domain\Models\Task();

        $task->date = new \DateTime('2026-01-05 10:55:00'); // 1 h 05 min ago
        $this->assertTrue(AbstractTask::isStuck($task, $now));

        $task->heartbeat = new \DateTime('2026-01-05 11:58:00');
        $this->assertFalse(AbstractTask::isStuck($task, $now), 'alive 2 minutes ago');

        $task->heartbeat = new \DateTime('2026-01-05 11:49:00');
        $this->assertTrue(AbstractTask::isStuck($task, $now));
    }

    private function taskContainerForTest(): \Psr\Container\ContainerInterface
    {
        $method = new \ReflectionMethod($this, 'taskContainer');

        return $method->invoke($this);
    }
}
