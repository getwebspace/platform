<?php declare(strict_types=1);

namespace App\Domain\Service\User;

use App\Domain\AbstractService;
use App\Domain\Models\Page;
use App\Domain\Models\UserGroup;
use App\Domain\Repository\User\GroupRepository as UserGroupRepository;
use App\Domain\Service\User\Exception\MissingTitleValueException;
use App\Domain\Service\User\Exception\TitleAlreadyExistsException;
use App\Domain\Service\User\Exception\UserGroupNotFoundException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Ramsey\Uuid\UuidInterface as Uuid;

class GroupService extends AbstractService
{


    /**
     * @throws MissingTitleValueException
     * @throws TitleAlreadyExistsException
     */
    public function create(array $data = []): UserGroup
    {
        $default = [
            'title' => '',
            'description' => '',
            'access' => [],
        ];
        $data = array_merge($default, $data);

        if (!$data['title']) {
            throw new MissingTitleValueException();
        }

        $userGroup = new UserGroup;
        $userGroup->fill($data);

        if (UserGroup::firstWhere(['title' => $userGroup->title]) !== null) {
            throw new TitleAlreadyExistsException();
        }

        $userGroup->save();

        return $userGroup;
    }

    /**
     * @throws UserGroupNotFoundException
     *
     * @return Collection|UserGroup
     */
    public function read(array $data = [])
    {
        $default = [
            'uuid' => null,
            'title' => null,
        ];
        $data = array_merge($default, static::$default_read, $data);

        $criteria = [];

        if ($data['uuid'] !== null) {
            $criteria['uuid'] = $data['uuid'];
        }
        if ($data['title'] !== null) {
            $criteria['title'] = $data['title'];
        }

        switch (true) {
            case !is_array($data['uuid']) && $data['uuid'] !== null:
            case !is_array($data['title']) && $data['title'] !== null:
                /** @var UserGroup $userGroup */
                $userGroup = UserGroup::firstWhere($criteria);

                return $userGroup ?: throw new UserGroupNotFoundException();

            default:
                $query = UserGroup::where($criteria);
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
     * @param string|UserGroup|Uuid $entity
     *
     * @throws TitleAlreadyExistsException
     * @throws UserGroupNotFoundException
     */
    public function update($entity, array $data = []): UserGroup
    {
        switch (true) {
            case is_string($entity) && \Ramsey\Uuid\Uuid::isValid($entity):
            case is_object($entity) && is_a($entity, Uuid::class):
                $entity = $this->read(['uuid' => $entity]);

                break;
        }

        if (is_object($entity) && is_a($entity, UserGroup::class)) {
            $default = [
                'title' => null,
                'description' => null,
                'access' => null,
            ];
            $data = array_filter(array_merge($default, $data), fn ($v) => $v !== null);

            if ($data !== $default) {
                $entity->fill($data);

                if (($found = UserGroup::firstWhere(['title' => $entity->title])) !== null && $found->uuid !== $entity->uuid) {
                    throw new TitleAlreadyExistsException();
                }

                $entity->save();
            }

            return $entity;
        }

        throw new UserGroupNotFoundException();
    }

    /**
     * @param string|UserGroup|Uuid $entity
     *
     * @throws UserGroupNotFoundException
     */
    public function delete($entity): bool
    {
        switch (true) {
            case is_string($entity) && \Ramsey\Uuid\Uuid::isValid($entity):
            case is_object($entity) && is_a($entity, Uuid::class):
                $entity = $this->read(['uuid' => $entity]);

                break;
        }

        if (is_object($entity) && is_a($entity, UserGroup::class)) {
            $entity->delete();

            return true;
        }

        throw new UserGroupNotFoundException();
    }
}
