<?php declare(strict_types=1);

namespace App\Domain\McpTools;

use App\Domain\AbstractMcpTool;
use App\Domain\Casts\User\Status as UserStatus;
use App\Domain\McpTools\Concerns\DescribesUsers;
use App\Domain\Models\UserGroup;
use App\Domain\Service\User\UserService;

class UserCreateTool extends AbstractMcpTool
{
    use DescribesUsers;

    public const NAME = 'user_create';
    public const TITLE = 'Create user';
    public const DESCRIPTION = 'Creates a customer account (username, e-mail or phone is required and must be unique). The account has NO usable password and nobody is notified - the person sets a password with the "forgot password" flow. '
        . 'The account is placed only in an ordinary group (never a staff group with admin panel access). Does not subscribe the person to mailings unless allow_mail=true. Confirm the data with the operator first.';
    public const READ_ONLY = false;
    public const PRIVATE = true;
    public const SCOPE = 'user';

    private const STRINGS = ['username', 'email', 'phone', 'firstname', 'lastname', 'patronymic', 'gender', 'country', 'city', 'address', 'postcode', 'website', 'language'];

    public function getInputSchema(): array
    {
        $string = ['type' => 'string'];

        return [
            'type' => 'object',
            'properties' => array_fill_keys(self::STRINGS, $string) + [
                'group' => $string + ['description' => 'Group title (not a staff group), see user_group_list'],
                'about' => $string,
                'allow_mail' => ['type' => 'boolean', 'default' => false],
            ],
        ];
    }

    public function execute(array $args = []): mixed
    {
        $data = array_map(fn ($value) => trim((string) $value), array_intersect_key($args, array_flip(self::STRINGS)));

        if (!array_filter([$data['username'] ?? '', $data['email'] ?? '', $data['phone'] ?? ''])) {
            throw new \InvalidArgumentException('Pass username, email or phone');
        }
        if (!blank($args['group'] ?? null)) {
            $group = UserGroup::query()->get()->first(fn (UserGroup $item) => mb_strtolower($item->title) === mb_strtolower(trim((string) $args['group'])) || (string) $item->uuid === $args['group']);

            if ($group === null) {
                throw new \InvalidArgumentException('Unknown group, allowed: ' . UserGroup::query()->pluck('title')->implode(', '));
            }
            if ($this->isStaffGroup($group)) {
                throw new \DomainException('Staff groups (admin panel access) can not be assigned here');
            }

            $data['group_uuid'] = (string) $group->uuid;
        }

        $user = $this->container->get(UserService::class)->create($data + [
            'additional' => trim((string) ($args['about'] ?? '')),
            'is_allow_mail' => (bool) ($args['allow_mail'] ?? false),
            'status' => UserStatus::WORK,
            // a random value nobody knows: an empty one would leave the field blank, and a blank hash must never be a way in
            'password' => bin2hex(random_bytes(24)),
        ]);

        // the create event sends the welcome and the admin mail, not published here
        $this->logger->notice('User created via MCP', ['uuid' => (string) $user->uuid]);

        return $this->userFull($user->load('group'));
    }
}
