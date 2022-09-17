<?php declare(strict_types=1);

namespace App\Application\Actions\Common\User;

use App\Domain\Service\User\Exception\EmailAlreadyExistsException;
use App\Domain\Service\User\Exception\PhoneAlreadyExistsException;
use App\Domain\Service\User\Exception\WrongEmailValueException;
use App\Domain\Service\User\Exception\WrongPhoneValueException;

class UserProfileAction extends UserAction
{
    protected function action(): \Slim\Psr7\Response
    {
        /** @var \App\Domain\Entities\User $user */
        $user = $this->request->getAttribute('user', false);

        if ($user && $this->isPost()) {
            try {
                $this->userService->update(
                    $user,
                    [
                        'firstname' => $this->getParam('firstname'),
                        'lastname' => $this->getParam('lastname'),
                        'address' => $this->getParam('address'),
                        'additional' => $this->getParam('additional'),
                        'email' => $this->getParam('email'),
                        'allow_mail' => $this->getParam('allow_mail'),
                        'phone' => $this->getParam('phone'),
                        'password' => $this->getParam('password'),
                        'language' => $this->getParam('language'),
                    ]
                );

                return $this->respondWithRedirect('/user/profile');
            } catch (WrongEmailValueException|EmailAlreadyExistsException $e) {
                $this->addError('email', $e->getMessage());
            } catch (WrongPhoneValueException|PhoneAlreadyExistsException $e) {
                $this->addError('phone', $e->getMessage());
            }
        }

        return $this->respond($this->parameter('user_profile_template', 'user.profile.twig'), [
            'user' => $user,
            'oauth' => $this->getOAuthProviders(true),
        ]);
    }
}
