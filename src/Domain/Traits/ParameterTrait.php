<?php declare(strict_types=1);

namespace App\Domain\Traits;

use App\Domain\Service\Parameter\ParameterService;
use Illuminate\Support\Collection;
use Psr\Container\ContainerInterface;

/**
 * @property ContainerInterface[] $container
 */
trait ParameterTrait
{
    private static ?Collection $parameters = null;

    /**
     * Returns the value of the parameter by the passed key
     * If an array of keys is passed, returns an array of found keys and their values
     */
    protected function parameter(mixed $name = null, mixed $default = null): mixed
    {
        if (!static::$parameters) {
            static::$parameters = $this->container->get(ParameterService::class)->read();
        }

        if (static::$parameters) {
            if ($name === null) {
                return static::$parameters->mapWithKeys(function ($item) {
                    [$group, $key] = explode('_', $item->name, 2);

                    return [$group . '[' . $key . ']' => $item];
                });
            }
            if (is_string($name)) {
                return ($buf = static::$parameters->firstWhere('name', $name)) ? $buf->value : $default;
            }

            return static::$parameters->whereIn('name', $name)->pluck('value', 'key')->all() ?? $default;
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

        if (($parameter = static::$parameters->firstWhere('name', $name)) !== null) {
            $parameterService->update($parameter, ['name' => $name, 'value' => $value]);
        } else {
            $parameterService->create(['name' => $name, 'value' => $value]);
        }

        return [$name => $value];
    }
}
