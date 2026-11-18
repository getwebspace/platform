<?php declare(strict_types=1);

namespace tests\Concerns;

use App\Domain\AbstractTask;
use App\Domain\Models\Task;

/**
 * Queues a background task and runs it right here, the way the worker does
 */
trait RunsTasks
{
    protected function queueTask(string $class, array $params = []): Task
    {
        return (new $class($this->taskContainer()))->execute($params);
    }

    protected function runTask(string $class, array $params = []): Task
    {
        $entity = $this->queueTask($class, $params);

        /** @var AbstractTask $task */
        $task = new $class($this->taskContainer(), $entity);
        $task->run();

        return Task::find($entity->uuid);
    }

    private function taskContainer(): \Psr\Container\ContainerInterface
    {
        $property = new \ReflectionProperty(\tests\TestCase::class, 'container');

        return $property->getValue();
    }
}
