<?php declare(strict_types=1);

namespace App\Domain\Service\User;

use App\Domain\AbstractService;
use App\Domain\Enums\UserStatus;
use App\Domain\Models\User;
use App\Domain\Models\UserGroup;
use App\Domain\Models\UserToken;
use App\Domain\Repository\UserRepository;
use App\Domain\Service\Page\Exception\PageNotFoundException;
use App\Domain\Service\User\Exception\EmailAlreadyExistsException;
use App\Domain\Service\User\Exception\EmailBannedException;
use App\Domain\Service\User\Exception\MissingUniqueValueException;
use App\Domain\Service\User\Exception\PhoneAlreadyExistsException;
use App\Domain\Service\User\Exception\UsernameAlreadyExistsException;
use App\Domain\Service\User\Exception\UserNotFoundException;
use App\Domain\Service\User\Exception\WrongEmailValueException;
use App\Domain\Service\User\Exception\WrongPasswordException;
use App\Domain\Service\User\Exception\WrongPhoneValueException;
use App\Domain\Service\User\GroupService as UserGroupService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Ramsey\Uuid\UuidInterface as Uuid;

class UserService extends AbstractService
{
    protected function init(): void
    {
    }

    /**
     * @throws EmailAlreadyExistsException
     * @throws EmailBannedException
     * @throws UsernameAlreadyExistsException
     * @throws PhoneAlreadyExistsException
     * @throws MissingUniqueValueException
     * @throws WrongEmailValueException
     * @throws WrongPhoneValueException
     */
    public function create(array $data = []): User
    {
        $default = [
            'username' => '',
            'email' => '',
            'phone' => '',
            'password' => '',
            'firstname' => '',
            'lastname' => '',
            'patronymic' => '',
            'gender' => '',
            'birthdate' => '',
            'country' => '',
            'city' => '',
            'address' => '',
            'postcode' => '',
            'additional' => '',
            'allow_mail' => true,
            'status' => \App\Domain\Types\UserStatusType::STATUS_WORK,
            'company' => [],
            'legal' => [],
            'messenger' => [],
            'website' => '',
            'source' => '',
            'group' => null,
            'group_uuid' => null,
            'auth_code' => '',
            'language' => '',
            'external_id' => '',
            'token' => [],
        ];
        $data = array_merge($default, $data);

        if ($data['username'] && User::firstWhere(['username' => $data['username']]) !== null) {
            throw new UsernameAlreadyExistsException();
        }
        if ($data['email']) {
            if (User::firstWhere(['email' => $data['email']]) !== null) {
                throw new EmailAlreadyExistsException();
            }
            if ($this->check_email($data['email'])) {
                throw new EmailBannedException();
            }
        }
        if ($data['phone'] && User::firstWhere(['phone' => $data['phone']]) !== null) {
            throw new PhoneAlreadyExistsException();
        }
        if (!$data['username'] && !$data['email'] && !$data['phone']) {
            throw new MissingUniqueValueException();
        }

        return User::create($data);
    }

    /**
     * @return Collection|User
     * @throws WrongPasswordException
     *
     * @throws UserNotFoundException
     */
    public function read(array $data = [])
    {
        $default = [
            'identifier' => null, // field for: username, email, phone
            'uuid' => null,
            'username' => null,
            'firstname' => null,
            'lastname' => null,
            'email' => null,
            'phone' => null,
            'country' => null,
            'city' => null,
            'postcode' => null,
            'additional' => null,
            'allow_mail' => null,
            'status' => null,
            'external_id' => null,
            'password' => null, // optional: for check
        ];
        $data = array_merge($default, static::$default_read, $data);

        $criteria = [];

        if ($data['uuid'] !== null) {
            $criteria['uuid'] = $data['uuid'];
        }
        if ($data['username'] !== null) {
            $criteria['username'] = $data['username'];
        }
        if ($data['email'] !== null) {
            $criteria['email'] = $data['email'];
        }
        if ($data['phone'] !== null) {
            $criteria['phone'] = $data['phone'];
        }
        if ($data['country'] !== null) {
            $criteria['country'] = $data['country'];
        }
        if ($data['city'] !== null) {
            $criteria['city'] = $data['city'];
        }
        if ($data['postcode'] !== null) {
            $criteria['postcode'] = $data['postcode'];
        }
        if ($data['additional'] !== null) {
            $criteria['additional'] = $data['additional'];
        }
        if ($data['allow_mail'] !== null) {
            $criteria['allow_mail'] = (bool)$data['allow_mail'];
        }
        if ($data['status'] !== null && in_array($data['status'], \App\Domain\Types\UserStatusType::LIST, true)) {
            $criteria['status'] = $data['status'];
        }
        if ($data['external_id'] !== null) {
            $criteria['external_id'] = $data['external_id'];
        }

        switch (true) {
            case $data['identifier'] !== null:
                /** @var User $user */
                $user = User::where('email', $data['identifier'])
                    ->orWhere('phone', $data['identifier'])
                    ->orWhere('username', $data['identifier'])
                    ->first();

                return $user ?: throw new UserNotFoundException();

            case !is_array($data['uuid']) && $data['uuid'] !== null:
            case !is_array($data['username']) && $data['username'] !== null:
            case !is_array($data['email']) && $data['email'] !== null:
            case !is_array($data['phone']) && $data['phone'] !== null:
            case !is_array($data['external_id']) && $data['external_id'] !== null:
                /** @var User $user */
                $user = User::firstWhere($criteria);

                if (!$user || ($data['status'] !== null && $data['status'] !== $user->status->value)) {
                    throw new UserNotFoundException();
                }

                // optional: check password
                if ($data['password'] !== null) {
                    if (!password_verify($data['password'], $user->password)) {
                        throw new WrongPasswordException();
                    }
                }

                return $user;

            case !is_array($data['firstname']) && $data['firstname'] !== null:
            case !is_array($data['lastname']) && $data['lastname'] !== null:
                $criteria = [
                    'firstname' => $data['firstname'],
                    'lastname' => $data['lastname'],
                ];
                $criteria = array_filter($criteria, fn ($v) => $v !== null);

                return User::where($criteria)->get();

            default:
                $query = User::where($criteria);
                /** @var Builder $query */

                foreach ($data['order'] as $column => $direction) {
                    $query = $query->orderBy($column, $direction);
                }
                if ($data['limit']) {
                    $query = $query->limit($data['limit']);
                }
                if ($data['offset']) {
                    $query = $query->offset($data['offset']);
                }

                return $query->get();
        }
    }

    /**
     * @param string|User|Uuid $entity
     *
     * @throws UsernameAlreadyExistsException
     * @throws EmailAlreadyExistsException
     * @throws EmailBannedException
     * @throws PhoneAlreadyExistsException
     * @throws WrongEmailValueException
     * @throws WrongPhoneValueException
     * @throws UserNotFoundException
     */
    public function update($entity, array $data = []): User
    {
        switch (true) {
            case is_string($entity) && \Ramsey\Uuid\Uuid::isValid($entity):
            case is_object($entity) && is_a($entity, Uuid::class):
                $entity = $this->read(['uuid' => $entity]);

                break;
        }

        if (is_object($entity) && is_a($entity, User::class)) {
            $default = [
                'username' => null,
                'email' => null,
                'phone' => null,
                'password' => null,
                'firstname' => null,
                'lastname' => null,
                'patronymic' => null,
                'gender' => null,
                'birthdate' => null,
                'country' => null,
                'city' => null,
                'address' => null,
                'postcode' => null,
                'additional' => null,
                'allow_mail' => null,
                'status' => null,
                'company' => null,
                'legal' => null,
                'messenger' => null,
                'website' => null,
                'source' => null,
                'group' => null,
                'group_uuid' => null,
                'auth_code' => null,
                'language' => null,
                'external_id' => null,
                'token' => null,
            ];
            $data = array_filter(array_merge($default, $data), fn ($v) => $v !== null);

            if ($data !== $default) {
                $entity->update($data);
            }

            return $entity;
        }

        throw new UserNotFoundException();
    }

    /**
     * @param string|User|Uuid $entity
     *
     * @throws UserNotFoundException
     */
    public function block($entity): ?User
    {
        switch (true) {
            case is_string($entity) && \Ramsey\Uuid\Uuid::isValid($entity):
            case is_object($entity) && is_a($entity, Uuid::class):
                $entity = $this->read(['uuid' => $entity]);

                break;
        }

        if (is_object($entity) && is_a($entity, User::class)) {
            $entity->update([
                'status' => UserStatus::BLOCK,
            ]);

            return $entity;
        }

        throw new UserNotFoundException();
    }

    /**
     * @param string|User|Uuid $entity
     *
     * @throws UserNotFoundException
     */
    public function delete($entity): User
    {
        switch (true) {
            case is_string($entity) && \Ramsey\Uuid\Uuid::isValid($entity):
            case is_object($entity) && is_a($entity, Uuid::class):
                $entity = $this->read(['uuid' => $entity]);

                break;
        }

        if (is_object($entity) && is_a($entity, User::class)) {
            $entity->update([
                'status' => UserStatus::DELETE,
            ]);

            return $entity;
        }

        throw new UserNotFoundException();
    }

    /**
     * @throws EmailBannedException
     */
    protected function check_email(string $email)
    {
        $emails = $this->parameter('user_email_list', '');

        if (trim($emails) && trim($email)) {
            $list = array_map('trim', explode(PHP_EOL, $emails));

            switch ($this->parameter('user_email_list_mode', 'blacklist')) {
                case 'blacklist':
                    foreach ($list as $item) {
                        if (str_ends_with($email, $item)) {
                            return true;
                        }
                    }

                    break;

                case 'whitelist':
                    foreach ($list as $item) {
                        if (!str_ends_with($email, $item)) {
                            return true;
                        }
                    }

                    break;
            }
        }

        return false;
    }
}
