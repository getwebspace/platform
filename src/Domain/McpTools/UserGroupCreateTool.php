<?php declare(strict_types=1);

namespace App\Domain\McpTools;

use App\Application\PubSub;
use App\Domain\AbstractMcpTool;
use App\Domain\McpTools\Concerns\DescribesUsers;
use App\Domain\Service\User\GroupService;

class UserGroupCreateTool extends AbstractMcpTool
{
    use DescribesUsers;

    public const NAME = 'user_group_create';
    public const TITLE = 'Create user group';
    public const DESCRIPTION = 'Creates an ordinary user group (title, description). Groups with admin panel access (staff) can not be created or changed here: the access rules are set in the admin panel only.';
    public const READ_ONLY = false;
    public const PRIVATE = true;
    public const SCOPE = 'user/group';

    public function getInputSchema(): array
    {
        $string = ['type' => 'string'];

        return [
            'type' => 'object',
            'properties' => ['title' => $string, 'description' => $string],
            'required' => ['title'],
        ];
    }

    public function execute(array $args = []): mixed
    {
        if (blank($title = $this->textArgument($args, 'title'))) {
            throw new \InvalidArgumentException('Field title is required');
        }

        // "access" is never taken from the caller: it opens the admin panel
        $group = $this->container->get(GroupService::class)->create([
            'title' => $title,
            'description' => trim((string) ($args['description'] ?? '')),
        ]);

        $this->container->get(PubSub::class)->publish('api:user:group:create', $group);
        $this->logger->notice('User group created via MCP', ['uuid' => (string) $group->uuid]);

        return ['uuid' => (string) $group->uuid, 'title' => $group->title, 'description' => $group->description, 'staff' => $this->isStaffGroup($group)];
    }
}
