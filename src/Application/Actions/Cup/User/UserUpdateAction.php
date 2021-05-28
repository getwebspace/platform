<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\User;

use App\Domain\Service\User\Exception\WrongEmailValueException;
use App\Domain\Service\User\Exception\WrongPhoneValueException;
use App\Domain\Service\User\Exception\EmailAlreadyExistsException;
use App\Domain\Service\User\Exception\PhoneAlreadyExistsException;
use App\Domain\Service\User\Exception\UsernameAlreadyExistsException;

class UserUpdateAction extends UserAction
{
    protected function action(): \Slim\Http\Response
    {
        if ($this->resolveArg('uuid')) {
            $user = $this->userService->read(['uuid' => $this->resolveArg('uuid')]);

            if ($user) {
                $userGroups = $this->userGroupService->read();

                if ($this->request->isPost()) {
                    try {
                        $group_uuid = $this->request->getParam('group_uuid');
                        $this->userService->update($user, [
                            'username' => $this->request->getParam('username'),
                            'firstname' => $this->request->getParam('firstname'),
                            'lastname' => $this->request->getParam('lastname'),
                            'address' => $this->request->getParam('address'),
                            'additional' => $this->request->getParam('additional'),
                            'email' => $this->request->getParam('email'),
                            'allow_mail' => $this->request->getParam('allow_mail'),
                            'phone' => $this->request->getParam('phone'),
                            'password' => $this->request->getParam('password'),
                            'group' => $group_uuid !== \Ramsey\Uuid\Uuid::NIL ? $userGroups->firstWhere('uuid', $group_uuid) : '',
                            'status' => $this->request->getParam('status'),
                        ]);
                        $user = $this->processEntityFiles($user);

                        switch (true) {
                            case $this->request->getParam('save', 'exit') === 'exit':
                                return $this->response->withRedirect('/cup/user');

                            default:
                                return $this->response->withRedirect('/cup/user/' . $user->getUuid() . '/edit');
                        }
                    } catch (UsernameAlreadyExistsException $e) {
                        $this->addError('username', $e->getMessage());
                    } catch (WrongEmailValueException | EmailAlreadyExistsException $e) {
                        $this->addError('email', $e->getMessage());
                    } catch (WrongPhoneValueException | PhoneAlreadyExistsException $e) {
                        $this->addError('phone', $e->getMessage());
                    }
                }

                return $this->respondWithTemplate('cup/user/form.twig', ['item' => $user, 'groups' => $userGroups]);
            }
        }

        return $this->response->withRedirect('/cup/user');
    }
}
