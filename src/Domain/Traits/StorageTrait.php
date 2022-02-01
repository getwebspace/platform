<?php declare(strict_types=1);

namespace App\Domain\Traits;

use App\Domain\Service\Parameter\ParameterService;
use Illuminate\Support\Collection;
use Psr\Container\ContainerInterface;

/**
 * @property ContainerInterface[] $container
 */
trait StorageTrait
{
    /**
     * Global storage across actions
     */
    protected static array $storage = [];

    /**
     * @param mixed $value
     *
     * @return mixed
     */
    public static function setStorage(string $key, $value, ?string $namespace = null)
    {
        self::$storage[$namespace][$key] = $value;

        return $value;
    }

    /**
     * @param mixed $default
     *
     * @return null|mixed
     */
    public static function getStorage(string $key, $default = null, ?string $namespace = null)
    {
        return self::$storage[$namespace][$key] ?? $default;
    }
}
