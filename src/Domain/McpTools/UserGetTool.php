<?php declare(strict_types=1);

namespace App\Domain\McpTools;

use App\Domain\AbstractMcpTool;
use App\Domain\McpTools\Concerns\DescribesUsers;
use App\Domain\Service\User\UserService;

class UserGetTool extends AbstractMcpTool
{
    use DescribesUsers;

    public const NAME = 'user_get';
    public const TITLE = 'Get user';
    public const DESCRIPTION = 'Returns the profile of one user: contacts, address, company, messengers, group, status and the number of orders. By uuid, e-mail, phone or username. '
        . 'Passwords, tokens and integrations are never returned.';
    public const PRIVATE = true;
    public const SCOPE = 'user';

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'uuid' => ['type' => 'string'],
                'email' => ['type' => 'string'],
                'phone' => ['type' => 'string'],
                'username' => ['type' => 'string'],
            ],
            'description' => 'Pass one of the fields',
        ];
    }

    public function execute(array $args = []): mixed
    {
        $criteria = array_filter(array_intersect_key($args, array_flip(['uuid', 'email', 'phone', 'username'])), fn ($value) => is_string($value) && !blank($value));

        if (!$criteria) {
            throw new \InvalidArgumentException('Pass one of: uuid, email, phone, username');
        }

        return $this->userFull($this->container->get(UserService::class)->read($criteria + ['with' => ['group'], 'status' => \App\Domain\Casts\User\Status::LIST]));
    }
}
