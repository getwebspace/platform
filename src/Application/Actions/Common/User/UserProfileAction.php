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
        /** @var \App\Domain\Models\User $user */
        $user = $this->request->getAttribute('user', false);

        if ($user && $this->isPost()) {
            try {
                $password = $this->getParam('password');
                $this->userService->update(
                    $user,
                    [
                        'username' => $this->getParam('username'),
                        'email' => $this->getParam('email'),
                        'phone' => $this->getParam('phone'),
                        'password' => !blank($password) ? $password : null,

                        'firstname' => $this->getParam('firstname'),
                        'lastname' => $this->getParam('lastname'),
                        'patronymic' => $this->getParam('patronymic'),
                        'gender' => $this->getParam('gender'),
                        'birthdate' => $this->getParam('birthdate'),

                        'country' => $this->getParam('country'),
                        'city' => $this->getParam('city'),
                        'address' => $this->getParam('address'),
                        'postcode' => $this->getParam('postcode'),

                        'company' => $this->getParam('company'),
                        'legal' => $this->getParam('legal'),

                        'website' => $this->getParam('website'),
                        'additional' => $this->getParam('additional'),

                        'is_allow_mail' => $this->getParam('is_allow_mail'),
                        'language' => $this->getParam('language'),
                    ]
                );

                $this->container->get(\App\Application\PubSub::class)->publish('common:user:edit', $user);

                return $this->respondWithRedirect('/user/profile');
            } catch (EmailAlreadyExistsException|WrongEmailValueException $e) {
                $this->addError('email', $e->getMessage());
            } catch (PhoneAlreadyExistsException|WrongPhoneValueException $e) {
                $this->addError('phone', $e->getMessage());
            }
        }

        return $this->respond($this->parameter('user_profile_template', 'user.profile.twig'), [
            'user' => $user,
        ]);
    }
}
