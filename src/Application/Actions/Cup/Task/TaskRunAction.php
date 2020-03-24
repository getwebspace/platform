<?php

namespace App\Application\Actions\Cup\Task;

use Alksily\Support\Str;
use App\Application\Actions\Action;

class TaskRunAction extends Action
{
    protected function action(): \Slim\Http\Response
    {
        if ($this->request->isPost()) {
            if (
                ($name = $this->request->getParam('task', null)) !== null &&
                class_exists($name)
            ) {
                /** @var \App\Domain\Tasks\Task $task */
                $task = new $name($this->container);
                $task->execute($this->request->getParam('params', []));
                $this->entityManager->flush();
                $this->response = $this->response->withAddedHeader('Location', '/cup')->withStatus(301);
            }

            // run worker
            \App\Domain\Tasks\Task::worker();
        }

        return $this->respondWithData(['run' => time()]);
    }
}
