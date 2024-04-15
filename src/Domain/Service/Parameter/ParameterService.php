<?php declare(strict_types=1);

namespace App\Domain\Service\Parameter;

use App\Domain\AbstractService;
use App\Domain\Models\Parameter;
use App\Domain\Service\Parameter\Exception\ParameterAlreadyExistsException;
use App\Domain\Service\Parameter\Exception\ParameterNotFoundException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\UuidInterface as Uuid;

class ParameterService extends AbstractService
{


    /**
     * @throws ParameterAlreadyExistsException
     */
    public function create(array $data = []): Parameter
    {
        $parameter = new Parameter;
        $parameter->fill($data);

        if (Parameter::firstWhere(['name' => $parameter->name]) !== null) {
            throw new ParameterAlreadyExistsException();
        }

        $parameter->save();

        return $parameter;
    }

    public function read(array $data = [], mixed $fallback = null): Collection|Parameter|null
    {
        $default = [
            'name' => null,
        ];
        $data = array_merge($default, static::$default_read, $data);

        $criteria = [];

        if ($data['name'] !== null) {
            $criteria['name'] = $data['name'];
        }

        switch (true) {
            case !is_array($data['name']) && $data['name'] !== null:
                /** @var Parameter $parameter */
                $parameter = Parameter::firstWhere($criteria);

                if (!$parameter) {
                    $parameter = new Parameter;
                    $parameter->name = $data['name'];
                    $parameter->value = $fallback;
                }

                return $parameter;

            default:
                $query = Parameter::query();
                /** @var Builder $query */

                foreach ($criteria as $key => $value) {
                    if (is_array($value)) {
                        $query->orWhereIn($key, $value);
                    } else {
                        $query->orWhere($key, $value);
                    }
                }
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
     * @param Parameter|string $entity
     */
    public function update($entity, array $data = []): Parameter
    {
        switch (true) {
            case is_string($entity):
                $entity = $this->read(['name' => $entity]);

                break;
        }

        if (is_object($entity) && is_a($entity, Parameter::class)) {
            $entity->fill($data);
            $entity->save();

            return $entity;
        }

        throw new ParameterNotFoundException();
    }

    /**
     * @param Parameter|string $entity
     *
     * @throws ParameterNotFoundException
     */
    public function delete($entity): bool
    {
        switch (true) {
            case is_string($entity):
                $entity = $this->read(['name' => $entity]);

                break;
        }

        if (is_object($entity) && is_a($entity, Parameter::class)) {
            $entity->delete();

            return true;
        }

        throw new ParameterNotFoundException();
    }
}
