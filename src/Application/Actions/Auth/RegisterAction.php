<?php declare(strict_types=1);

namespace App\Application\Actions\Auth;

use App\Domain\Exceptions\HttpRedirectException;
use App\Domain\Service\User\Exception\EmailAlreadyExistsException;
use App\Domain\Service\User\Exception\EmailBannedException;
use App\Domain\Service\User\Exception\MissingUniqueValueException;
use App\Domain\Service\User\Exception\PhoneAlreadyExistsException;
use App\Domain\Service\User\Exception\UsernameAlreadyExistsException;
use App\Domain\Service\User\Exception\UserNotFoundException;
use App\Domain\Service\User\Exception\WrongEmailValueException;
use App\Domain\Service\User\Exception\WrongPasswordException;
use App\Domain\Service\User\Exception\WrongPhoneValueException;
use App\Domain\Service\User\Exception\WrongUsernameValueException;

class RegisterAction extends AuthAction
{
    protected function action(): \Slim\Psr7\Response
    {
        $redirect = $this->getParam('redirect', '/');

        try {
            $result = $this->auth->register(
                $this->getParam('provider', $_SESSION['auth_provider'] ?? 'BasicAuthProvider'),
                [
                    'firstname' => $this->getParam('firstname', ''),
                    'lastname' => $this->getParam('lastname', ''),
                    'username' => $this->getParam('username', ''),
                    'email' => $this->getParam('email', ''),
                    'phone' => $this->getParam('phone', ''),
                    'address' => $this->getParam('address', ''),
                    'additional' => $this->getParam('additional', ''),
                    'is_allow_mail' => $this->getParam('is_allow_mail', true),
                    'password' => $this->getParam('password'),
                    'password_again' => $this->getParam('password_again'),
                    'external_id' => $this->getParam('external_id', ''),
                ]
            );

            switch ($this->isRequestJson()) {
                case true:
                    return $this->respondWithJson([
                        'user' => $result['user'],
                    ]);

                case false:
                default:
                    return $this->response->withAddedHeader('Location', $redirect)->withStatus(307);
            }
        } catch (
            MissingUniqueValueException|
            UsernameAlreadyExistsException|WrongUsernameValueException|EmailAlreadyExistsException|PhoneAlreadyExistsException|
            EmailBannedException|
            WrongEmailValueException|WrongPhoneValueException $e
        ) {
            return $this->respondWithJson(['error' => $e->getMessage()])->withStatus(400);
        }
    }
}
