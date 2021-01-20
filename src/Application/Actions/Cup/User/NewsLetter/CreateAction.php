<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\User\NewsLetter;

use App\Application\Actions\Cup\User\UserAction;

class CreateAction extends UserAction
{
    protected function action(): \Slim\Http\Response
    {
        if ($this->request->isPost()) {
            $task = new \App\Domain\Tasks\SendNewsLetterMailTask($this->container);
            $task->execute([
                'subject' => $this->request->getParam('subject'),
                'body' => $this->request->getParam('body'),
                'type' => $this->request->getParam('type'),
            ]);

            // run worker
            \App\Domain\AbstractTask::worker($task);

            return $this->response->withRedirect('/cup/user/newsletter');
        }

        return $this->respondWithTemplate('cup/user/newsletter/form.twig');
    }
}
