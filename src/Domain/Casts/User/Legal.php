<?php declare(strict_types=1);

namespace App\Domain\Casts\User;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;

class Legal implements CastsAttributes
{
    protected array $default = [
        'code' => '',
        'number' => '',
    ];

    public function get($model, string $key, mixed $value, array $attributes): array
    {
        $value = json_decode((string) $value, true);

        return array_merge($this->default, is_array($value) ? $value : []);
    }

    public function set($model, string $key, mixed $value, array $attributes): string
    {
        if ($value) {
            return json_encode(array_merge($this->default, is_array($value) ? $value : []), JSON_UNESCAPED_UNICODE);
        }

        return '{}';
    }
}
