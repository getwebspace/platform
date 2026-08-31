<?php declare(strict_types=1);

namespace App\Domain\Service\Review;

use App\Domain\AbstractService;
use App\Domain\Casts\Review\EntityType as ReviewEntityType;
use App\Domain\Casts\Review\Status as ReviewStatus;
use App\Domain\Casts\Review\Type as ReviewType;
use App\Domain\Models\Review;
use App\Domain\Service\Review\Exception\MissingEntityValueException;
use App\Domain\Service\Review\Exception\MissingMessageValueException;
use App\Domain\Service\Review\Exception\ReviewNotFoundException;
use App\Domain\Service\Review\Exception\WrongEntityTypeValueException;
use App\Domain\Service\Review\Exception\WrongTypeValueException;
use Illuminate\Support\Collection;
use Ramsey\Uuid\UuidInterface as Uuid;

class ReviewService extends AbstractService
{
    protected static array $search_columns = ['message'];

    protected static array $eager = ['user'];

    /**
     * @throws MissingMessageValueException
     * @throws MissingEntityValueException
     * @throws WrongTypeValueException
     * @throws WrongEntityTypeValueException
     */
    public function create(array $data = []): Review
    {
        $default = [
            'type' => ReviewType::REVIEW,
            'entity_type' => ReviewEntityType::PUBLICATION,
        ];
        $data = array_merge($default, $data);

        if (!in_array($data['type'], ReviewType::LIST, true)) {
            throw new WrongTypeValueException();
        }
        if (!in_array($data['entity_type'], ReviewEntityType::LIST, true)) {
            throw new WrongEntityTypeValueException();
        }

        $review = new Review();
        $review->fill($data);

        if (!$review->message) {
            throw new MissingMessageValueException();
        }
        if (!$review->entity_uuid) {
            throw new MissingEntityValueException();
        }

        $review->save();

        return $review;
    }

    /**
     * @throws ReviewNotFoundException
     *
     * @return Collection|Review
     */
    public function read(array $data = [])
    {
        $default = [
            'uuid' => null,
            'parent_uuid' => null,
            'user_uuid' => null,
            'type' => null,
            'entity_type' => null,
            'entity_uuid' => null,
            'status' => null,
        ];
        $data = array_merge($default, static::$default_read, $data);

        $criteria = [];

        if ($data['uuid'] !== null) {
            $criteria['uuid'] = $data['uuid'];
        }
        if ($data['user_uuid'] !== null) {
            $criteria['user_uuid'] = $data['user_uuid'];
        }
        if ($data['entity_uuid'] !== null) {
            $criteria['entity_uuid'] = $data['entity_uuid'];
        }
        if ($data['type'] !== null && ($types = $this->limitByList($data['type'], ReviewType::LIST))) {
            $criteria['type'] = $types;
        }
        if ($data['entity_type'] !== null && ($entityTypes = $this->limitByList($data['entity_type'], ReviewEntityType::LIST))) {
            $criteria['entity_type'] = $entityTypes;
        }
        if ($data['status'] !== null && ($statuses = $this->limitByList($data['status'], ReviewStatus::LIST))) {
            $criteria['status'] = $statuses;
        }

        $query = Review::query();

        // `parent_uuid === false` narrows to top-level entries only, a uuid (or
        // list of them) narrows to replies of those, null leaves it untouched
        if ($data['parent_uuid'] === false) {
            $query->whereNull('parent_uuid');
        } elseif ($data['parent_uuid'] !== null) {
            $criteria['parent_uuid'] = $data['parent_uuid'];
        }

        switch (true) {
            case !is_array($data['uuid']) && $data['uuid'] !== null:
                /** @var Review $review */
                $review = $this->buildQuery($query, $criteria, $data)->first();

                return $review ?: throw new ReviewNotFoundException();

            default:
                return $this->buildQuery($query, $criteria, $data)->get();
        }
    }

    public function count(array $criteria = []): int
    {
        return Review::where($criteria)->count();
    }

    /**
     * @param Review|string|Uuid $entity
     *
     * @throws ReviewNotFoundException
     */
    public function update($entity, array $data = []): Review
    {
        switch (true) {
            case is_string($entity) && \Ramsey\Uuid\Uuid::isValid($entity):
            case is_object($entity) && is_a($entity, Uuid::class):
                $entity = $this->read(['uuid' => $entity]);

                break;
        }

        if (is_object($entity) && is_a($entity, Review::class)) {
            $entity->fill($data);
            $entity->save();

            return $entity;
        }

        throw new ReviewNotFoundException();
    }

    /**
     * @param Review|string|Uuid $entity
     *
     * @throws ReviewNotFoundException
     */
    public function delete($entity): bool
    {
        switch (true) {
            case is_string($entity) && \Ramsey\Uuid\Uuid::isValid($entity):
            case is_object($entity) && is_a($entity, Uuid::class):
                $entity = $this->read(['uuid' => $entity]);

                break;
        }

        if (is_object($entity) && is_a($entity, Review::class)) {
            // drop replies too, they make no sense without their parent
            Review::query()->where('parent_uuid', $entity->uuid)->delete();
            $entity->delete();

            return true;
        }

        throw new ReviewNotFoundException();
    }
}
