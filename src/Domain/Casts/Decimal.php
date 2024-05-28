<?php declare(strict_types=1);

namespace App\Domain\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;

class Decimal implements CastsAttributes
{
    public function get($model, string $key, mixed $value, array $attributes): float
    {
        return floatval($value);
    }

    public function set($model, string $key, mixed $value, array $attributes): float
    {
        return floatval($value);
    }
}
