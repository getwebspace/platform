<?php declare(strict_types=1);

namespace App\Domain\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;

abstract class Enum implements CastsAttributes
{
    public const LIST = [];

    public function get($model, string $key, mixed $value, array $attributes): string
    {
        return $value;
    }

    public function set($model, string $key, mixed $value, array $attributes): string
    {
        return in_array($value, static::LIST) ? $value : '';
    }
}
