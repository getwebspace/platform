<?php declare(strict_types=1);

namespace App\Domain\Service\Catalog;

use App\Domain\AbstractService;
use App\Domain\Entities\Catalog\Measure;
use App\Domain\Repository\Catalog\MeasureRepository;
use App\Domain\Service\Catalog\Exception\MeasureNotFoundException;
use App\Domain\Service\Catalog\Exception\MissingTitleValueException;
use App\Domain\Service\Catalog\Exception\TitleAlreadyExistsException;
use Illuminate\Support\Collection;
use Ramsey\Uuid\Uuid;

class MeasureService extends AbstractService
{
    /**
     * @var MeasureRepository
     */
    protected $service;

    protected function init(): void
    {
        $this->service = $this->entityManager->getRepository(Measure::class);
    }

    /**
     * @param array $data
     *
     * @throws TitleAlreadyExistsException
     * @throws MissingTitleValueException
     *
     * @return Measure
     */
    public function create(array $data = []): Measure
    {
        $default = [
            'title' => '',
            'contraction' => '',
            'value' => 1.00,
        ];
        $data = array_merge($default, $data);

        if ($data['title'] && $this->service->findOneByTitle($data['title']) !== null) {
            throw new TitleAlreadyExistsException();
        }
        if (!$data['title']) {
            throw new MissingTitleValueException();
        }

        $measure = (new Measure)
            ->setTitle($data['title'])
            ->setContraction($data['contraction'])
            ->setValue($data['value']);

        $this->entityManager->persist($measure);
        $this->entityManager->flush();

        return $measure;
    }

    /**
     * @param array $data
     *
     * @throws MeasureNotFoundException
     *
     * @return Collection|Measure
     */
    public function read(array $data = [])
    {
        $default = [
            'uuid' => null,
            'title' => null,
            'contraction' => null,
            'value' => null,
        ];
        $data = array_merge($default, static::$default_read, $data);

        $criteria = [];

        if ($data['uuid'] !== null) {
            $criteria['uuid'] = $data['uuid'];
        }
        if ($data['title'] !== null) {
            $criteria['title'] = $data['title'];
        }
        if ($data['contraction'] !== null) {
            $criteria['contraction'] = $data['contraction'];
        }
        if ($data['value'] !== null) {
            $criteria['value'] = $data['value'];
        }

        try {
            switch (true) {
                case !is_array($data['uuid']) && $data['uuid'] !== null:
                case !is_array($data['title']) && $data['title'] !== null:
                case !is_array($data['contraction']) && $data['contraction'] !== null:
                $measure = $this->service->findOneBy($criteria);

                    if (empty($measure)) {
                        throw new MeasureNotFoundException();
                    }

                    return $measure;

                default:
                    return collect($this->service->findBy($criteria, $data['order'], $data['limit'], $data['offset']));
            }
        } catch (\Doctrine\DBAL\Exception\TableNotFoundException $e) {
            return null;
        }
    }

    /**
     * @param Measure|string|Uuid $entity
     * @param array               $data
     *
     * @throws TitleAlreadyExistsException
     * @throws MeasureNotFoundException
     *
     * @return Measure
     */
    public function update($entity, array $data = []): Measure
    {
        switch (true) {
            case is_string($entity) && Uuid::isValid($entity):
            case is_object($entity) && is_a($entity, Uuid::class):
                $entity = $this->service->findOneByUuid((string) $entity);

                break;
        }

        if (is_object($entity) && is_a($entity, Measure::class)) {
            $default = [
                'title' => null,
                'contraction' => null,
                'value' => null,
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
                if ($data['contraction'] !== null) {
                    $entity->setContraction($data['contraction']);
                }
                if ($data['value'] !== null) {
                    $entity->setValue($data['value']);
                }

                $this->entityManager->flush();
            }

            return $entity;
        }

        throw new MeasureNotFoundException();
    }

    /**
     * @param Measure|string|Uuid $entity
     *
     * @throws MeasureNotFoundException
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

        if (is_object($entity) && is_a($entity, Measure::class)) {
            $this->entityManager->remove($entity);
            $this->entityManager->flush();

            return true;
        }

        throw new MeasureNotFoundException();
    }
}
