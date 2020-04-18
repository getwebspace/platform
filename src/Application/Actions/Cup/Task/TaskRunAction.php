<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\Task;

use App\Domain\AbstractAction;

class TaskRunAction extends AbstractAction
{
    protected function action(): \Slim\Http\Response
    {
        if ($this->request->isPost()) {
            if (
                ($name = $this->request->getParam('task', null)) !== null &&
                class_exists($name)
            ) {
                /** @var \App\Domain\AbstractTask $task */
                $task = new $name($this->container);
                $task->execute($this->request->getParam('params', []));
                $this->entityManager->flush();
                $this->response = $this->response->withAddedHeader('Location', '/cup')->withStatus(301);
            }

            // run worker
            \App\Domain\AbstractTask::worker();
        }

        return $this->respondWithJson(['run' => time()]);
    }
}
