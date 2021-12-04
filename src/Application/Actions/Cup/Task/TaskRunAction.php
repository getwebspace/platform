<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\Task;

use App\Domain\AbstractAction;

class TaskRunAction extends AbstractAction
{
    protected function action(): \Slim\Psr7\Response
    {
        if ($this->isPost()) {
            if (
                ($name = $this->getParam('task', null)) !== null
                && class_exists($name)
            ) {
                /** @var \App\Domain\AbstractTask $task */
                $task = new $name($this->container);
                $task->execute($this->getParam('params', []));

                // run worker
                \App\Domain\AbstractTask::worker($task);

                $this->response = $this->response->withAddedHeader('Location', '/cup')->withStatus(301);
            }
        }

        return $this->respondWithJson(['run' => time()]);
    }
}
