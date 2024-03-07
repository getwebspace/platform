<?php declare(strict_types=1);

namespace App\Domain\Casts\User;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;

class Messenger implements CastsAttributes
{
    protected array $default = [
        'skype' => '',
        'telegram' => '',
        'whatsapp' => '',
        'viber' => '',
        'facebook' => '',
        'instagram' => '',
        'signal' => '',
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
