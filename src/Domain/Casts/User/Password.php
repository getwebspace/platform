<?php declare(strict_types=1);

namespace App\Domain\Casts\User;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;

class Password implements CastsAttributes
{
    public function get($model, string $key, mixed $value, array $attributes): string
    {
        return $value;
    }

    public function set($model, string $key, mixed $value, array $attributes): string
    {
        if ($value) {
            return password_hash($value, PASSWORD_ARGON2ID);
        }

        return $model->password;
    }
}
