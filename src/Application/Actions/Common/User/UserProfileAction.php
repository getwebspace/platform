<?php declare(strict_types=1);

namespace App\Application\Actions\Common\User;

class UserProfileAction extends UserAction
{
    protected function action(): \Slim\Http\Response
    {
        /** @var \App\Domain\Entities\User $user */
        $user = $this->request->getAttribute('user', false);

        if ($user && $this->request->isPost()) {
            $data = [
                'firstname' => $this->request->getParam('firstname'),
                'lastname' => $this->request->getParam('lastname'),
                'email' => $this->request->getParam('email'),
                'phone' => $this->request->getParam('phone'),
                'password' => $this->request->getParam('password'),
            ];

            $user
                ->setFirstname($data['firstname'])
                ->setLastname($data['lastname'])
                ->setEmail($data['email'])
                ->setPhone($data['phone'])
                ->setPassword($data['password'])
                ->setChange('now');

            $this->entityManager->flush();

            return $this->response->withRedirect('/user/profile');
        }

        return $this->respondWithTemplate($this->getParameter('user_profile_template', 'user.profile.twig'));
    }
}
