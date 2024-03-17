<?php declare(strict_types=1);

namespace App\Domain\Service\Catalog;

use App\Domain\AbstractService;
use App\Domain\Entities\Catalog\Order;
use App\Domain\Entities\User;
use App\Domain\Repository\Catalog\OrderRepository;
use App\Domain\Service\Catalog\Exception\OrderNotFoundException;
use App\Domain\Service\Catalog\Exception\WrongEmailValueException;
use App\Domain\Service\Catalog\Exception\WrongPhoneValueException;
use Illuminate\Support\Collection;
use Ramsey\Uuid\UuidInterface as Uuid;

class OrderService extends AbstractService
{
    /**
     * @var OrderRepository
     */
    protected mixed $service;

    protected OrderProductService $orderProductService;

    protected function init(): void
    {
        $this->service = $this->entityManager->getRepository(Order::class);
        $this->orderProductService = $this->container->get(OrderProductService::class);
    }

    /**
     * @throws Exception\WrongEmailValueException
     * @throws Exception\WrongPhoneValueException
     */
    public function create(array $data = []): Order
    {
        $default = [
            'serial' => intval(microtime(true)),
            'delivery' => [
                'client' => '',
                'address' => '',
            ],
            'user' => null,
            'phone' => '',
            'email' => '',
            'status' => null,
            'payment' => null,
            'shipping' => '',
            'comment' => '',
            'date' => 'now',
            'external_id' => '',
            'export' => 'manual',
            'system' => '',

            'products' => [],
        ];
        $data = array_merge($default, $data);

        $order = (new Order())
            ->setSerial($data['serial'])
            ->setDelivery($data['delivery'])
            ->setUser($data['user'])
            ->setPhone($data['phone'])
            ->setEmail($data['email'])
            ->setStatus($data['status'])
            ->setPayment($data['payment'])
            ->setShipping($data['shipping'], $this->parameter('common_timezone', 'UTC'))
            ->setComment($data['comment'])
            ->setDate($data['date'], $this->parameter('common_timezone', 'UTC'))
            ->setExternalId($data['external_id'])
            ->setExport($data['export'])
            ->setSystem($data['system']);

        $this->entityManager->persist($order);

        // add products
        $this->orderProductService->process($order, $data['products']);

        $this->entityManager->flush();

        return $order;
    }

    /**
     * @throws OrderNotFoundException
     *
     * @return Collection|Order
     */
    public function read(array $data = [])
    {
        $default = [
            'uuid' => null,
            'user' => null,
            'user_uuid' => null,
            'serial' => null,
            'phone' => null,
            'email' => null,
            'status' => null,
            'payment' => null,
            'external_id' => null,
            'export' => null,
        ];
        $data = array_merge($default, static::$default_read, $data);

        $criteria = [];

        if ($data['uuid'] !== null) {
            $criteria['uuid'] = $data['uuid'];
        }
        if ($data['user'] !== null && is_a($data['user'], User::class)) {
            $criteria['user_uuid'] = $data['user']->getUuid();
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
        if ($data['status'] !== null) {
            if (
                is_string($data['status']) && \Ramsey\Uuid\Uuid::isValid($data['status'])
                || is_object($data['status']) && is_a($data['status'], Uuid::class)
            ) {
                $criteria['status'] = $data['status'];
            }
        }
        if ($data['payment'] !== null) {
            if (
                is_string($data['payment']) && \Ramsey\Uuid\Uuid::isValid($data['payment'])
                || is_object($data['payment']) && is_a($data['payment'], Uuid::class)
            ) {
                $criteria['payment'] = $data['payment'];
            }
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
     *
     * @throws WrongEmailValueException
     * @throws WrongPhoneValueException
     * @throws OrderNotFoundException
     */
    public function update($entity, array $data = []): Order
    {
        switch (true) {
            case is_string($entity) && \Ramsey\Uuid\Uuid::isValid($entity):
            case is_object($entity) && is_a($entity, Uuid::class):
                $entity = $this->service->findOneByUuid((string) $entity);

                break;
        }

        if (is_object($entity) && is_a($entity, Order::class)) {
            $default = [
                'serial' => null,
                'delivery' => null,
                'user' => null,
                'phone' => null,
                'email' => null,
                'status' => null,
                'payment' => null,
                'shipping' => null,
                'comment' => null,
                'date' => null,
                'external_id' => null,
                'export' => null,
                'system' => null,

                'products' => null,
            ];
            $data = array_merge($default, $data);

            if ($data !== $default) {
                if ($data['serial'] !== null) {
                    $entity->setSerial($data['serial']);
                }
                if ($data['delivery'] !== null) {
                    $entity->setDelivery($data['delivery']);
                }
                if ($data['user'] !== null) {
                    $entity->setUser($data['user']);
                }
                if ($data['phone'] !== null) {
                    if (blank($data['phone'])) {
                        $entity->setPhone();
                    } else {
                        $entity->setPhone($data['phone']);
                    }
                }
                if ($data['email'] !== null) {
                    $entity->setEmail($data['email']);
                }
                if ($data['status'] !== null) {
                    $entity->setStatus($data['status']);
                }
                if ($data['payment'] !== null) {
                    $entity->setPayment($data['payment']);
                }
                if ($data['shipping'] !== null) {
                    $entity->setShipping($data['shipping'], $this->parameter('common_timezone', 'UTC'));
                }
                if ($data['comment'] !== null) {
                    $entity->setComment($data['comment']);
                }
                if ($data['date'] !== null) {
                    $entity->setDate($data['date'], $this->parameter('common_timezone', 'UTC'));
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
                if ($data['products'] !== null) {
                    // update products
                    $this->orderProductService->process($entity, $data['products']);
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
     */
    public function delete($entity): bool
    {
        switch (true) {
            case is_string($entity) && \Ramsey\Uuid\Uuid::isValid($entity):
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
