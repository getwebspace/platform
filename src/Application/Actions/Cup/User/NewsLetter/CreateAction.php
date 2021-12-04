<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\User\NewsLetter;

use App\Application\Actions\Cup\User\UserAction;

class CreateAction extends UserAction
{
    protected function action(): \Slim\Psr7\Response
    {
        if ($this->isPost()) {
            $task = new \App\Domain\Tasks\SendNewsLetterMailTask($this->container);
            $task->execute([
                'subject' => $this->getParam('subject'),
                'body' => $this->getParam('body'),
                'type' => $this->getParam('type'),
            ]);

            // run worker
            \App\Domain\AbstractTask::worker($task);

            return $this->respondWithRedirect('/cup/user/newsletter');
        }

        return $this->respondWithTemplate('cup/user/newsletter/form.twig');
    }
}
