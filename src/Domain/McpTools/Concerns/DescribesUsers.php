<?php declare(strict_types=1);

namespace App\Domain\McpTools\Concerns;

use App\Domain\Models\User;
use App\Domain\Models\UserGroup;

/**
 * Users as the tools show them: never the password, tokens or integrations
 */
trait DescribesUsers
{
    /**
     * A group with access rules gives entry to the admin panel: its members are staff
     */
    protected function isStaffGroup(?UserGroup $group): bool
    {
        return $group !== null && (array) $group->access !== [];
    }

    protected function isStaff(User $user): bool
    {
        return $this->isStaffGroup($user->getRelationValue('group'));
    }

    protected function userCard(User $user): array
    {
        return [
            'uuid' => (string) $user->uuid,
            'name' => $user->name('full') ?: null,
            'username' => $user->username ?: null,
            'email' => $user->email ?: null,
            'phone' => $user->phone ?: null,
            'group' => $user->getRelationValue('group')?->title,
            'staff' => $this->isStaff($user),
            'status' => $user->status,
            'registered' => datetime($user->register)->format('Y-m-d H:i'),
        ];
    }

    protected function userFull(User $user): array
    {
        return $this->userCard($user) + [
            'firstname' => $user->firstname,
            'lastname' => $user->lastname,
            'patronymic' => $user->patronymic,
            'birthdate' => blank($user->getAttributes()['birthdate'] ?? null) ? null : datetime($user->birthdate)->format('Y-m-d'),
            'gender' => $user->gender,
            'country' => $user->country,
            'city' => $user->city,
            'address' => $user->address,
            'postcode' => $user->postcode,
            'website' => $user->website,
            'company' => $user->company,
            'legal' => $user->legal,
            'messenger' => array_filter((array) $user->messenger),
            'about' => $user->additional,
            'language' => $user->language,
            'allow_mail' => (bool) $user->is_allow_mail,
            'source' => $user->source,
            'external_id' => $user->external_id,
            'orders' => $user->orders()->count(),
            'changed' => datetime($user->change)->format('Y-m-d H:i'),
        ];
    }
}
