<?php declare(strict_types=1);

namespace App\Domain\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;

class Boolean implements CastsAttributes
{
    public function get($model, string $key, mixed $value, array $attributes): bool
    {
        return $value;
    }

    public function set($model, string $key, mixed $value, array $attributes): bool
    {
        if (is_string($value) || is_int($value) || is_bool($value)) {
            switch (true) {
                case $value === true || in_array(mb_strtolower(trim((string) $value)), ['1', 'on', 'true', 't', 'yes', 'y'], true):
                    return true;

                case $value === false || in_array(mb_strtolower(trim((string) $value)), ['0', 'off', 'false', 'f', 'no', 'n'], true):
                    return false;
            }
        }

        return false;
    }
}
