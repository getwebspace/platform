<?php declare(strict_types=1);

namespace App\Domain\Casts\Catalog\Product;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;

class Tags implements CastsAttributes
{
    public function get($model, string $key, mixed $value, array $attributes): array
    {
        return json_decode($value, true);
    }

    public function set($model, string $key, mixed $value, array $attributes): string
    {
        if (blank($value)) {
            $value = [];
        }
        if (is_string($value)) {
            $value = explode(',', $value);
        }

        return json_encode($value, JSON_UNESCAPED_UNICODE);
    }
}
