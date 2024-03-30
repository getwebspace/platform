<?php declare(strict_types=1);

namespace App\Domain\Service\Publication;

use App\Domain\AbstractService;
use App\Domain\Models\Publication;
use App\Domain\Models\PublicationCategory;
use App\Domain\Service\Publication\Exception\AddressAlreadyExistsException;
use App\Domain\Service\Publication\Exception\MissingCategoryValueException;
use App\Domain\Service\Publication\Exception\MissingTitleValueException;
use App\Domain\Service\Publication\Exception\PublicationNotFoundException;
use App\Domain\Service\Publication\Exception\TitleAlreadyExistsException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Ramsey\Uuid\UuidInterface as Uuid;

class PublicationService extends AbstractService
{


    /**
     * @throws TitleAlreadyExistsException
     * @throws MissingTitleValueException
     * @throws AddressAlreadyExistsException
     */
    public function create(array $data = []): Publication
    {
        $default = [
            'title' => '',
            'address' => '',
            'category_uuid' => null,
            'user_uuid' => '',
            'date' => 'now',
            'content' => [],
            'meta' => [],
            'external_id' => '',
        ];
        $data = array_merge($default, $data);

        if (!$data['title']) {
            throw new MissingTitleValueException();
        }

        if (!$data['category_uuid']) {
            throw new MissingCategoryValueException();
        }

        $publication = new Publication;
        $publication->fill($data);

        // if address generation is enabled
        if ($this->parameter('common_auto_generate_address', 'no') === 'yes') {
            $publication->address = implode('/', array_filter([$publication->category->address ?? '', $publication->address ?? $publication->title ?? uniqid()], fn ($el) => (bool) $el));
        }

        if (Publication::firstWhere(['title' => $publication->title]) !== null) {
            throw new TitleAlreadyExistsException();
        }

        if (Publication::firstWhere(['address' => $publication->address]) !== null) {
            throw new AddressAlreadyExistsException();
        }

        $publication->save();

        return $publication;
    }

    /**
     * @throws PublicationNotFoundException
     *
     * @return Collection|Publication
     */
    public function read(array $data = [])
    {
        $default = [
            'uuid' => null,
            'address' => null,
            'title' => null,
            'category_uuid' => null,
            'user_uuid' => null,
            'external_id' => null,
        ];
        $data = array_merge($default, static::$default_read, $data);

        $criteria = [];

        if ($data['uuid'] !== null) {
            $criteria['uuid'] = $data['uuid'];
        }
        if ($data['address'] !== null) {
            $criteria['address'] = $data['address'];
        }
        if ($data['title'] !== null) {
            $criteria['title'] = $data['title'];
        }
        if ($data['category_uuid'] !== null) {
            $criteria['category_uuid'] = $data['category_uuid'];
        }
        if ($data['user_uuid'] !== null) {
            $criteria['user_uuid'] = $data['user_uuid'];
        }
        if ($data['external_id'] !== null) {
            $criteria['external_id'] = $data['external_id'];
        }

        switch (true) {
            case !is_array($data['uuid']) && $data['uuid'] !== null:
            case !is_array($data['title']) && $data['title'] !== null:
            case !is_array($data['address']) && $data['address'] !== null:
            case !is_array($data['external_id']) && $data['external_id'] !== null:
                /** @var Publication $publication */
                $publication = Publication::firstWhere($criteria);

                return $publication ?: throw new PublicationNotFoundException();

            default:
                $query = Publication::where($criteria);
                /** @var Builder $query */

                foreach ($data['order'] as $column => $direction) {
                    $query = $query->orderBy($column, $direction);
                }
                if ($data['limit']) {
                    $query = $query->limit($data['limit']);
                }
                if ($data['offset']) {
                    $query = $query->offset($data['offset']);
                }

                return $query->get();
        }
    }

    /**
     * @param Publication|string|Uuid $entity
     *
     * @throws PublicationNotFoundException
     * @throws TitleAlreadyExistsException
     * @throws AddressAlreadyExistsException
     */
    public function update($entity, array $data = []): Publication
    {
        switch (true) {
            case is_string($entity) && \Ramsey\Uuid\Uuid::isValid($entity):
            case is_object($entity) && is_a($entity, Uuid::class):
                $entity = $this->read(['uuid' => $entity]);

                break;
        }

        if (is_object($entity) && is_a($entity, Publication::class)) {
            $entity->fill($data);

            // if address generation is enabled
            if ($entity->isDirty('address') && $this->parameter('common_auto_generate_address', 'no') === 'yes') {
                $entity->address = implode('/', array_filter([$entity->category->address ?? '', $entity->address ?? $entity->title ?? uniqid()], fn ($el) => (bool) $el));
            }

            if ($entity->isDirty('title')) {
                $found = Publication::firstWhere(['title' => $entity->title]);

                if ($found && $found->uuid !== $entity->uuid) {
                    throw new TitleAlreadyExistsException();
                }
            }

            if ($entity->isDirty('address')) {
                $found = Publication::firstWhere(['address' => $entity->address]);

                if ($found && $found->uuid !== $entity->uuid) {
                    throw new AddressAlreadyExistsException();
                }
            }

            $entity->save();

            return $entity;
        }

        throw new PublicationNotFoundException();
    }

    /**
     * @param Publication|string|Uuid $entity
     *
     * @throws PublicationNotFoundException
     */
    public function delete($entity): bool
    {
        switch (true) {
            case is_string($entity) && \Ramsey\Uuid\Uuid::isValid($entity):
            case is_object($entity) && is_a($entity, Uuid::class):
                $entity = $this->read(['uuid' => $entity]);

                break;
        }

        if (is_object($entity) && is_a($entity, Publication::class)) {
            $entity->files()->detach();
            $entity->delete();

            return true;
        }

        throw new PublicationNotFoundException();
    }
}
