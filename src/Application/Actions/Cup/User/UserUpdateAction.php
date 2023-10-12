<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\User;

use App\Domain\Exceptions\HttpBadRequestException;
use App\Domain\Service\User\Exception\EmailAlreadyExistsException;
use App\Domain\Service\User\Exception\PhoneAlreadyExistsException;
use App\Domain\Service\User\Exception\UsernameAlreadyExistsException;
use App\Domain\Service\User\Exception\UserNotFoundException;
use App\Domain\Service\User\Exception\WrongEmailValueException;
use App\Domain\Service\User\Exception\WrongPasswordException;
use App\Domain\Service\User\Exception\WrongPhoneValueException;
use App\Domain\Service\User\Exception\WrongUsernameValueException;

class UserUpdateAction extends UserAction
{
    protected function action(): \Slim\Psr7\Response
    {
        if ($this->resolveArg('uuid')) {
            try {
                $user = $this->userService->read([
                    'uuid' => $this->resolveArg('uuid')
                ]);

                if ($this->isPost()) {
                    try {
                        $user = $this->userService->update($user, [
                            'username' => $this->getParam('username'),
                            'firstname' => $this->getParam('firstname'),
                            'lastname' => $this->getParam('lastname'),
                            'patronymic' => $this->getParam('patronymic'),
                            'gender' => $this->getParam('gender'),
                            'birthdate' => $this->getParam('birthdate'),
                            'country' => $this->getParam('country'),
                            'city' => $this->getParam('city'),
                            'address' => $this->getParam('address'),
                            'postcode' => $this->getParam('postcode'),
                            'additional' => $this->getParam('additional'),
                            'email' => $this->getParam('email'),
                            'allow_mail' => $this->getParam('allow_mail'),
                            'phone' => $this->getParam('phone'),
                            'password' => $this->getParam('password'),
                            'company' => $this->getParam('company'),
                            'legal' => $this->getParam('legal'),
                            'website' => $this->getParam('website'),
                            'source' => $this->getParam('source'),
                            'group_uuid' => $this->getParam('group_uuid'),
                            'status' => $this->getParam('status'),
                            'language' => $this->getParam('language'),
                            'external_id' => $this->getParam('external_id'),
                        ]);
                        $user = $this->processEntityFiles($user);

                        $this->container->get(\App\Application\PubSub::class)->publish('cup:user:edit', $user);

                        switch (true) {
                            case $this->getParam('save', 'exit') === 'exit':
                                return $this->respondWithRedirect('/cup/user');

                            default:
                                return $this->respondWithRedirect('/cup/user/' . $user->getUuid() . '/edit');
                        }
                    } catch (WrongUsernameValueException|UsernameAlreadyExistsException $e) {
                        $this->addError('username', $e->getMessage());
                    } catch (WrongEmailValueException|EmailAlreadyExistsException $e) {
                        $this->addError('email', $e->getMessage());
                    } catch (WrongPhoneValueException|PhoneAlreadyExistsException $e) {
                        $this->addError('phone', $e->getMessage());
                    }
                }

                $userGroups = $this->userGroupService->read();

                return $this->respondWithTemplate('cup/user/form.twig', [
                    'item' => $user,
                    'groups' => $userGroups
                ]);
            } catch (UserNotFoundException $e) {
                // nothing
            }
        }

        return $this->respondWithRedirect('/cup/user');
    }
}
