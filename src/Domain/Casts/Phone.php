<?php declare(strict_types=1);

namespace App\Domain\Casts;

use App\Application\i18n;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;

class Phone implements CastsAttributes
{
    public function get($model, string $key, mixed $value, array $attributes): string
    {
        return $value;
    }

    public function set($model, string $key, mixed $value, array $attributes): string
    {
        $value = str_replace([' ', '-', '.', '(', ')'], '', $value);

        if (isset($_ENV['SIMPLE_PHONE_CHECK']) && $_ENV['SIMPLE_PHONE_CHECK']) {
            return $value;
        }

        $pattern = '/\(?\+[0-9]{1,3}\)? ?-?[0-9]{1,3} ?-?[0-9]{3,5} ?-?[0-9]{4}( ?-?[0-9]{3})? ?(\w{1,10}\s?\d{1,6})?/';

        if (preg_match($pattern, $value)) {
            return $value;
        }

        return '';
    }
}
