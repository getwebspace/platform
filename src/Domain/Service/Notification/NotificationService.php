<?php declare(strict_types=1);

namespace App\Domain\Service\Notification;

use App\Domain\AbstractService;
use App\Domain\Entities\Notification;
use App\Domain\Repository\NotificationRepository;
use App\Domain\Service\Notification\Exception\MissingMessageValueException;
use App\Domain\Service\Notification\Exception\MissingTitleValueException;
use App\Domain\Service\Notification\Exception\MissingUserUuidValueException;
use App\Domain\Service\Notification\Exception\NotificationNotFoundException;
use Ramsey\Uuid\Uuid;
use Tightenco\Collect\Support\Collection;

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
     * @param array $data
     *
     * @throws MissingUserUuidValueException
     * @throws MissingTitleValueException
     * @throws MissingMessageValueException
     *
     * @return Notification
     */
    public function create(array $data = []): Notification
    {
        $default = [
            'user_uuid' => '',
            'title' => '',
            'message' => '',
            'params' => [],
            'date' => 'now',
        ];
        $data = array_merge($default, $data);

        if (!$data['user_uuid']) {
            throw new MissingUserUuidValueException();
        }
        if (!$data['title']) {
            throw new MissingTitleValueException();
        }
        if (!$data['message']) {
            throw new MissingMessageValueException();
        }

        $file = (new Notification)
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
     * @param array $data
     *
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

        if ($data['uuid']) {
            switch (true) {
                case $data['uuid']:
                    $entry = $this->service->findOneByUuid((string) $data['uuid']);

                    break;
            }

            if (empty($entry)) {
                throw new NotificationNotFoundException();
            }

            return $entry;
        }

        $criteria = [];

        if ($data['user_uuid'] !== null) {
            $criteria['user_uuid'] = $data['user_uuid'];
        }

        return collect($this->service->findBy($criteria, $data['order'], $data['limit'], $data['offset']));
    }

    /**
     * @param Notification|string|Uuid $entity
     * @param array            $data
     *
     * @throws NotificationNotFoundException
     *
     * @return Notification
     */
    public function update($entity, array $data = []): Notification
    {
        switch (true) {
            case is_string($entity) && Uuid::isValid($entity):
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

        if (is_object($entity) && is_a($entity, Notification::class)) {
            $this->entityManager->remove($entity);
            $this->entityManager->flush();

            return true;
        }

        throw new NotificationNotFoundException();
    }
}
