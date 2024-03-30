<?php declare(strict_types=1);

namespace App\Domain\Service\Publication;

use App\Domain\AbstractService;
use App\Domain\Models\PublicationCategory;
use App\Domain\Models\UserToken;
use App\Domain\Service\Publication\Exception\AddressAlreadyExistsException;
use App\Domain\Service\Publication\Exception\CategoryNotFoundException;
use App\Domain\Service\Publication\Exception\MissingTitleValueException;
use App\Domain\Service\Publication\Exception\TitleAlreadyExistsException;
use App\Domain\Service\User\Exception\TokenNotFoundException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Ramsey\Uuid\UuidInterface as Uuid;

class CategoryService extends AbstractService
{


    /**
     * @throws MissingTitleValueException
     * @throws TitleAlreadyExistsException
     * @throws AddressAlreadyExistsException
     */
    public function create(array $data = []): PublicationCategory
    {
        $default = [
            'title' => '',
            'address' => '',
            'description' => '',
            'parent_uuid' => null,
            'pagination' => 10,
            'children' => false,
            'public' => true,
            'sort' => [],
            'template' => [],
            'meta' => [],
        ];
        $data = array_merge($default, $data);

        if (!$data['title']) {
            throw new MissingTitleValueException();
        }

        $category = new PublicationCategory;
        $category->fill($data);

        // if address generation is enabled
        if ($this->parameter('common_auto_generate_address', 'no') === 'yes') {
            $category->address = implode('/', array_filter([$category->parent->address ?? '', $category->address ?? $category->title ?? uniqid()], fn ($el) => (bool) $el));
        }

        if (PublicationCategory::firstWhere(['title' => $category->title]) !== null) {
            throw new TitleAlreadyExistsException();
        }

        if (PublicationCategory::firstWhere(['address' => $category->address]) !== null) {
            throw new AddressAlreadyExistsException();
        }

        $category->save();

        return $category;
    }

    /**
     * @throws CategoryNotFoundException
     *
     * @return Collection|PublicationCategory
     */
    public function read(array $data = [])
    {
        $default = [
            'parent_uuid' => '',
            'uuid' => null,
            'title' => null,
            'address' => null,
            'parent' => null,
            'public' => null,
        ];
        $data = array_merge($default, static::$default_read, $data);

        $criteria = [];

        if ($data['parent_uuid'] !== '') {
            $criteria['parent_uuid'] = $data['parent_uuid'];
        }
        if ($data['uuid'] !== null) {
            $criteria['uuid'] = $data['uuid'];
        }
        if ($data['title'] !== null) {
            $criteria['title'] = $data['title'];
        }
        if ($data['address'] !== null) {
            $criteria['address'] = $data['address'];
        }
        if ($data['parent'] !== null) {
            $criteria['parent'] = $data['parent'];
        }
        if ($data['public'] !== null) {
            $criteria['public'] = (bool) $data['public'];
        }

        switch (true) {
            case !is_array($data['uuid']) && $data['uuid'] !== null:
            case !is_array($data['title']) && $data['title'] !== null:
            case !is_array($data['address']) && $data['address'] !== null:
                /** @var PublicationCategory $publicationCategory */
                $publicationCategory = PublicationCategory::firstWhere($criteria);

                return $publicationCategory ?: throw new CategoryNotFoundException();

            default:
                $query = PublicationCategory::where($criteria);
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
     * @param PublicationCategory|string|Uuid $entity
     *
     * @throws TitleAlreadyExistsException
     * @throws AddressAlreadyExistsException
     * @throws CategoryNotFoundException
     */
    public function update($entity, array $data = []): PublicationCategory
    {
        switch (true) {
            case is_string($entity) && \Ramsey\Uuid\Uuid::isValid($entity):
            case is_object($entity) && is_a($entity, Uuid::class):
                $entity = $this->read(['uuid' => $entity]);

                break;
        }

        if (is_object($entity) && is_a($entity, PublicationCategory::class)) {
            $entity->fill($data);

            // if address generation is enabled
            if ($data['address'] && $this->parameter('common_auto_generate_address', 'no') === 'yes') {
                $entity->address = implode('/', array_filter([$entity->parent->address ?? '', $entity->address ?? $entity->title ?? uniqid()], fn ($el) => (bool) $el));
            }

            if ($entity->isDirty('title') || $entity->isDirty('address')) {
                if (($found = PublicationCategory::firstWhere(['title' => $entity->title])) !== null && $found->uuid !== $entity->uuid) {
                    throw new TitleAlreadyExistsException();
                }

                if (($found = PublicationCategory::firstWhere(['address' => $entity->address])) !== null && $found->uuid !== $entity->uuid) {
                    throw new AddressAlreadyExistsException();
                }
            }

            $entity->save();

            return $entity;
        }

        throw new CategoryNotFoundException();
    }

    /**
     * @param PublicationCategory|string|Uuid $entity
     *
     * @throws CategoryNotFoundException
     */
    public function delete($entity): bool
    {
        switch (true) {
            case is_string($entity) && \Ramsey\Uuid\Uuid::isValid($entity):
            case is_object($entity) && is_a($entity, Uuid::class):
                $entity = $this->read(['uuid' => $entity]);

                break;
        }

        if (is_object($entity) && is_a($entity, PublicationCategory::class)) {
            $entity->files()->detach();
            $entity->delete();

            return true;
        }

        throw new CategoryNotFoundException();
    }
}
