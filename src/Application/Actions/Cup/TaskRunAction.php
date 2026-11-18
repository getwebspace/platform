<?php declare(strict_types=1);

namespace App\Application\Actions\Cup;

use App\Domain\AbstractAction;

class TaskRunAction extends AbstractAction
{
    protected function action(): \Slim\Psr7\Response
    {
        $redirect = $this->backTo('/cup');

        if ($this->isPost()) {
            if (($name = $this->getParam('task', null)) !== null && is_string($name) && is_subclass_of($name, \App\Domain\AbstractTask::class)) {
                /** @var \App\Domain\AbstractTask $task */
                $task = new $name($this->container);
                $task->execute($this->getParam('params', []));

                // run worker
                \App\Domain\AbstractTask::worker($task);

                $this->container->get(\App\Application\PubSub::class)->publish('cup:task:run', $task);

                $this->response = $this->respondWithRedirect($redirect);
            }
        }

        return $this->respondWithJson(['run' => time()]);
    }
}
