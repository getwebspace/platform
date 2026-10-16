<?php declare(strict_types=1);

namespace App\Domain\McpTools;

use App\Domain\AbstractMcpTool;
use App\Domain\McpTools\Concerns\DescribesUsers;
use App\Domain\Models\UserGroup;

class UserGroupListTool extends AbstractMcpTool
{
    use DescribesUsers;

    public const NAME = 'user_group_list';
    public const TITLE = 'User groups';
    public const DESCRIPTION = 'Lists user groups with the number of members. "staff" means the group gives access to the admin panel: such users can not be edited or assigned by tools. '
        . 'Use the group title in user_list and user_update.';
    public const PRIVATE = true;
    public const SCOPE = 'user/group';

    public function execute(array $args = []): mixed
    {
        // few groups: counting members one by one is simpler than a join and typed
        return UserGroup::all()
            ->sortBy('title')
            ->map(fn (UserGroup $group) => [
                'uuid' => (string) $group->uuid,
                'title' => $group->title,
                'description' => $group->description ?: null,
                'members' => $group->users()->count(),
                'staff' => $this->isStaffGroup($group),
            ])
            ->values()
            ->all();
    }
}
