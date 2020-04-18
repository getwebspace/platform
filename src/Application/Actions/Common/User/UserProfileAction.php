<?php declare(strict_types=1);

namespace App\Application\Actions\Common\User;

class UserProfileAction extends UserAction
{
    protected function action(): \Slim\Http\Response
    {
        /** @var \App\Domain\Entities\User $user */
        $user = $this->request->getAttribute('user', false);

        if ($user && $this->request->isPost()) {
            // смена email
            if (
                ($email = $this->request->getParam('email')) !== null &&
                $this->users->findOneByUsername($email) === null
            ) {
                $user->setEmail($email);
            }

            $user
                ->setFirstname($this->request->getParam('firstname'))
                ->setLastname($this->request->getParam('lastname'))
                ->setPhone($this->request->getParam('phone'))
                ->setPassword($this->request->getParam('password'))
                ->setChange('now');

            $this->entityManager->flush();

            return $this->response->withRedirect('/user/profile');
        }

        return $this->respondWithTemplate($this->getParameter('user_profile_template', 'user.profile.twig'));
    }
}
