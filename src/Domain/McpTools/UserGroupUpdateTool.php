<?php declare(strict_types=1);

namespace App\Domain\McpTools;

use App\Application\PubSub;
use App\Domain\Service\User\GroupService;

class UserGroupUpdateTool extends UserGroupCreateTool
{
    public const NAME = 'user_group_update';
    public const TITLE = 'Update user group';
    public const DESCRIPTION = 'Changes the title or description of an ordinary user group (uuid from user_group_list). Staff groups (admin panel access) can not be changed here, and access rules are never touched.';

    public function getInputSchema(): array
    {
        $schema = parent::getInputSchema();
        $schema['properties'] = ['uuid' => ['type' => 'string']] + $schema['properties'];
        $schema['required'] = ['uuid'];

        return $schema;
    }

    public function execute(array $args = []): mixed
    {
        if (!is_string($args['uuid'] ?? null) || !\Ramsey\Uuid\Uuid::isValid($args['uuid'])) {
            throw new \InvalidArgumentException('Field uuid is required');
        }

        /** @var GroupService $service */
        $service = $this->container->get(GroupService::class);
        $group = $service->read(['uuid' => $args['uuid']]);

        if ($this->isStaffGroup($group)) {
            throw new \DomainException('This is a staff group (admin panel access), it can not be changed here');
        }

        $data = [];

        if (array_key_exists('title', $args)) {
            if (blank($title = $this->textArgument($args, 'title'))) {
                throw new \InvalidArgumentException('Field title cannot be empty');
            }
            $data['title'] = $title;
        }
        if (array_key_exists('description', $args)) {
            $data['description'] = trim((string) $args['description']);
        }
        if (!$data) {
            throw new \InvalidArgumentException('Nothing to change');
        }

        $group = $service->update($group, $data);

        $this->container->get(PubSub::class)->publish('api:user:group:edit', $group);
        $this->logger->notice('User group updated via MCP', ['uuid' => (string) $group->uuid, 'fields' => array_keys($data)]);

        return ['uuid' => (string) $group->uuid, 'title' => $group->title, 'description' => $group->description, 'changed_fields' => array_keys($data)];
    }
}
