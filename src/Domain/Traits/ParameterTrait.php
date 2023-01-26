<?php declare(strict_types=1);

namespace App\Domain\Traits;

use App\Domain\Service\Parameter\ParameterService;
use Psr\Container\ContainerInterface;
use Illuminate\Support\Collection;

/**
 * @property ContainerInterface[] $container
 */
trait ParameterTrait
{
    private static ?Collection $parameters = null;

    /**
     * Returns the value of the parameter by the passed key
     * If an array of keys is passed, returns an array of found keys and their values
     *
     * @throws \Psr\Container\NotFoundExceptionInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    protected function parameter(mixed $key = null, mixed $default = null): mixed
    {
        if (!static::$parameters) {
            static::$parameters = $this->container->get(ParameterService::class)->read();
        }

        if (static::$parameters) {
            if ($key === null) {
                return static::$parameters->mapWithKeys(function ($item) {
                    [$group, $key] = explode('_', $item->key, 2);

                    return [$group . '[' . $key . ']' => $item];
                });
            }
            if (is_string($key)) {
                return ($buf = static::$parameters->firstWhere('key', $key)) ? $buf->getValue() : $default;
            }

            return static::$parameters->whereIn('key', $key)->pluck('value', 'key')->all() ?? $default;
        }

        return $default;
    }

    /**
     * For quickly updating parameter values.
     * Use only as a last resort.
     *
     * @param $key
     * @param $value
     *
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     * @return array
     */
    protected function parameter_set($key, $value): array
    {
        $parameterService = $this->container->get(ParameterService::class);

        if (($parameter = static::$parameters->firstWhere('key', $key)) !== null) {
            $parameterService->update($parameter, ['key' => $key, 'value' => $value]);
        } else {
            $parameterService->create(['key' => $key, 'value' => $value]);
        }

        return [$key => $value];
    }
}
