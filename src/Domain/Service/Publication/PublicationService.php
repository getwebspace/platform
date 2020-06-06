<?php declare(strict_types=1);

namespace App\Domain\Service\Publication;

use Alksily\Entity\Collection;
use App\Domain\AbstractService;
use App\Domain\Entities\Publication;
use App\Domain\Repository\PublicationRepository;
use App\Domain\Service\Publication\Exception\AddressAlreadyExistsException;
use App\Domain\Service\Publication\Exception\MissingTitleValueException;
use App\Domain\Service\Publication\Exception\PublicationNotFoundException;
use App\Domain\Service\Publication\Exception\TitleAlreadyExistsException;
use Doctrine\ORM\EntityManager;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;

class PublicationService extends AbstractService
{
    /**
     * @var PublicationRepository
     */
    protected $service;

    protected function init()
    {
        $this->service = $this->entityManager->getRepository(Publication::class);
    }

    /**
     * @param array $data
     *
     * @throws TitleAlreadyExistsException
     * @throws MissingTitleValueException
     * @throws AddressAlreadyExistsException
     *
     * @return Publication
     */
    public function create(array $data = []): Publication
    {
        $default = [
            'title' => '',
            'address' => '',
            'category' => Uuid::NIL,
            'date' => 'now',
            'content' => [
                'short' => '',
                'full' => '',
            ],
            'meta' => [
                'title' => '',
                'description' => '',
                'keywords' => '',
            ],
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

        $publication = (new Publication)
            ->setAddress($data['address'])
            ->setTitle($data['title'])
            ->setCategory($data['category'])
            ->setDate($data['date'])
            ->setContent($data['content'])
            ->setMeta($data['meta']);

        $this->entityManager->persist($publication);
        $this->entityManager->flush();

        return $publication;
    }

    /**
     * @param array $data
     *
     * @throws PublicationNotFoundException
     *
     * @return Collection|Publication
     */
    public function read(array $data = [])
    {
        $default = [
            'uuid' => '',
            'address' => '',
            'title' => '',
            'category' => '',
        ];
        $data = array_merge($default, static::$default_read, $data);

        if ($data['uuid'] || $data['address'] || $data['title']) {
            switch (true) {
                case $data['uuid']:
                    $publication = $this->service->findOneByUuid((string) $data['uuid']);

                    break;

                case $data['address']:
                    $publication = $this->service->findOneByAddress($data['address']);

                    break;

                case $data['title']:
                    $publication = $this->service->findOneByTitle($data['title']);

                    break;
            }

            if (empty($publication)) {
                throw new PublicationNotFoundException();
            }

            return $publication;
        }

        $criteria = [];

        if ($data['category'] !== '') {
            $criteria['category'] = $data['category'];
        }

        return collect($this->service->findBy($criteria, $data['order'], $data['limit'], $data['offset']));
    }

    /**
     * @param Publication|string|Uuid $entity
     * @param array                   $data
     *
     * @throws PublicationNotFoundException
     * @throws TitleAlreadyExistsException
     * @throws AddressAlreadyExistsException
     *
     * @return Publication
     */
    public function update($entity, array $data = []): Publication
    {
        switch (true) {
            case is_string($entity) && Uuid::isValid($entity):
            case is_object($entity) && is_a($entity, Uuid::class):
                $entity = $this->service->findOneByUuid((string) $entity);

                break;
        }

        if (is_object($entity) && is_a($entity, Publication::class)) {
            $default = [
                'title' => '',
                'address' => '',
                'category' => '',
                'date' => '',
                'content' => [],
                'meta' => [],
            ];
            $data = array_merge($default, $data);

            if ($data !== $default) {
                if ($data['title']) {
                    $found = $this->service->findOneByTitle($data['title']);

                    if ($found === null || $found === $entity) {
                        $entity->setTitle($data['title']);
                    } else {
                        throw new TitleAlreadyExistsException();
                    }
                }
                if ($data['address']) {
                    $found = $this->service->findOneByAddress($data['address']);

                    if ($found === null || $found === $entity) {
                        $entity->setAddress($data['address']);
                    } else {
                        throw new AddressAlreadyExistsException();
                    }
                }
                if ($data['category']) {
                    $entity->setCategory($data['category']);
                }
                if ($data['date']) {
                    $entity->setDate($data['date']);
                }
                if ($data['content']) {
                    $entity->setContent($data['content']);
                }
                if ($data['meta']) {
                    $entity->setMeta($data['meta']);
                }

                $this->entityManager->flush();
            }

            return $entity;
        }

        throw new PublicationNotFoundException();
    }

    /**
     * @param Publication|string|Uuid $entity
     *
     * @throws PublicationNotFoundException
     *
     * @return bool
     */
    public function delete($entity): bool
    {
        if (
            (is_string($entity) && Uuid::isValid($entity)) ||
            (is_object($entity) && is_a($entity, Uuid::class))
        ) {
            $entity = $this->service->findOneByUuid((string) $entity);
        }

        if (is_object($entity) && is_a($entity, Publication::class)) {
            $this->entityManager->remove($entity);
            $this->entityManager->flush();

            return true;
        }

        throw new PublicationNotFoundException();
    }
}
