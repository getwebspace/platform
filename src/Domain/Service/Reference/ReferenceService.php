<?php declare(strict_types=1);

namespace App\Domain\Service\Reference;

use App\Domain\AbstractService;
use App\Domain\Entities\Reference;
use App\Domain\Repository\ReferenceRepository;
use App\Domain\Service\Reference\Exception\AddressAlreadyExistsException;
use App\Domain\Service\Reference\Exception\MissingTitleValueException;
use App\Domain\Service\Reference\Exception\ReferenceNotFoundException;
use App\Domain\Service\Reference\Exception\TitleAlreadyExistsException;
use Illuminate\Support\Collection;
use Ramsey\Uuid\UuidInterface as Uuid;

class ReferenceService extends AbstractService
{
    /**
     * @var ReferenceRepository
     */
    protected mixed $service;

    protected function init(): void
    {
        $this->service = $this->entityManager->getRepository(Reference::class);
    }

    /**
     * @throws TitleAlreadyExistsException
     * @throws MissingTitleValueException
     */
    public function create(array $data = []): Reference
    {
        $default = [
            'type' => '',
            'title' => '',
            'value' => '',
            'order' => '',
            'status' => '',
        ];
        $data = array_merge($default, $data);

        if ($data['title'] && $this->service->findOneByTitle($data['title']) !== null) {
            throw new TitleAlreadyExistsException();
        }
        if (!$data['title']) {
            throw new MissingTitleValueException();
        }

        $reference = (new Reference())
            ->setType($data['type'])
            ->setTitle($data['title'])
            ->setValue($data['value'])
            ->setOrder(+$data['order'])
            ->setStatus($data['status']);

        $this->entityManager->persist($reference);
        $this->entityManager->flush();

        return $reference;
    }

    /**
     * @throws ReferenceNotFoundException
     *
     * @return Collection|Reference
     */
    public function read(array $data = [])
    {
        $default = [
            'uuid' => null,
            'title' => null,
            'type' => null,
        ];
        $data = array_merge($default, static::$default_read, $data);

        $criteria = [];

        if ($data['uuid'] !== null) {
            $criteria['uuid'] = $data['uuid'];
        }
        if ($data['title'] !== null) {
            $criteria['title'] = $data['title'];
        }
        if ($data['type'] !== null && in_array($data['type'], \App\Domain\Types\ReferenceTypeType::LIST, true)) {
            $criteria['type'] = $data['type'];
        }

        try {
            switch (true) {
                case !is_array($data['uuid']) && $data['uuid'] !== null:
                case !is_array($data['title']) && $data['title'] !== null:
                    $page = $this->service->findOneBy($criteria);

                    if (empty($page)) {
                        throw new ReferenceNotFoundException();
                    }

                    return $page;

                default:
                    return collect($this->service->findBy($criteria, $data['order'], $data['limit'], $data['offset']));
            }
        } catch (\Doctrine\DBAL\Exception\TableNotFoundException $e) {
            return null;
        }
    }

    /**
     * @param Reference|string|Uuid $entity
     *
     * @throws TitleAlreadyExistsException
     * @throws ReferenceNotFoundException
     */
    public function update($entity, array $data = []): Reference
    {
        switch (true) {
            case is_string($entity) && \Ramsey\Uuid\Uuid::isValid($entity):
            case is_object($entity) && is_a($entity, Uuid::class):
                $entity = $this->service->findOneByUuid((string) $entity);

                break;
        }

        if (is_object($entity) && is_a($entity, Reference::class)) {
            $default = [
                'type' => null,
                'title' => null,
                'value' => null,
                'order' => null,
            ];
            $data = array_merge($default, $data);

            if ($data !== $default) {
                if ($data['type'] !== null) {
                    $entity->setType($data['type']);
                }
                if ($data['title'] !== null) {
                    $found = $this->service->findOneByTitle($data['title']);

                    if ($found === null || $found === $entity) {
                        $entity->setTitle($data['title']);
                    } else {
                        throw new TitleAlreadyExistsException();
                    }
                }
                if ($data['value'] !== null) {
                    $entity->setValue($data['value']);
                }
                if ($data['order'] !== null) {
                    $entity->setOrder(+$data['order']);
                }

                $this->entityManager->flush();
            }

            return $entity;
        }

        throw new ReferenceNotFoundException();
    }

    /**
     * @param Reference|string|Uuid $entity
     *
     * @throws ReferenceNotFoundException
     */
    public function delete($entity): bool
    {
        switch (true) {
            case is_string($entity) && \Ramsey\Uuid\Uuid::isValid($entity):
            case is_object($entity) && is_a($entity, Uuid::class):
                $entity = $this->service->findOneByUuid((string) $entity);

                break;
        }

        if (is_object($entity) && is_a($entity, Reference::class)) {
            $this->entityManager->remove($entity);
            $this->entityManager->flush();

            return true;
        }

        throw new ReferenceNotFoundException();
    }
}
