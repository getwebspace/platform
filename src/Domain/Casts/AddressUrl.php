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
        if (blank($value)) {
            $value = $model->title ?? '';
        }

        $value = mb_strtolower($value);
        $value = i18n::getTranslatedText($value);
        $value = trim($value);
        $value = preg_replace(['/[^\w\s\/-]/', '/\s+/'], ['', '-'], $value);

        // for fix duplicate parts; no slash in front, the page is looked up by what comes after the first one
        // (a slash left at the end stays: "category/" is how an address that could not be made shows itself)
        return ltrim(implode('/', array_unique(explode('/', $value))), '/');
    }
}
