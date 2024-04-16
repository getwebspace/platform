<?php declare(strict_types=1);

namespace App\Domain\Service\User;

use App\Domain\AbstractService;
use App\Domain\Models\User;
use App\Domain\Models\UserGroup;
use App\Domain\Models\UserToken;
use App\Domain\Repository\User\TokenRepository as UserTokenRepository;
use App\Domain\Service\User\Exception\TokenNotFoundException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Ramsey\Uuid\UuidInterface as Uuid;

class TokenService extends AbstractService
{
    public function create(array $data = []): UserToken
    {
        $userToken = new UserToken;
        $userToken->fill($data);

        if (!$userToken->user_uuid) {
            throw new \RuntimeException();
        }

        $userToken->save();

        return $userToken;
    }

    /**
     * @throws TokenNotFoundException
     *
     * @return Collection|UserToken
     */
    public function read(array $data = [])
    {
        $default = [
            'user_uuid' => null,
            'unique' => null,
            'agent' => null,
            'ip' => null,
        ];
        $data = array_merge($default, static::$default_read, $data);

        $criteria = [];

        if ($data['user_uuid'] !== null) {
            $criteria['user_uuid'] = $data['user_uuid'];
        }
        if ($data['unique'] !== null) {
            $criteria['unique'] = $data['unique'];
        }
        if ($data['agent'] !== null) {
            $criteria['agent'] = $data['agent'];
        }
        if ($data['ip'] !== null) {
            $criteria['ip'] = $data['ip'];
        }

        switch (true) {
            case !is_array($data['unique']) && $data['unique'] !== null:
                /** @var UserToken $userToken */
                $userToken = UserToken::firstWhere($criteria);

                return $userToken ?: throw new TokenNotFoundException();

            default:
                $query = UserToken::query();
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

    public function update($entity, array $data = []): UserToken
    {
        switch (true) {
            case is_string($entity) && \Ramsey\Uuid\Uuid::isValid($entity):
            case is_object($entity) && is_a($entity, Uuid::class):
                $entity = $this->read(['uuid' => $entity]);

                break;
        }

        if (is_object($entity) && is_a($entity, UserToken::class)) {
            $entity->fill($data);
            $entity->save();

            return $entity;
        }

        throw new TokenNotFoundException();
    }

    public function delete($entity): bool
    {
        switch (true) {
            case is_string($entity) && \Ramsey\Uuid\Uuid::isValid($entity):
            case is_object($entity) && is_a($entity, Uuid::class):
                $entity = $this->service->findOneByUuid((string) $entity);

                break;
        }

        if (is_object($entity) && is_a($entity, UserToken::class)) {
            $entity->delete();

            return true;
        }

        throw new TokenNotFoundException();
    }
}
