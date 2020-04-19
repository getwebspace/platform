<?php declare(strict_types=1);

namespace App\Application\Actions\Common\User;

use App\Domain\Service\User\UserService;

class UserProfileAction extends UserAction
{
    protected function action(): \Slim\Http\Response
    {
        /** @var \App\Domain\Entities\User $user */
        $user = $this->request->getAttribute('user', false);

        if ($user && $this->request->isPost()) {
            $userService = UserService::getFromContainer($this->container);
            $userService->change(
                $user,
                [
                    'firstname' => $this->request->getParam('firstname'),
                    'lastname' => $this->request->getParam('lastname'),
                    'email' => $this->request->getParam('email'),
                    'phone' => $this->request->getParam('phone'),
                    'password' => $this->request->getParam('password'),
                ]
            );

            return $this->response->withRedirect('/user/profile');
        }

        return $this->respondWithTemplate($this->getParameter('user_profile_template', 'user.profile.twig'));
    }
}
