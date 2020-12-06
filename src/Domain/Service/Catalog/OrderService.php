<?php declare(strict_types=1);

namespace App\Domain\Service\Catalog;

use App\Domain\AbstractService;
use App\Domain\Entities\Catalog\Order;
use App\Domain\Repository\Catalog\OrderRepository;
use App\Domain\Service\Catalog\Exception\OrderNotFoundException;
use Illuminate\Support\Collection;
use Ramsey\Uuid\Uuid;

class OrderService extends AbstractService
{
    protected const SERIAL_LENGTH = 7;

    /**
     * @var OrderRepository
     */
    protected $service;

    protected function init(): void
    {
        $this->service = $this->entityManager->getRepository(Order::class);
    }

    /**
     * @param array $data
     *
     * @return Order
     */
    public function create(array $data = []): Order
    {
        $default = [
            'delivery' => [
                'client' => '',
                'address' => '',
            ],
            'user_uuid' => \Ramsey\Uuid\Uuid::NIL,
            'list' => [
                // 'uuid' => 'count',
            ],
            'phone' => '',
            'email' => '',
            'status' => \App\Domain\Types\Catalog\OrderStatusType::STATUS_NEW,
            'comment' => '',
            'shipping' => '',
            'date' => 'now',
            'external_id' => '',
            'export' => 'manual',
            'system' => '',
        ];
        $data = array_merge($default, $data);

        $order = (new Order)
            ->setDelivery($data['delivery'])
            ->setUserUuid($data['user_uuid'])
            ->setList($data['list'])
            ->setPhone($data['phone'])
            ->setEmail($data['email'])
            ->setStatus($data['status'])
            ->setComment($data['comment'])
            ->setShipping($data['shipping'])
            ->setDate($data['date'])
            ->setExternalId($data['external_id'])
            ->setExport($data['export'])
            ->setSystem($data['system']);

        // set serial value
        if (isset($_ENV['SIMPLE_ORDER_SERIAL']) && $_ENV['SIMPLE_ORDER_SERIAL']) {
            $lastOrder = $this->service->findOneBy([], ['date' => 'desc']);

            $order->setSerial(
                $lastOrder ? ((int) $lastOrder->getSerial()) + 1 : 1
            );
        } else {
            $order->setSerial(
                mb_strtoupper(mb_substr(bin2hex(random_bytes(10 + self::SERIAL_LENGTH)), 0, self::SERIAL_LENGTH))
            );
        }

        $this->entityManager->persist($order);
        $this->entityManager->flush();

        return $order;
    }

    /**
     * @param array $data
     *
     * @throws OrderNotFoundException
     *
     * @return Collection|Order
     */
    public function read(array $data = [])
    {
        $default = [
            'uuid' => null,
            'user_uuid' => null,
            'serial' => null,
            'phone' => null,
            'email' => null,
            'status' => null,
            'external_id' => null,
            'export' => null,
        ];
        $data = array_merge($default, static::$default_read, $data);

        $criteria = [];

        if ($data['uuid'] !== null) {
            $criteria['uuid'] = $data['uuid'];
        }
        if ($data['user_uuid'] !== null) {
            $criteria['user_uuid'] = $data['user_uuid'];
        }
        if ($data['serial'] !== null) {
            $criteria['serial'] = $data['serial'];
        }
        if ($data['phone'] !== null) {
            $criteria['phone'] = $data['phone'];
        }
        if ($data['email'] !== null) {
            $criteria['email'] = $data['email'];
        }
        if ($data['status'] !== null && in_array($data['status'], \App\Domain\Types\Catalog\OrderStatusType::LIST, true)) {
            $criteria['status'] = $data['status'];
        }
        if ($data['external_id'] !== null) {
            $criteria['external_id'] = $data['external_id'];
        }
        if ($data['export'] !== null) {
            $criteria['export'] = $data['export'];
        }

        try {
            switch (true) {
                case !is_array($data['uuid']) && $data['uuid'] !== null:
                case !is_array($data['serial']) && $data['serial'] !== null:
                case !is_array($data['external_id']) && $data['external_id'] !== null:
                    $order = $this->service->findOneBy($criteria);

                    if (empty($order)) {
                        throw new OrderNotFoundException();
                    }

                    return $order;

                default:
                    return collect($this->service->findBy($criteria, $data['order'], $data['limit'], $data['offset']));
            }
        } catch (\Doctrine\DBAL\Exception\TableNotFoundException $e) {
            return null;
        }
    }

    /**
     * @param Order|string|Uuid $entity
     * @param array             $data
     *
     * @throws OrderNotFoundException
     *
     * @return Order
     */
    public function update($entity, array $data = []): Order
    {
        switch (true) {
            case is_string($entity) && Uuid::isValid($entity):
            case is_object($entity) && is_a($entity, Uuid::class):
                $entity = $this->service->findOneByUuid((string) $entity);

                break;
        }

        if (is_object($entity) && is_a($entity, Order::class)) {
            $default = [
                'delivery' => null,
                'user_uuid' => null,
                'list' => null,
                'phone' => null,
                'email' => null,
                'status' => null,
                'comment' => null,
                'shipping' => null,
                'date' => null,
                'external_id' => null,
                'export' => null,
                'system' => null,
            ];
            $data = array_merge($default, $data);

            if ($data !== $default) {
                if ($data['delivery'] !== null) {
                    $entity->setDelivery($data['delivery']);
                }
                if ($data['user_uuid'] !== null) {
                    $entity->setUserUuid($data['user_uuid']);
                }
                if ($data['list'] !== null) {
                    $entity->setList($data['list']);
                }
                if ($data['phone'] !== null) {
                    $entity->setPhone($data['phone']);
                }
                if ($data['email'] !== null) {
                    $entity->setEmail($data['email']);
                }
                if ($data['status'] !== null) {
                    $entity->setStatus($data['status']);
                }
                if ($data['comment'] !== null) {
                    $entity->setComment($data['comment']);
                }
                if ($data['shipping'] !== null) {
                    $entity->setShipping($data['shipping']);
                }
                if ($data['date'] !== null) {
                    $entity->setDate($data['date']);
                }
                if ($data['external_id'] !== null) {
                    $entity->setExternalId($data['external_id']);
                }
                if ($data['export'] !== null) {
                    $entity->setExport($data['export']);
                }
                if ($data['system'] !== null) {
                    $entity->setSystem($data['system']);
                }

                $this->entityManager->flush();
            }

            return $entity;
        }

        throw new OrderNotFoundException();
    }

    /**
     * @param Order|string|Uuid $entity
     *
     * @throws OrderNotFoundException
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

        if (is_object($entity) && is_a($entity, Order::class)) {
            $this->entityManager->remove($entity);
            $this->entityManager->flush();

            return true;
        }

        throw new OrderNotFoundException();
    }
}
