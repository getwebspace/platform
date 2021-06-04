<?php declare(strict_types=1);

namespace App\Application\Actions\Common\User;

use App\Domain\Service\User\Exception\EmailAlreadyExistsException;
use App\Domain\Service\User\Exception\PhoneAlreadyExistsException;
use App\Domain\Service\User\Exception\WrongEmailValueException;
use App\Domain\Service\User\Exception\WrongPhoneValueException;

class UserProfileAction extends UserAction
{
    protected function action(): \Slim\Http\Response
    {
        /** @var \App\Domain\Entities\User $user */
        $user = $this->request->getAttribute('user', false);

        if ($user && $this->request->isPost()) {
            try {
                $this->userService->update(
                    $user,
                    [
                        'firstname' => $this->request->getParam('firstname'),
                        'lastname' => $this->request->getParam('lastname'),
                        'address' => $this->request->getParam('address'),
                        'additional' => $this->request->getParam('additional'),
                        'email' => $this->request->getParam('email'),
                        'allow_mail' => $this->request->getParam('allow_mail'),
                        'phone' => $this->request->getParam('phone'),
                        'password' => $this->request->getParam('password'),
                    ]
                );

                return $this->response->withRedirect('/user/profile');
            } catch (WrongEmailValueException | EmailAlreadyExistsException $e) {
                $this->addError('email', $e->getMessage());
            } catch (WrongPhoneValueException | PhoneAlreadyExistsException $e) {
                $this->addError('phone', $e->getMessage());
            }
        }

        return $this->respond($this->parameter('user_profile_template', 'user.profile.twig'), [
            'user' => $user,
        ]);
    }
}
