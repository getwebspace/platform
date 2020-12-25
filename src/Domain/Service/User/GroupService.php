<?php declare(strict_types=1);

namespace App\Domain\Service\User;

use App\Domain\AbstractService;
use App\Domain\Entities\User\Group as UserGroup;
use App\Domain\Repository\User\GroupRepository as UserGroupRepository;
use App\Domain\Service\User\Exception\MissingTitleValueException;
use App\Domain\Service\User\Exception\TitleAlreadyExistsException;
use App\Domain\Service\User\Exception\UserGroupNotFoundException;
use Illuminate\Support\Collection;
use Ramsey\Uuid\Uuid;

class GroupService extends AbstractService
{
    /**
     * @var UserGroupRepository
     */
    protected $service;

    protected function init(): void
    {
        $this->service = $this->entityManager->getRepository(UserGroup::class);
    }

    /**
     * @param array $data
     *
     * @throws MissingTitleValueException
     * @throws TitleAlreadyExistsException
     *
     * @return UserGroup
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
        if ($data['title'] && $this->service->findOneByTitle($data['title']) !== null) {
            throw new TitleAlreadyExistsException();
        }

        $userGroup = (new UserGroup())
            ->setTitle($data['title'])
            ->setDescription($data['description'])
            ->setAccess($data['access']);

        $this->entityManager->persist($userGroup);
        $this->entityManager->flush();

        return $userGroup;
    }

    /**
     * @param array $data
     *
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

        try {
            if (
                !is_array($data['uuid']) && $data['uuid'] !== null ||
                !is_array($data['title']) && $data['title'] !== null
            ) {
                switch (true) {
                    case $data['uuid']:
                        $userGroup = $this->service->findOneByUuid($data['uuid']);

                        break;

                    case $data['title']:
                        $userGroup = $this->service->findOneByTitle($data['title']);

                        break;
                }

                if (empty($userGroup)) {
                    throw new UserGroupNotFoundException();
                }

                return $userGroup;
            }

            return collect($this->service->findBy($criteria, $data['order'], $data['limit'], $data['offset']));
        } catch (\Doctrine\DBAL\Exception\TableNotFoundException $e) {
            return null;
        }
    }

    /**
     * @param string|UserGroup|Uuid $entity
     * @param array                 $data
     *
     * @throws TitleAlreadyExistsException
     * @throws UserGroupNotFoundException
     *
     * @return UserGroup
     */
    public function update($entity, array $data = []): UserGroup
    {
        switch (true) {
            case is_string($entity) && Uuid::isValid($entity):
            case is_object($entity) && is_a($entity, Uuid::class):
                $entity = $this->service->findOneByUuid((string) $entity);

                break;
        }

        if (is_object($entity) && is_a($entity, UserGroup::class)) {
            $default = [
                'title' => null,
                'description' => null,
                'access' => null,
            ];
            $data = array_merge($default, $data);

            if ($data !== $default) {
                if ($data['title'] !== null) {
                    $found = $this->service->findOneByTitle($data['title']);

                    if ($found === null || $found === $entity) {
                        $entity->setTitle($data['title']);
                    } else {
                        throw new TitleAlreadyExistsException();
                    }
                }
                if ($data['description'] !== null) {
                    $entity->setDescription($data['description']);
                }
                if ($data['access'] !== null) {
                    $entity->setAccess($data['access']);
                }

                $this->entityManager->flush();
            }

            return $entity;
        }

        throw new UserGroupNotFoundException();
    }

    /**
     * @param string|UserGroup|Uuid $entity
     *
     * @throws UserGroupNotFoundException
     *
     * @return bool
     */
    public function delete($entity): bool
    {
        switch (true) {
            case is_string($entity) && Uuid::isValid($entity):
            case is_object($entity) && is_a($entity, Uuid::class):
                $entity = $this->service->findOneByUuid((string) $entity);

                break;
        }

        if (is_object($entity) && is_a($entity, UserGroup::class)) {
            $this->entityManager->remove($entity);
            $this->entityManager->flush();

            return true;
        }

        throw new UserGroupNotFoundException();
    }
}
