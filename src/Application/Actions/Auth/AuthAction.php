<?php declare(strict_types=1);

namespace App\Application\Actions\Auth;

use App\Domain\AbstractAction;
use App\Domain\Service\User\TokenService as UserTokenService;
use Psr\Container\ContainerInterface;

abstract class AuthAction extends AbstractAction
{
    protected UserTokenService $userTokenService;

    /**
     * {@inheritdoc}
     */
    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);

        $this->userTokenService = $container->get(UserTokenService::class);
    }
}
