<?php declare(strict_types=1);

namespace App\Domain\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;

class Uuid implements CastsAttributes
{
    public const VALID_REGEX = '/\A[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\z/ms';

    public function get($model, string $key, mixed $value, array $attributes): ?string
    {
        return $value;
    }

    public function set($model, string $key, mixed $value, array $attributes): ?string
    {
        if (!blank($value) && preg_match(self::VALID_REGEX, $value) === 1) {
            return $value;
        }

        return null;
    }
}
