<?php declare(strict_types=1);

namespace App\Domain\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;

class Meta implements CastsAttributes
{
    protected array $default = [
        'title' => '',
        'description' => '',
        'keywords' => '',
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
