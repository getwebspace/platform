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
     * storage array
     */
    protected static array $storage = [];

    /**
     * @param string      $key
     * @param mixed       $value
     * @param string|null $namespace
     *
     * @return mixed
     */
    protected static function setStorage(string $key, mixed $value, ?string $namespace = null)
    {
        self::$storage[$namespace][$key] = $value;

        return $value;
    }

    /**
     * @param string      $key
     * @param mixed       $default
     * @param string|null $namespace
     *
     * @return null|mixed
     */
    protected static function getStorage(string $key, mixed $default = null, ?string $namespace = null)
    {
        return self::$storage[$namespace][$key] ?? $default;
    }
}
