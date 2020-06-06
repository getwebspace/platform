<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\User;

use App\Domain\Service\User\UserService;

class UserDeleteAction extends UserAction
{
    protected function action(): \Slim\Http\Response
    {
        if ($this->resolveArg('uuid') && \Ramsey\Uuid\Uuid::isValid($this->resolveArg('uuid'))) {
            $userService = UserService::getWithContainer($this->container);
            $userService->delete($this->resolveArg('uuid'));
        }

        return $this->response->withAddedHeader('Location', '/cup/user')->withStatus(301);
    }
}
