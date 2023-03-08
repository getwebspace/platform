<?php declare(strict_types=1);

namespace App\Application\Actions\Auth;

use App\Domain\AbstractAction;
use App\Domain\Service\User\TokenService as UserTokenService;
use App\Domain\Service\User\UserService;
use Psr\Container\ContainerInterface;

abstract class AuthAction extends AbstractAction
{
    protected UserService $userService;

    protected UserTokenService $userTokenService;

    /**
     * {@inheritdoc}
     */
    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);

        $this->userService = $container->get(UserService::class);
        $this->userTokenService = $container->get(UserTokenService::class);
    }

    protected function isRequestJson(): bool
    {
        $headerAccept = $this->request->getHeaderLine('accept');

        return str_contains($headerAccept, 'application/json') || $headerAccept === '*/*';
    }
}
