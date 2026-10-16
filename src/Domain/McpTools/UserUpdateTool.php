<?php declare(strict_types=1);

namespace App\Domain\McpTools;

use App\Application\PubSub;
use App\Domain\AbstractMcpTool;
use App\Domain\Casts\User\Status as UserStatus;
use App\Domain\McpTools\Concerns\DescribesUsers;
use App\Domain\Models\UserGroup;
use App\Domain\Service\User\UserService;

class UserUpdateTool extends AbstractMcpTool
{
    use DescribesUsers;

    public const NAME = 'user_update';
    public const TITLE = 'Update user';
    public const DESCRIPTION = 'Changes profile fields of a user (uuid from user_list). Pass only the fields to change. status can be work or block (blocked users cannot sign in; nothing is deleted). '
        . 'Group can be changed only between ordinary groups: staff accounts (groups with admin panel access) can not be edited or assigned here. '
        . 'Passwords, user name and tokens can not be changed. Confirm the change with the operator first.';
    public const READ_ONLY = false;
    public const PRIVATE = true;
    public const SCOPE = 'user';

    private const STRINGS = ['firstname', 'lastname', 'patronymic', 'email', 'phone', 'gender', 'country', 'city', 'address', 'postcode', 'website', 'language'];

    /**
     * Statuses an operator can set; confirmation belongs to the e-mail flow, delete is not offered
     */
    private const STATUSES = [UserStatus::WORK, UserStatus::BLOCK];

    public function getInputSchema(): array
    {
        $string = ['type' => 'string'];

        return [
            'type' => 'object',
            'properties' => ['uuid' => $string]
                + array_fill_keys(self::STRINGS, $string)
                + [
                    'birthdate' => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
                    'about' => ['type' => 'string', 'description' => 'Free text about the user'],
                    'allow_mail' => ['type' => 'boolean', 'description' => 'Agrees to receive mail'],
                    'company' => ['type' => 'object', 'properties' => ['title' => $string, 'position' => $string]],
                    'legal' => ['type' => 'object', 'properties' => ['code' => $string, 'number' => $string]],
                    'messenger' => ['type' => 'object', 'properties' => array_fill_keys(['telegram', 'whatsapp', 'viber', 'facebook', 'instagram', 'signal'], $string)],
                    'status' => ['type' => 'string', 'enum' => self::STATUSES],
                    'group' => $string + ['description' => 'Group title (not a staff group), see user_group_list'],
                ],
            'required' => ['uuid'],
        ];
    }

    public function execute(array $args = []): mixed
    {
        if (!is_string($args['uuid'] ?? null) || !\Ramsey\Uuid\Uuid::isValid($args['uuid'])) {
            throw new \InvalidArgumentException('Field uuid is required');
        }

        /** @var UserService $userService */
        $userService = $this->container->get(UserService::class);
        $user = $userService->read(['uuid' => $args['uuid'], 'with' => ['group'], 'status' => UserStatus::LIST]);

        if ($this->isStaff($user)) {
            throw new \DomainException('This is a staff account (admin panel access), it can not be changed here');
        }

        $data = array_intersect_key($args, array_flip(self::STRINGS));
        $data = array_map(fn ($value) => trim((string) $value), $data);

        if (array_key_exists('about', $args)) {
            $data['additional'] = trim((string) $args['about']);
        }
        if (array_key_exists('allow_mail', $args)) {
            $data['is_allow_mail'] = (bool) $args['allow_mail'];
        }
        if (array_key_exists('birthdate', $args)) {
            $value = trim((string) $args['birthdate']);

            if ($value !== '' && (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1 || strtotime($value) === false)) {
                throw new \InvalidArgumentException('Field birthdate must be YYYY-MM-DD');
            }

            $data['birthdate'] = $value === '' ? null : $value;
        }
        // json fields are replaced as a whole: merge with the current value
        foreach (['company', 'legal', 'messenger'] as $key) {
            if (is_array($args[$key] ?? null)) {
                $data[$key] = array_merge((array) $user->{$key}, array_map(fn ($value) => trim((string) $value), $args[$key]));
            }
        }
        if (array_key_exists('status', $args)) {
            if (!in_array($args['status'], self::STATUSES, true)) {
                throw new \InvalidArgumentException('Field status must be one of: ' . implode(', ', self::STATUSES));
            }
            if ($user->status === UserStatus::CONFIRMATION || $user->status === UserStatus::DELETE) {
                throw new \DomainException("User status is {$user->status}, it is not changed here");
            }

            $data['status'] = $args['status'];
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

        if (!$data) {
            throw new \InvalidArgumentException('Nothing to change');
        }

        $user = $userService->update($user, $data);

        $this->container->get(PubSub::class)->publish('api:user:edit', $user);
        $this->logger->notice('User updated via MCP', ['uuid' => (string) $user->uuid, 'fields' => array_keys($data)]);

        return $this->userFull($user->load('group')) + ['changed_fields' => array_keys($data)];
    }
}
