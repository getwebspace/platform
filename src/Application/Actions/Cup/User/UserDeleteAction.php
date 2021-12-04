<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\User;

class UserDeleteAction extends UserAction
{
    protected function action(): \Slim\Psr7\Response
    {
        if ($this->resolveArg('uuid') && \Ramsey\Uuid\Uuid::isValid($this->resolveArg('uuid'))) {
            $this->userService->delete($this->resolveArg('uuid'));
        }

        return $this->response->withAddedHeader('Location', '/cup/user')->withStatus(301);
    }
}
