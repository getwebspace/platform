<?php declare(strict_types=1);

namespace tests\Domain\Tasks;

use App\Application\PubSub;
use App\Domain\Casts\Task\Status;
use App\Domain\Service\Page\PageService;
use App\Domain\Service\Search\SearchService;
use App\Domain\Tasks\SearchIndexTask;
use App\Domain\Tasks\SendJSONTask;
use tests\Concerns\RunsTasks;
use tests\TestCase;

/**
 * @internal
 *
 * #[CoversNothing]
 */
class SimpleTasksTest extends TestCase
{
    use RunsTasks;

    public function testSearchIndexTaskBuildsTheIndexAndAnnouncesIt(): void
    {
        $this->getService(PageService::class)->create(['title' => 'Warranty conditions', 'address' => 'warranty', 'content' => 'two years']);

        $announced = 0;
        $this->getService(PubSub::class)->subscribe('task:search:indexed', function () use (&$announced): void {
            ++$announced;
        });

        $task = $this->runTask(SearchIndexTask::class);

        $this->assertEquals(Status::DONE, $task->status);
        $this->assertSame(1, $announced);

        $found = $this->getService(SearchService::class)->search('Warranty', [], 10, 0);
        $this->assertSame(1, $found['total']);
    }

    public function testSearchIndexTaskIgnoresParameters(): void
    {
        $task = $this->queueTask(SearchIndexTask::class, ['anything' => 'at all']);

        $this->assertSame([], $task->params);
    }

    public function testJsonIsPostedToTheAddress(): void
    {
        // the application itself answers a POST to the subscription form with a 200
        $task = $this->runTask(SendJSONTask::class, ['url' => 'http://127.0.0.1/user/subscriber', 'data' => ['order' => 7]]);

        $this->assertEquals(Status::DONE, $task->status, $task->output);
        $this->assertNotSame('', $task->output, 'the answer is kept as the output');
    }

    public function testJsonSendWithFilesStillGoesThrough(): void
    {
        $task = $this->runTask(SendJSONTask::class, [
            'url' => 'http://127.0.0.1/user/subscriber',
            'data' => ['order' => 7],
            'files' => ['a.txt' => 'http://example.invalid/a.txt'],
        ]);

        $this->assertEquals(Status::DONE, $task->status);
    }

    public function testJsonSendFailsWhenTheAddressDoesNotAnswer(): void
    {
        $task = $this->runTask(SendJSONTask::class, ['url' => 'http://127.0.0.1:9/nothing-listens-here', 'data' => []]);

        $this->assertEquals(Status::FAIL, $task->status);
        $this->assertStringContainsString('Connection refused', $task->output, 'the reason is kept');
    }

    public function testJsonSendFailsWhenTheAddressAnswersWithAnError(): void
    {
        $task = $this->runTask(SendJSONTask::class, ['url' => 'http://127.0.0.1/api/v1/signature-does-not-exist-' . uniqid(), 'data' => []]);

        // file_get_contents() treats a 4xx as a failure
        $this->assertEquals(Status::FAIL, $task->status);
        $this->assertStringContainsString('401', $task->output);
    }

    public function testJsonSendAnnouncesTheSentRequest(): void
    {
        $announced = 0;
        $this->getService(PubSub::class)->subscribe('task:json:send', function () use (&$announced): void {
            ++$announced;
        });

        $this->runTask(SendJSONTask::class, ['url' => 'http://127.0.0.1/user/subscriber']);

        $this->assertSame(1, $announced);
    }
}
