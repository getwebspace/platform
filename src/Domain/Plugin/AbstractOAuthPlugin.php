<?php declare(strict_types=1);

namespace App\Domain\Plugin;

use App\Domain\AbstractPlugin;
use App\Domain\Models\User;

abstract class AbstractOAuthPlugin extends AbstractPlugin
{
    abstract public function getAuthUrl(): ?string;

    abstract public function getToken(array $data = []): mixed;

    abstract public function getInfo(array $data = []): ?array;

    abstract public function getButton(): ?string;
}
