<?php declare(strict_types=1);

namespace App\Domain\Casts;

use App\Application\i18n;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;

class AddressUrl implements CastsAttributes
{
    public function get($model, string $key, mixed $value, array $attributes): string
    {
        return $value;
    }

    public function set($model, string $key, mixed $value, array $attributes): string
    {
        if (!$value) {
            $value = $model->title ?? '';
        }

        $value = mb_strtolower($value);
        $value = i18n::getTranslatedText($value);
        $value = trim($value);
        $value = preg_replace(['/[^\x20-\x7E]/', '/\s+/'], ['', '-'], $value);
        $value = implode('/', array_unique(explode('/', $value))); // for fix duplicate parts

        return $value;
    }
}
