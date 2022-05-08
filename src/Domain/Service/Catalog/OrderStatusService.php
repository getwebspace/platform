<?php declare(strict_types=1);

namespace App\Domain\Service\Catalog;

use App\Domain\AbstractService;
use App\Domain\Entities\Catalog\OrderStatus;
use App\Domain\Repository\Catalog\OrderStatusRepository;
use App\Domain\Service\Catalog\Exception\OrderStatusNotFoundException;
use App\Domain\Service\Catalog\Exception\MissingTitleValueException;
use App\Domain\Service\Catalog\Exception\TitleAlreadyExistsException;
use Illuminate\Support\Collection;
use Ramsey\Uuid\UuidInterface as Uuid;

class OrderStatusService extends AbstractService
{
    /**
     * @var OrderStatusRepository
     */
    protected mixed $service;

    protected function init(): void
    {
        $this->service = $this->entityManager->getRepository(OrderStatus::class);
    }

    /**
     * @throws TitleAlreadyExistsException
     * @throws MissingTitleValueException
     */
    public function create(array $data = []): OrderStatus
    {
        $default = [
            'title' => '',
            'order' => 1,
        ];
        $data = array_merge($default, $data);

        if ($data['title'] && $this->service->findOneByTitle($data['title']) !== null) {
            throw new TitleAlreadyExistsException();
        }
        if (!$data['title']) {
            throw new MissingTitleValueException();
        }

        $os = (new OrderStatus())
            ->setTitle($data['title'])
            ->setOrder(+$data['order']);

        $this->entityManager->persist($os);
        $this->entityManager->flush();

        return $os;
    }

    /**
     * @throws OrderStatusNotFoundException
     *
     * @return Collection|OrderStatus
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
            switch (true) {
                case !is_array($data['uuid']) && $data['uuid'] !== null:
                case !is_array($data['title']) && $data['title'] !== null:
                    $os = $this->service->findOneBy($criteria);

                    if (empty($os)) {
                        throw new OrderStatusNotFoundException();
                    }

                    return $os;

                default:
                    return collect($this->service->findBy($criteria, $data['order'], $data['limit'], $data['offset']));
            }
        } catch (\Doctrine\DBAL\Exception\TableNotFoundException $e) {
            return null;
        }
    }

    /**
     * @param OrderStatus|string|Uuid $entity
     *
     * @throws TitleAlreadyExistsException
     * @throws OrderStatusNotFoundException
     */
    public function update($entity, array $data = []): OrderStatus
    {
        switch (true) {
            case is_string($entity) && \Ramsey\Uuid\Uuid::isValid($entity):
            case is_object($entity) && is_a($entity, Uuid::class):
                $entity = $this->service->findOneByUuid((string) $entity);

                break;
        }

        if (is_object($entity) && is_a($entity, OrderStatus::class)) {
            $default = [
                'title' => '',
                'order' => null,
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
                if ($data['order'] !== null) {
                    $entity->setOrder(+$data['order']);
                }

                $this->entityManager->flush();
            }

            return $entity;
        }

        throw new OrderStatusNotFoundException();
    }

    /**
     * @param OrderStatus|string|Uuid $entity
     *
     * @throws OrderStatusNotFoundException
     */
    public function delete($entity): bool
    {
        switch (true) {
            case is_string($entity) && \Ramsey\Uuid\Uuid::isValid($entity):
            case is_object($entity) && is_a($entity, Uuid::class):
                $entity = $this->service->findOneByUuid((string) $entity);

                break;
        }

        if (is_object($entity) && is_a($entity, OrderStatus::class)) {
            $this->entityManager->remove($entity);
            $this->entityManager->flush();

            return true;
        }

        throw new OrderStatusNotFoundException();
    }
}
