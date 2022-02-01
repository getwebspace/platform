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
    /**
     * Returns the value of the parameter by the passed key
     * If an array of keys is passed, returns an array of found keys and their values
     *
     * @param null|string|string[] $key
     * @param mixed                $default
     *
     * @return null|array|Collection|string
     */
    protected function parameter(mixed $key = null, mixed $default = null): mixed
    {
        static $parameters;

        if (!$parameters) {
            \Netpromotion\Profiler\Profiler::start('parameters');
            $parameters = $this->container->get(ParameterService::class)->read();
            \Netpromotion\Profiler\Profiler::finish('%s', $parameters ? $parameters->count() : 'null');
        }

        if ($parameters) {
            if ($key === null) {
                return $parameters->mapWithKeys(function ($item) {
                    [$group, $key] = explode('_', $item->key, 2);

                    return [$group . '[' . $key . ']' => $item];
                });
            }
            if (is_string($key)) {
                return ($buf = $parameters->firstWhere('key', $key)) ? $buf->getValue() : $default;
            }

            return $parameters->whereIn('key', $key)->pluck('value', 'key')->all() ?? $default;
        }

        return $default;
    }
}
