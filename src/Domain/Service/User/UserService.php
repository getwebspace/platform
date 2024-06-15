<?php declare(strict_types=1);

namespace App\Domain\Service\User;

use App\Domain\AbstractService;
use App\Domain\Casts\User\Status as UserStatus;
use App\Domain\Models\User;
use App\Domain\Service\User\Exception\EmailAlreadyExistsException;
use App\Domain\Service\User\Exception\EmailBannedException;
use App\Domain\Service\User\Exception\MissingUniqueValueException;
use App\Domain\Service\User\Exception\PhoneAlreadyExistsException;
use App\Domain\Service\User\Exception\UsernameAlreadyExistsException;
use App\Domain\Service\User\Exception\UserNotFoundException;
use App\Domain\Service\User\Exception\WrongEmailValueException;
use App\Domain\Service\User\Exception\WrongPasswordException;
use App\Domain\Service\User\Exception\WrongPhoneValueException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Ramsey\Uuid\UuidInterface as Uuid;

class UserService extends AbstractService
{
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
        $user = new User();
        $user->fill($data);

        if (!$user->username && !$user->email && !$user->phone) {
            throw new MissingUniqueValueException();
        }

        if ($user->username && User::firstWhere(['username' => $user->username]) !== null) {
            throw new UsernameAlreadyExistsException();
        }

        if ($user->email) {
            if (User::firstWhere(['email' => $user->email]) !== null) {
                throw new EmailAlreadyExistsException();
            }
            if ($this->check_email($user->email)) {
                throw new EmailBannedException();
            }
        }

        if ($user->phone && User::firstWhere(['phone' => $user->phone]) !== null) {
            throw new PhoneAlreadyExistsException();
        }

        $user->save();

        return $user;
    }

    /**
     * @throws WrongPasswordException
     * @throws UserNotFoundException
     *
     * @return Collection|User
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
            'is_allow_mail' => null,
            'status' => null,
            'external_id' => null,
            'provider' => null,
            'unique' => null,
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
        if ($data['is_allow_mail'] !== null) {
            $criteria['is_allow_mail'] = (bool) $data['is_allow_mail'];
        }
        if ($data['status'] !== null) {
            if (is_array($data['status'])) {
                $statuses = array_intersect($data['status'], \App\Domain\Casts\User\Status::LIST);
            } else {
                $statuses = in_array($data['status'], \App\Domain\Casts\User\Status::LIST, true) ? [$data['status']] : [];
            }

            if ($statuses) {
                $criteria['status'] = $statuses;
            }
        }
        if ($data['external_id'] !== null) {
            $criteria['external_id'] = $data['external_id'];
        }

        switch (true) {
            case !is_array($data['identifier']) && $data['identifier'] !== null:
            case !is_array($data['uuid']) && $data['uuid'] !== null:
            case !is_array($data['username']) && $data['username'] !== null:
            case !is_array($data['email']) && $data['email'] !== null:
            case !is_array($data['phone']) && $data['phone'] !== null:
            case !is_array($data['external_id']) && $data['external_id'] !== null:
                /** @var User $user */
                $user = User::firstWhere(function (Builder $query) use ($data): void {
                    switch (true) {
                        case $data['identifier'] !== null:
                            $query->orWhere($this->db->raw('lower(email)'), strtolower($data['identifier']));
                            $query->orWhere($this->db->raw('lower(phone)'), strtolower($data['identifier']));
                            $query->orWhere($this->db->raw('lower(username)'), strtolower($data['identifier']));
                            break;
                        case $data['uuid'] !== null:
                            $query->where($this->db->raw('lower(uuid)'), strtolower($data['uuid']));
                            break;
                        case $data['username'] !== null:
                            $query->where($this->db->raw('lower(username)'), strtolower($data['username']));
                            break;
                        case $data['email'] !== null:
                            $query->where($this->db->raw('lower(email)'), strtolower($data['email']));
                            break;
                        case $data['phone'] !== null:
                            $query->where($this->db->raw('lower(phone)'), strtolower($data['phone']));
                            break;
                        case $data['external_id'] !== null:
                            $query->where($this->db->raw('lower(external_id)'), strtolower($data['external_id']));
                            break;
                    }
                });

                if (!$user || ($data['status'] !== null && $data['status'] !== $user->status)) {
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
                $query = User::query();
                /** @var Builder $query */
                if (!empty($data['firstname'])) {
                    $query->orWhere('firstname', 'like', $data['firstname'] . '%');
                }
                if (!empty($data['lastname'])) {
                    $query->orWhere('firstname', 'like', $data['lastname'] . '%');
                }
                if ($data['limit']) {
                    $query = $query->limit($data['limit']);
                }
                if ($data['offset']) {
                    $query = $query->offset($data['offset']);
                }

                return $query->get();

            case !is_array($data['provider']) && $data['provider'] !== null && !is_array($data['unique']) && $data['unique'] !== null:
                /** @var User $user */
                $user = User::query()
                    ->select('user.*')
                    ->join('user_integration as ui', 'user.uuid', '=', 'ui.user_uuid')
                    ->where('ui.provider', $data['provider'])
                    ->where('ui.unique', $data['unique'])
                    ->first();

                return $user ?: throw new UserNotFoundException();

            default:
                $query = User::query();
                /** @var Builder $query */
                foreach ($criteria as $key => $value) {
                    if (is_array($value)) {
                        $query->whereIn($key, $value);
                    } else {
                        $query->where($key, $value);
                    }
                }
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
            $entity->fill($data);

            if ($entity->isDirty('username')) {
                $found = User::firstWhere(['username' => $entity->username]);

                if ($found && $found->uuid !== $entity->uuid) {
                    throw new UsernameAlreadyExistsException();
                }
            }

            if ($entity->isDirty('email')) {
                $found = User::firstWhere(['email' => $entity->email]);

                if ($found && $found->uuid !== $entity->uuid) {
                    throw new EmailAlreadyExistsException();
                }
                if ($this->check_email($entity->email)) {
                    throw new EmailBannedException();
                }
            }

            if ($entity->isDirty('phone')) {
                $found = User::firstWhere(['phone' => $entity->phone]);

                if ($found && $found->uuid !== $entity->uuid) {
                    throw new PhoneAlreadyExistsException();
                }
            }

            $entity->save();

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
