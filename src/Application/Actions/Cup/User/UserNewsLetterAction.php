<?php

namespace App\Application\Actions\Cup\User;

class UserNewsLetterAction extends UserAction
{
    protected function action(): \Slim\Http\Response
    {
        if ($this->request->isPost()) {
            $data = [
                'subject' => $this->request->getParam('subject'),
                'body' => $this->request->getParam('body'),
            ];

            $check = \App\Domain\Filters\User::newsletter($data);

            if ($check === true) {
                $list = $this->userRepository->findBy(['allow_mail' => true]);

                /** @var \App\Domain\Entities\User $user */
                foreach ($list as $user) {
                    $task = new \App\Domain\Tasks\SendMailTask($this->container);
                    $task->execute([
                        'to' => $user->email,
                        'subject' => $data['subject'],
                        'body' => $data['body'],
                    ]);
                }

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
