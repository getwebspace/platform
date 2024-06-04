<?php declare(strict_types=1);

namespace App\Domain\Plugin;

use App\Domain\AbstractPlugin;

abstract class AbstractOAuthPlugin extends AbstractPlugin
{
    abstract public function getAuthUrl(string $redirect, ?string $state = null): ?string;

    abstract public function getToken(array $data, string $redirect): mixed;

    abstract public function getInfo(array $data): ?array;

    abstract public function getButton(): ?string;
}
