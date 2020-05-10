<?php declare(strict_types=1);

namespace App\Application\Actions\Common\User;

use App\Domain\Exceptions\WrongEmailValueException;
use App\Domain\Exceptions\WrongPhoneValueException;
use App\Domain\Service\User\Exception\EmailAlreadyExistsException;
use App\Domain\Service\User\UserService;

class UserProfileAction extends UserAction
{
    protected function action(): \Slim\Http\Response
    {
        /** @var \App\Domain\Entities\User $user */
        $user = $this->request->getAttribute('user', false);

        if ($user && $this->request->isPost()) {
            try {
                $userService = UserService::getFromContainer($this->container);
                $userService->update(
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
            } catch (WrongEmailValueException|EmailAlreadyExistsException $e) {
                $this->addError('email', $e->getMessage());
            } catch (WrongPhoneValueException $e) {
                $this->addError('phone', $e->getMessage());
            }
        }

        return $this->respondWithTemplate($this->getParameter('user_profile_template', 'user.profile.twig'));
    }
}
