<?php

namespace App\Application\Actions\Cup\Task;

use AEngine\Support\Str;
use App\Application\Actions\Action;

class TaskRunAction extends Action
{
    protected function action(): \Slim\Http\Response
    {
        if ($this->request->isPost() && ($name = $this->request->getParam('task', null)) !== null) {
            if (Str::start('App\Domain\Tasks', $name) && class_exists($name)) {
                /** @var \App\Domain\Tasks\Task $task */
                $task = new $name($this->container);
                $task->execute($this->request->getParam('params', []));
                $this->entityManager->flush();
            }
            return $this->response->withAddedHeader('Location', '/cup');
        }

        return $this->respondWithData(['run' => time()]);
    }

    public function __destruct()
    {
        exec('php ' . CONFIG_DIR . '/cli-task.php > /dev/null 2>&1 &');
    }
}
