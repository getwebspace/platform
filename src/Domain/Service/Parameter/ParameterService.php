<?php declare(strict_types=1);

namespace App\Domain\Service\Parameter;

use App\Domain\AbstractService;
use App\Domain\Models\Parameter;
use App\Domain\Service\Parameter\Exception\ParameterAlreadyExistsException;
use App\Domain\Service\Parameter\Exception\ParameterNotFoundException;
use Illuminate\Support\Collection;

class ParameterService extends AbstractService
{
    protected static array $search_columns = ['name'];

    /**
     * @throws ParameterAlreadyExistsException
     */
    public function create(array $data = []): Parameter
    {
        $parameter = new Parameter();
        $parameter->fill($data);
        $this->normalize($parameter);

        if (Parameter::firstWhere(['name' => $parameter->name]) !== null) {
            throw new ParameterAlreadyExistsException();
        }

        $parameter->save();

        return $parameter;
    }

    /**
     * The site address is used to build links and robots.txt, so it must carry a scheme
     * and no trailing slash: "example.com/" becomes "https://example.com"
     */
    private function normalize(Parameter $parameter): void
    {
        if ($parameter->name === 'common_homepage' && is_string($parameter->value) && trim($parameter->value) !== '') {
            $value = trim($parameter->value);

            if (!preg_match('#^https?://#i', $value)) {
                $value = 'https://' . ltrim($value, '/');
            }

            $parameter->value = rtrim($value, '/');
        }
    }

    public function read(array $data = [], mixed $fallback = null): null|Collection|Parameter
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
                /** @var null|Parameter $parameter */
                $parameter = $this->buildQuery(Parameter::query(), $criteria, $data)->first();

                if (!$parameter) {
                    $parameter = new Parameter();
                    $parameter->name = $data['name'];
                    $parameter->value = $fallback;
                }

                return $parameter;

            default:
                return $this->buildQuery(Parameter::query(), $criteria, $data)->get();
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
            $this->normalize($entity);
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
