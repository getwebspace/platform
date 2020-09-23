<?php declare(strict_types=1);

namespace App\Domain\Service\GuestBook;

use App\Domain\AbstractService;
use App\Domain\Entities\GuestBook;
use App\Domain\Repository\GuestBookRepository;
use App\Domain\Service\GuestBook\Exception\EntryNotFoundException;
use App\Domain\Service\GuestBook\Exception\MissingEmailValueException;
use App\Domain\Service\GuestBook\Exception\MissingMessageValueException;
use App\Domain\Service\GuestBook\Exception\MissingNameValueException;
use Illuminate\Support\Collection;
use Ramsey\Uuid\Uuid;

class GuestBookService extends AbstractService
{
    /**
     * @var GuestBookRepository
     */
    protected $service;

    protected function init(): void
    {
        $this->service = $this->entityManager->getRepository(GuestBook::class);
    }

    /**
     * @param array $data
     *
     * @throws MissingNameValueException
     * @throws MissingEmailValueException
     * @throws MissingMessageValueException
     *
     * @return GuestBook
     */
    public function create(array $data = []): GuestBook
    {
        $default = [
            'name' => '',
            'email' => '',
            'message' => '',
            'response' => '',
            'status' => \App\Domain\Types\GuestBookStatusType::STATUS_MODERATE,
            'date' => 'now',
        ];
        $data = array_merge($default, $data);

        if (!$data['name']) {
            throw new MissingNameValueException();
        }
        if (!$data['email']) {
            throw new MissingEmailValueException();
        }
        if (!$data['message']) {
            throw new MissingMessageValueException();
        }

        $file = (new GuestBook)
            ->setName($data['name'])
            ->setEmail($data['email'])
            ->setMessage($data['message'])
            ->setResponse($data['response'])
            ->setStatus($data['status'])
            ->setDate($data['date']);

        $this->entityManager->persist($file);
        $this->entityManager->flush();

        return $file;
    }

    /**
     * @param array $data
     *
     * @throws EntryNotFoundException
     *
     * @return Collection|GuestBook
     */
    public function read(array $data = [])
    {
        $default = [
            'uuid' => null,
            'email' => null,
            'status' => null,
        ];
        $data = array_merge($default, static::$default_read, $data);

        $criteria = [];

        if ($data['uuid'] !== null) {
            $criteria['uuid'] = $data['uuid'];
        }
        if ($data['email'] !== null) {
            $criteria['email'] = $data['email'];
        }
        if ($data['status'] !== null) {
            $criteria['status'] = $data['status'];
        }

        try {
            switch (true) {
                case !is_array($data['uuid']) && $data['uuid'] !== null:
                    $entry = $this->service->findOneBy($criteria);

                    if (empty($entry)) {
                        throw new EntryNotFoundException();
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
     * @param GuestBook|string|Uuid $entity
     * @param array                 $data
     *
     * @throws EntryNotFoundException
     *
     * @return GuestBook
     */
    public function update($entity, array $data = []): GuestBook
    {
        switch (true) {
            case is_string($entity) && Uuid::isValid($entity):
            case is_object($entity) && is_a($entity, Uuid::class):
                $entity = $this->service->findOneByUuid((string) $entity);

                break;
        }

        if (is_object($entity) && is_a($entity, GuestBook::class)) {
            $default = [
                'name' => null,
                'email' => null,
                'message' => null,
                'response' => null,
                'status' => null,
                'date' => null,
            ];
            $data = array_merge($default, $data);

            if ($data !== $default) {
                if ($data['name'] !== null) {
                    $entity->setName($data['name']);
                }
                if ($data['email'] !== null) {
                    $entity->setEmail($data['email']);
                }
                if ($data['message'] !== null) {
                    $entity->setMessage($data['message']);
                }
                if ($data['response'] !== null) {
                    $entity->setResponse($data['response']);
                }
                if ($data['status'] !== null) {
                    $entity->setStatus($data['status']);
                }
                if ($data['date'] !== null) {
                    $entity->setDate($data['date']);
                }

                $this->entityManager->flush();
            }

            return $entity;
        }

        throw new EntryNotFoundException();
    }

    /**
     * @param GuestBook|string|Uuid $entity
     *
     * @throws EntryNotFoundException
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

        if (is_object($entity) && is_a($entity, GuestBook::class)) {
            $this->entityManager->remove($entity);
            $this->entityManager->flush();

            return true;
        }

        throw new EntryNotFoundException();
    }
}
