<?php declare(strict_types=1);

namespace App\Domain\Traits;

trait HasStorage
{
    /**
     * storage array
     */
    protected static array $storage = [];

    protected static function hasStorage(string $key, bool $strict = false): bool
    {
        return in_array($key, static::$storage, true);
    }

    protected static function setStorage(string $key, mixed $value, ?string $namespace = null): mixed
    {
        self::$storage[$namespace][$key] = $value;

        return $value;
    }

    protected static function getStorage(string $key, mixed $default = null, ?string $namespace = null): mixed
    {
        return self::$storage[$namespace][$key] ?? $default;
    }
}
