<?php

namespace App\Application\Actions\Cup\User\NewsLetter;

use App\Application\Actions\Cup\User\UserAction;

class CreateAction extends UserAction
{
    protected function action(): \Slim\Http\Response
    {
        if ($this->request->isPost()) {
            $data = [
                'subject' => $this->request->getParam('subject'),
                'body' => $this->request->getParam('body'),
                'type' => $this->request->getParam('type'),
            ];

            $check = \App\Domain\Filters\User::newsletter($data);

            if ($check === true) {
                $task = new \App\Domain\Tasks\SendNewsLetterMailTask($this->container);
                $task->execute([
                    'subject' => $data['subject'],
                    'body' => $data['body'],
                    'type' => $data['type'],
                ]);

                $this->entityManager->flush();

                // run worker
                \App\Domain\Tasks\Task::worker();

                return $this->response->withAddedHeader('Location', '/cup/user/newsletter')->withStatus(301);
            } else {
                $this->addErrorFromCheck($check);
            }
        }

        return $this->respondRender('cup/user/newsletter/form.twig');
    }
}
