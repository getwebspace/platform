<?php declare(strict_types=1);

namespace App\Domain\McpTools;

use App\Domain\AbstractMcpTool;
use App\Domain\Casts\User\Status as UserStatus;
use App\Domain\McpTools\Concerns\DescribesUsers;
use App\Domain\Models\User;
use App\Domain\Models\UserGroup;
use App\Domain\Service\User\UserService;

class UserListTool extends AbstractMcpTool
{
    use DescribesUsers;

    public const NAME = 'user_list';
    public const TITLE = 'Users';
    public const DESCRIPTION = 'Lists site users (customers, participants, staff), newest first. Filter by status (default: active), group title and search by name, e-mail, phone or username. '
        . 'Short cards with contacts: use user_get for the whole profile. Groups: user_group_list.';
    public const PRIVATE = true;
    public const SCOPE = 'user';

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'status' => ['type' => 'string', 'enum' => [...UserStatus::LIST, 'all'], 'default' => UserStatus::WORK, 'description' => 'work - active, block - blocked, confirmation - waiting for e-mail confirmation, delete - deleted'],
                'group' => ['type' => 'string', 'description' => 'Group title or uuid'],
                'without_group' => ['type' => 'boolean', 'description' => 'Only users without a group'],
                'search' => ['type' => 'string', 'description' => 'Part of name, e-mail, phone or username'],
            ] + $this->paginationSchema(),
        ];
    }

    public function execute(array $args = []): mixed
    {
        $status = $args['status'] ?? UserStatus::WORK;

        if ($status !== 'all' && !in_array($status, UserStatus::LIST, true)) {
            throw new \InvalidArgumentException('Field status must be one of: ' . implode(', ', [...UserStatus::LIST, 'all']));
        }

        $filter = $status === 'all' ? [] : ['status' => $status];

        if (!empty($args['without_group'])) {
            $filter['group_uuid'] = [null];
        } elseif (!blank($args['group'] ?? null)) {
            $filter['group_uuid'] = [$this->findGroup((string) $args['group'])];
        }
        if (!blank($search = trim((string) ($args['search'] ?? '')))) {
            $filter['search'] = $search;
        }

        /** @var UserService $userService */
        $userService = $this->container->get(UserService::class);

        return $this->paginate(
            $args,
            fn (int $limit, int $offset) => $userService->read($filter + [
                'with' => ['group'],
                'order' => ['register' => 'desc'],
                'limit' => $limit,
                'offset' => $offset,
            ]),
            fn (User $user) => $this->userCard($user)
        );
    }

    private function findGroup(string $group): string
    {
        $groups = UserGroup::query()->pluck('title', 'uuid');
        $found = $groups->search(fn ($title, $uuid) => (string) $uuid === $group || mb_strtolower((string) $title) === mb_strtolower($group));

        if ($found === false) {
            throw new \InvalidArgumentException('Unknown group, allowed: ' . $groups->values()->implode(', '));
        }

        return (string) $found;
    }
}
