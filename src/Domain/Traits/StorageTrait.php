<?php declare(strict_types=1);

namespace App\Domain\Traits;

trait StorageTrait
{
    /**
     * storage array
     */
    protected static array $storage = [];

    protected static function setStorage(string $key, mixed $value, ?string $namespace = null): mixed
    {
        self::$storage[$namespace][$key] = $value;

        return $value;
    }

    /**
     * @param mixed       $default
     */
    protected static function getStorage(string $key, mixed $default = null, ?string $namespace = null): mixed
    {
        return self::$storage[$namespace][$key] ?? $default;
    }
}
