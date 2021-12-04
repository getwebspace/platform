<?php declare(strict_types=1);

namespace App\Domain\Service\Catalog;

use App\Domain\AbstractService;
use App\Domain\Entities\Catalog\Attribute;
use App\Domain\Repository\Catalog\AttributeRepository;
use App\Domain\Service\Catalog\Exception\AddressAlreadyExistsException;
use App\Domain\Service\Catalog\Exception\AttributeNotFoundException;
use App\Domain\Service\Catalog\Exception\MissingTitleValueException;
use App\Domain\Service\Catalog\Exception\TitleAlreadyExistsException;
use Illuminate\Support\Collection;
use Ramsey\Uuid\UuidInterface as Uuid;

class AttributeService extends AbstractService
{
    /**
     * @var AttributeRepository
     */
    protected $service;

    protected function init(): void
    {
        $this->service = $this->entityManager->getRepository(Attribute::class);
    }

    /**
     * @throws TitleAlreadyExistsException
     * @throws MissingTitleValueException
     * @throws AddressAlreadyExistsException
     */
    public function create(array $data = []): Attribute
    {
        $default = [
            'title' => '',
            'address' => '',
            'type' => '',
        ];
        $data = array_merge($default, $data);

        if ($data['title'] && $this->service->findOneByTitle($data['title']) !== null) {
            throw new TitleAlreadyExistsException();
        }
        if (!$data['title']) {
            throw new MissingTitleValueException();
        }
        if ($data['address'] && $this->service->findOneByAddress($data['address']) !== null) {
            throw new AddressAlreadyExistsException();
        }

        $attribute = (new Attribute())
            ->setTitle($data['title'])
            ->setAddress($data['address'])
            ->setType($data['type']);

        $this->entityManager->persist($attribute);
        $this->entityManager->flush();

        return $attribute;
    }

    /**
     * @throws AttributeNotFoundException
     *
     * @return Attribute|Collection
     */
    public function read(array $data = [])
    {
        $default = [
            'uuid' => null,
            'title' => null,
            'address' => null,
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
        if ($data['address'] !== null) {
            $criteria['address'] = $data['address'];
        }
        if ($data['type'] !== null) {
            $criteria['type'] = $data['type'];
        }

        try {
            switch (true) {
                case !is_array($data['uuid']) && $data['uuid'] !== null:
                case !is_array($data['title']) && $data['title'] !== null:
                case !is_array($data['address']) && $data['address'] !== null:
                    $attribute = $this->service->findOneBy($criteria);

                    if (empty($attribute)) {
                        throw new AttributeNotFoundException();
                    }

                    return $attribute;

                default:
                    return collect($this->service->findBy($criteria, $data['order'], $data['limit'], $data['offset']));
            }
        } catch (\Doctrine\DBAL\Exception\TableNotFoundException $e) {
            return null;
        }
    }

    /**
     * @param Attribute|string|Uuid $entity
     *
     * @throws TitleAlreadyExistsException
     * @throws AttributeNotFoundException
     * @throws AddressAlreadyExistsException
     */
    public function update($entity, array $data = []): Attribute
    {
        switch (true) {
            case is_string($entity) && \Ramsey\Uuid\Uuid::isValid($entity):
            case is_object($entity) && is_a($entity, Uuid::class):
                $entity = $this->service->findOneByUuid((string) $entity);

                break;
        }

        if (is_object($entity) && is_a($entity, Attribute::class)) {
            $default = [
                'title' => null,
                'type' => null,
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
                if ($data['address'] !== null) {
                    $found = $this->service->findOneByAddress($data['address']);

                    if ($found === null || $found === $entity) {
                        $entity->setAddress($data['address']);
                    } else {
                        throw new AddressAlreadyExistsException();
                    }
                }
                if ($data['type'] !== null) {
                    $entity->setType($data['type']);
                }

                $this->entityManager->flush();
            }

            return $entity;
        }

        throw new AttributeNotFoundException();
    }

    /**
     * @param Attribute|string|Uuid $entity
     *
     * @throws AttributeNotFoundException
     */
    public function delete($entity): bool
    {
        switch (true) {
            case is_string($entity) && \Ramsey\Uuid\Uuid::isValid($entity):
            case is_object($entity) && is_a($entity, Uuid::class):
                $entity = $this->service->findOneByUuid((string) $entity);

                break;
        }

        if (is_object($entity) && is_a($entity, Attribute::class)) {
            $this->entityManager->remove($entity);
            $this->entityManager->flush();

            return true;
        }

        throw new AttributeNotFoundException();
    }
}
