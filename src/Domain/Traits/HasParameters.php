<?php declare(strict_types=1);

namespace App\Domain\Traits;

use App\Domain\Service\Parameter\ParameterService;
use Illuminate\Support\Collection;
use Psr\Container\ContainerInterface;

/**
 * @property ContainerInterface[] $container
 */
trait HasParameters
{
    /**
     * Returns the value of the parameter by the passed key
     * If an array of keys is passed, returns an array of found keys and their values
     */
    protected function parameter(mixed $name = null, mixed $default = null): mixed
    {
        $parameters = $this->from_cache();

        if ($parameters) {
            if ($name === null) {
                return $parameters->mapWithKeys(function ($item) {
                    [$group, $key] = explode('_', $item->name, 2);

                    return [$group . '[' . $key . ']' => $item];
                });
            }
            if (is_string($name)) {
                return ($buf = $parameters->firstWhere('name', $name)) ? $buf->value : $default;
            }

            return $parameters->whereIn('name', $name)->pluck('value', 'name')->all() ?? $default;
        }

        return $default;
    }

    /**
     * For quickly updating parameter values.
     * Use only as a last resort.
     */
    protected function parameter_set(string $name, mixed $value): array
    {
        $parameterService = $this->container->get(ParameterService::class);
        $parameters = $this->from_cache();

        if (($parameter = $parameters->firstWhere('name', $name)) !== null) {
            $parameterService->update($parameter, ['name' => $name, 'value' => $value]);
        } else {
            $parameterService->create(['name' => $name, 'value' => $value]);
        }

        return [$name => $value];
    }

    private function from_cache(): ?Collection
    {
        $parameters = $this->arrayCache->get('params');

        if (!$parameters) {
            $parameters = $this->container->get(ParameterService::class)->read();

            $this->arrayCache->forever('params', $parameters);
        }

        return $parameters;
    }
}
