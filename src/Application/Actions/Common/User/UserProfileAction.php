<?php

namespace App\Application\Actions\Common\User;

use Exception;

class UserProfileAction extends UserAction
{
    protected function action(): \Slim\Http\Response
    {
        /** @var \App\Domain\Entities\User $user */
        $user = $this->request->getAttribute('user', false);

        if ($user && $this->request->isPost()) {
            $data = [
                'uuid' => $user->uuid,
                'firstname' => $this->request->getParam('firstname'),
                'lastname' => $this->request->getParam('lastname'),
                'email' => $this->request->getParam('email'),
                'phone' => $this->request->getParam('phone'),
                'password' => $this->request->getParam('password'),
            ];

            $check = \App\Domain\Filters\User::check($data);

            if ($check === true) {
                try {
                    $user->replace($data);
                    $user->change = new \DateTime();
                    $this->entityManager->flush();

                    return $this->response->withAddedHeader('Location', '/user/profile')->withStatus(301);
                } catch (Exception $e) {
                    // todo nothing
                }
            } else {
                $this->addErrorFromCheck($check);
            }
        }

        return $this->respondRender($this->getParameter('user_profile_template', 'user.profile.twig'));
    }
}
