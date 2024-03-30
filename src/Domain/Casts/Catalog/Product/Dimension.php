<?php declare(strict_types=1);

namespace App\Domain\Casts\Catalog\Product;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;

class Dimension implements CastsAttributes
{
    protected array $default = [
        'length' => 0.0,
        'width' => 0.0,
        'height' => 0.0,
        'weight' => 0.0,
        'length_class' => '',
        'weight_class' => '',
    ];

    public function get($model, string $key, mixed $value, array $attributes): array
    {
        $value = json_decode($value, true);

        return array_merge($this->default, $value);
    }

    public function set($model, string $key, mixed $value, array $attributes): string
    {
        return json_encode(array_merge($this->default, $value), JSON_UNESCAPED_UNICODE);
    }
}
