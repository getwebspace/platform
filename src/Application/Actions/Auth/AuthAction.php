<?php declare(strict_types=1);

namespace App\Application\Actions\Auth;

use App\Application\Auth;
use App\Domain\AbstractAction;
use Psr\Container\ContainerInterface;

abstract class AuthAction extends AbstractAction
{
    protected Auth $auth;

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);

        $this->auth = $container->get(Auth::class);
    }

    protected function isRequestJson(): bool
    {
        $headerAccept = $this->request->getHeaderLine('accept');

        return str_contains($headerAccept, 'application/json') || $headerAccept === '*/*';
    }
}
