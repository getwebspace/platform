<?php declare(strict_types=1);

namespace App\Domain\Service\File;

use App\Domain\AbstractService;
use App\Domain\Entities\FileRelation;
use App\Domain\Repository\FileRelationRepository;
use App\Domain\Service\File\Exception\RelationNotFoundException;
use Illuminate\Support\Collection;
use Ramsey\Uuid\UuidInterface as Uuid;

class FileRelationService extends AbstractService
{
    /**
     * @var FileRelationRepository
     */
    protected $service;

    protected function init(): void
    {
        $this->service = $this->entityManager->getRepository(FileRelation::class);
    }

    public function create(array $data = []): ?FileRelation
    {
        $default = [
            'entity' => '',
            'file' => '',
            'comment' => '',
            'order' => 1,
        ];
        $data = array_merge($default, $data);

        $fileRelation = null;

        // type discriminator
        switch (true) {
            case is_a($data['entity'], \App\Domain\Entities\Catalog\Product::class):
                $fileRelation = new \App\Domain\Entities\File\CatalogProductFileRelation();

                break;

            case is_a($data['entity'], \App\Domain\Entities\Catalog\Category::class):
                $fileRelation = new \App\Domain\Entities\File\CatalogCategoryFileRelation();

                break;

            case is_a($data['entity'], \App\Domain\Entities\Form\Data::class):
                $fileRelation = new \App\Domain\Entities\File\FormDataFileRelation();

                break;

            case is_a($data['entity'], \App\Domain\Entities\Page::class):
                $fileRelation = new \App\Domain\Entities\File\PageFileRelation();

                break;

            case is_a($data['entity'], \App\Domain\Entities\Publication::class):
                $fileRelation = new \App\Domain\Entities\File\PublicationFileRelation();

                break;

            case is_a($data['entity'], \App\Domain\Entities\Publication\Category::class):
                $fileRelation = new \App\Domain\Entities\File\PublicationCategoryFileRelation();

                break;

            case is_a($data['entity'], \App\Domain\Entities\User::class):
                $fileRelation = new \App\Domain\Entities\File\UserFileRelation();

                break;
        }

        if ($fileRelation) {
            $fileRelation = $fileRelation
                ->setEntity($data['entity'])
                ->setFile($data['file'])
                ->setComment($data['comment'])
                ->setOrder((int) $data['order']);

            $this->entityManager->persist($fileRelation);
            $this->entityManager->flush();

            return $fileRelation;
        }

        return null;
    }

    /**
     * @throws RelationNotFoundException
     *
     * @return Collection|FileRelation
     */
    public function read(array $data = [])
    {
        $default = [
            'uuid' => null,
            'entity_uuid' => null,
            'file_uuid' => null,
        ];
        $data = array_merge($default, static::$default_read, $data);

        $criteria = [];

        if ($data['uuid'] !== null) {
            $criteria['uuid'] = $data['uuid'];
        }
        if ($data['entity_uuid'] !== null) {
            $criteria['entity_uuid'] = $data['entity_uuid'];
        }
        if ($data['file_uuid'] !== null) {
            $criteria['file_uuid'] = $data['file_uuid'];
        }

        try {
            switch (true) {
                case !is_array($data['uuid']) && $data['uuid'] !== null:
                    $relation = $this->service->findOneBy($criteria);

                    if (empty($relation)) {
                        throw new RelationNotFoundException();
                    }

                    return $relation;

                default:
                    return collect($this->service->findBy($criteria, $data['order'], $data['limit'], $data['offset']));
            }
        } catch (\Doctrine\DBAL\Exception\TableNotFoundException $e) {
            return null;
        }
    }

    /**
     * @param FileRelation|string|Uuid $entity
     *
     * @throws RelationNotFoundException
     */
    public function update($entity, array $data = []): FileRelation
    {
        switch (true) {
            case is_string($entity) && \Ramsey\Uuid\Uuid::isValid($entity):
            case is_object($entity) && is_a($entity, Uuid::class):
                $entity = $this->service->findOneByUuid((string) $entity);

                break;
        }

        if (is_object($entity) && is_a($entity, FileRelation::class)) {
            $default = [
                'entity' => null,
                'file' => null,
                'comment' => null,
                'order' => null,
            ];
            $data = array_merge($default, $data);

            if ($data !== $default) {
                if ($data['entity'] !== null) {
                    $entity->setEntity($data['entity']);
                }
                if ($data['file'] !== null) {
                    $entity->setFile($data['file']);
                }
                if ($data['comment'] !== null) {
                    $entity->setComment($data['comment']);
                }
                if ($data['order'] !== null) {
                    $entity->setOrder((int) $data['order']);
                }

                $this->entityManager->flush();
            }

            return $entity;
        }

        throw new RelationNotFoundException();
    }

    /**
     * @param FileRelation|string|Uuid $entity
     *
     * @throws RelationNotFoundException
     */
    public function delete($entity): bool
    {
        switch (true) {
            case is_string($entity) && \Ramsey\Uuid\Uuid::isValid($entity):
            case is_object($entity) && is_a($entity, Uuid::class):
                $entity = $this->service->findOneByUuid((string) $entity);

                break;
        }

        if (is_object($entity) && is_a($entity, FileRelation::class)) {
            $this->entityManager->remove($entity);
            $this->entityManager->flush();

            return true;
        }

        throw new RelationNotFoundException();
    }
}
