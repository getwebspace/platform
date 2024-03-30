<?php declare(strict_types=1);

namespace App\Domain\Service\User;

use App\Domain\AbstractService;
use App\Domain\Models\UserSubscriber;
use App\Domain\Service\User\Exception\EmailAlreadyExistsException;
use App\Domain\Service\User\Exception\MissingUniqueValueException;
use App\Domain\Service\User\Exception\UserNotFoundException;
use App\Domain\Service\User\Exception\WrongEmailValueException;
use Illuminate\Support\Collection;
use Ramsey\Uuid\UuidInterface as Uuid;
use Illuminate\Database\Eloquent\Builder;

class SubscriberService extends AbstractService
{


    /**
     * @throws MissingUniqueValueException
     * @throws EmailAlreadyExistsException
     */
    public function create(array $data = []): UserSubscriber
    {
        $default = [
            'email' => '',
            'date' => 'now',
        ];
        $data = array_merge($default, $data);

        if (!$data['email']) {
            throw new MissingUniqueValueException();
        }

        $userSubscriber = new UserSubscriber();
        $userSubscriber->fill($data);

        if (UserSubscriber::firstWhere(['email' => $userSubscriber->email]) !== null) {
            throw new EmailAlreadyExistsException();
        }

        $userSubscriber->save();

        return $userSubscriber;
    }

    /**
     * @throws UserNotFoundException
     *
     * @return Collection|UserSubscriber
     */
    public function read(array $data = [])
    {
        $default = [
            'uuid' => null,
            'email' => null,
            'date' => null,
        ];
        $data = array_merge($default, static::$default_read, $data);

        $criteria = [];

        if ($data['uuid'] !== null) {
            $criteria['uuid'] = $data['uuid'];
        }
        if ($data['email'] !== null) {
            $criteria['email'] = $data['email'];
        }
        if ($data['date'] !== null) {
            $criteria['date'] = $data['date'];
        }

        switch (true) {
            case !is_array($data['uuid']) && $data['uuid'] !== null:
            case !is_array($data['email']) && $data['email'] !== null:
                /** @var UserSubscriber $page */
                $subscriber = UserSubscriber::firstWhere($criteria);

                return $subscriber ?: throw new UserNotFoundException();

            default:
                $query = UserSubscriber::where($criteria);
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
     * @param string|UserSubscriber|Uuid $entity
     *
     * @throws EmailAlreadyExistsException
     * @throws UserNotFoundException
     * @throws WrongEmailValueException
     *
     * @return UserSubscriber
     */
    public function update($entity, array $data = [])
    {
        switch (true) {
            case is_string($entity) && \Ramsey\Uuid\Uuid::isValid($entity):
            case is_object($entity) && is_a($entity, Uuid::class):
                $entity = $this->read(['uuid' => $entity]);

                break;
        }

        if (is_object($entity) && is_a($entity, UserSubscriber::class)) {
            $default = [
                'email' => null,
                'date' => null,
            ];
            $data = array_filter(array_merge($default, $data), fn ($v) => $v !== null);

            if ($data !== $default) {
                $entity->fill($data);

                if (($found = UserSubscriber::firstWhere(['email' => $entity->email])) !== null && $found->uuid !== $entity->uuid) {
                    throw new EmailAlreadyExistsException();
                }

                $entity->save();
            }

            return $entity;
        }

        throw new UserNotFoundException();
    }

    /**
     * @param mixed $entity
     *
     * @throws UserNotFoundException
     */
    public function delete($entity): bool
    {
        switch (true) {
            case is_string($entity) && \Ramsey\Uuid\Uuid::isValid($entity):
            case is_object($entity) && is_a($entity, Uuid::class):
                $entity = $this->read(['uuid' => $entity]);

                break;
        }

        if (is_object($entity) && is_a($entity, UserSubscriber::class)) {
            $entity->delete();

            return true;
        }

        throw new UserNotFoundException();
    }
}
