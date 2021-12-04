<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\User;

use App\Domain\Service\User\Exception\EmailAlreadyExistsException;
use App\Domain\Service\User\Exception\EmailBannedException;
use App\Domain\Service\User\Exception\PhoneAlreadyExistsException;
use App\Domain\Service\User\Exception\UsernameAlreadyExistsException;
use App\Domain\Service\User\Exception\WrongEmailValueException;
use App\Domain\Service\User\Exception\WrongPhoneValueException;

class UserCreateAction extends UserAction
{
    protected function action(): \Slim\Psr7\Response
    {
        $userGroups = $this->userGroupService->read();

        if ($this->isPost()) {
            try {
                $group_uuid = $this->getParam('group_uuid');
                $user = $this->userService->create([
                    'username' => $this->getParam('username'),
                    'password' => $this->getParam('password'),
                    'firstname' => $this->getParam('firstname'),
                    'lastname' => $this->getParam('lastname'),
                    'address' => $this->getParam('address'),
                    'additional' => $this->getParam('additional'),
                    'email' => $this->getParam('email'),
                    'allow_mail' => $this->getParam('allow_mail'),
                    'phone' => $this->getParam('phone'),
                    'group' => $group_uuid !== \Ramsey\Uuid\Uuid::NIL ? $userGroups->firstWhere('uuid', $group_uuid) : '',
                    'external_id' => $this->getParam('external_id'),
                ]);
                $user = $this->processEntityFiles($user);

                switch (true) {
                    case $this->getParam('save', 'exit') === 'exit':
                        return $this->respondWithRedirect('/cup/user');

                    default:
                        return $this->respondWithRedirect('/cup/user/' . $user->getUuid() . '/edit');
                }
            } catch (UsernameAlreadyExistsException $e) {
                $this->addError('username', $e->getMessage());
            } catch (WrongEmailValueException|EmailAlreadyExistsException|EmailBannedException $e) {
                $this->addError('email', $e->getMessage());
            } catch (WrongPhoneValueException|PhoneAlreadyExistsException $e) {
                $this->addError('phone', $e->getMessage());
            }
        }

        return $this->respondWithTemplate('cup/user/form.twig', ['groups' => $userGroups]);
    }
}
