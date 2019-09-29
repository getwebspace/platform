<?php

namespace App\Application\Actions\Cup\Task;

use App\Application\Actions\Action;

class TaskRunAction extends Action
{
    protected function action(): \Slim\Http\Response
    {
        return $this->respondWithData(['run' => time()]);
    }

    public function __destruct()
    {
        exec('php ' . CONFIG_DIR . '/cli-task.php > /dev/null 2>&1 &');
    }
}
