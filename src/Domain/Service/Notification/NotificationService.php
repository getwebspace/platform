<?php declare(strict_types=1);

namespace App\Domain\Service\Notification;

use App\Domain\AbstractService;
use App\Domain\Entities\Notification;
use App\Domain\Repository\NotificationRepository;
use App\Domain\Service\Notification\Exception\MissingMessageValueException;
use App\Domain\Service\Notification\Exception\MissingTitleValueException;
use App\Domain\Service\Notification\Exception\MissingUserUuidValueException;
use App\Domain\Service\Notification\Exception\NotificationNotFoundException;
use Illuminate\Support\Collection;
use Ramsey\Uuid\UuidInterface as Uuid;

class NotificationService extends AbstractService
{
    /**
     * @var NotificationRepository
     */
    protected $service;

    protected function init(): void
    {
        $this->service = $this->entityManager->getRepository(Notification::class);
    }

    /**
     * @throws MissingUserUuidValueException
     * @throws MissingTitleValueException
     * @throws MissingMessageValueException
     */
    public function create(array $data = []): Notification
    {
        $default = [
            'user_uuid' => \Ramsey\Uuid\Uuid::NIL,
            'title' => '',
            'message' => '',
            'params' => [],
            'date' => 'now',
        ];
        $data = array_merge($default, $data);

        if (blank($data['user_uuid'])) {
            throw new MissingUserUuidValueException();
        }
        if (!$data['title']) {
            throw new MissingTitleValueException();
        }
        if (!$data['message']) {
            throw new MissingMessageValueException();
        }

        $file = (new Notification())
            ->setUserUuid($data['user_uuid'])
            ->setTitle($data['title'])
            ->setMessage($data['message'])
            ->setParams($data['params'])
            ->setDate($data['date']);

        $this->entityManager->persist($file);
        $this->entityManager->flush();

        return $file;
    }

    /**
     * @throws NotificationNotFoundException
     *
     * @return Collection|Notification
     */
    public function read(array $data = [])
    {
        $default = [
            'uuid' => null,
            'user_uuid' => null,
        ];
        $data = array_merge($default, static::$default_read, $data);

        $criteria = [];

        if ($data['uuid'] !== null) {
            $criteria['uuid'] = $data['uuid'];
        }
        if ($data['user_uuid'] !== null) {
            $criteria['user_uuid'] = $data['user_uuid'];
        }

        try {
            switch (true) {
                case !is_array($data['uuid']) && $data['uuid'] !== null:
                    $entry = $this->service->findOneBy($criteria);

                    if (empty($entry)) {
                        throw new NotificationNotFoundException();
                    }

                    return $entry;

                default:
                    return collect($this->service->findBy($criteria, $data['order'], $data['limit'], $data['offset']));
            }
        } catch (\Doctrine\DBAL\Exception\TableNotFoundException $e) {
            return null;
        }
    }

    /**
     * @param Notification|string|Uuid $entity
     *
     * @throws NotificationNotFoundException
     */
    public function update($entity, array $data = []): Notification
    {
        switch (true) {
            case is_string($entity) && \Ramsey\Uuid\Uuid::isValid($entity):
            case is_object($entity) && is_a($entity, Uuid::class):
                $entity = $this->service->findOneByUuid((string) $entity);

                break;
        }

        if (is_object($entity) && is_a($entity, Notification::class)) {
            $default = [
                'user_uuid' => null,
                'title' => null,
                'message' => null,
                'params' => null,
                'date' => null,
            ];
            $data = array_merge($default, $data);

            if ($data !== $default) {
                if ($data['user_uuid'] !== null) {
                    $entity->setUserUuid($data['user_uuid']);
                }
                if ($data['title'] !== null) {
                    $entity->setTitle($data['title']);
                }
                if ($data['message'] !== null) {
                    $entity->setMessage($data['message']);
                }
                if ($data['params'] !== null) {
                    $entity->setParams($data['params']);
                }
                if ($data['date'] !== null) {
                    $entity->setDate($data['date']);
                }

                $this->entityManager->flush();
            }

            return $entity;
        }

        throw new NotificationNotFoundException();
    }

    /**
     * @param Notification|string|Uuid $entity
     *
     * @throws NotificationNotFoundException
     */
    public function delete($entity): bool
    {
        switch (true) {
            case is_string($entity) && \Ramsey\Uuid\Uuid::isValid($entity):
            case is_object($entity) && is_a($entity, Uuid::class):
                $entity = $this->service->findOneByUuid((string) $entity);

                break;
        }

        if (is_object($entity) && is_a($entity, Notification::class)) {
            $this->entityManager->remove($entity);
            $this->entityManager->flush();

            return true;
        }

        throw new NotificationNotFoundException();
    }
}
