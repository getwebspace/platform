<?php declare(strict_types=1);

namespace App\Domain\Plugin;

use App\Application\i18n;
use App\Domain\AbstractPlugin;

abstract class AbstractLanguagePlugin extends AbstractPlugin
{
    /**
     * Add new line in current locale table
     */
    public function addLocale(string $code, array $strings = []): void
    {
        i18n::addLocale($code, $strings);
    }

    /**
     * Add new array of lines in current locale table
     */
    public function addLocaleFromFile(string $code, string $path): void
    {
        i18n::addLocaleFromFile($code, $path);
    }

    /**
     * Add locale editor words
     */
    public function addLocaleEditor(string $code, array $translate): void
    {
        i18n::addLocaleEditor($code, $translate);
    }

    /**
     * Add translate letters
     */
    public function addLocaleTranslateLetters(string $code, array $original, array $latin): void
    {
        i18n::addLocaleTranslateLetters($code, $original, $latin);
    }
}
