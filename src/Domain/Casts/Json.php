<?php declare(strict_types=1);

namespace App\Domain\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;

class Json implements CastsAttributes
{
    public function get($model, string $key, mixed $value, array $attributes): array
    {
        return json_decode($value, true);
    }

    public function set($model, string $key, mixed $value, array $attributes): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE);
    }
}
