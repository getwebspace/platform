<?php declare(strict_types=1);

namespace App\Application;

use App\Domain\Exceptions\NullPointException;
use SplPriorityQueue;

class i18n
{
    /**
     * Current possible locale options
     */
    public static array $accept = ['ru', 'en'];

    /**
     * Buffer storage of the language file
     */
    public static array $locale = [];

    /**
     * Locale code
     */
    public static string $localeCode = 'ru';

    /**
     * i18n constructor
     */
    public static function init(array $config = []): void
    {
        $default = [
            'accept' => [],
            'locale' => null,
            'default' => null,
            'force' => null,
        ];
        $config = array_merge($default, $config);
        $priority = new SplPriorityQueue();

        if ($config['force'] && in_array($config['force'], static::$accept, true)) {
            $priority->insert($config['force'], 10);
        }

        if ($config['locale'] && in_array($config['locale'], static::$accept, true)) {
            $priority->insert($config['locale'], 5);
        }

        if ($config['default'] && in_array($config['default'], static::$accept, true)) {
            $priority->insert($config['default'], 0);
        }

        if (!count($priority)) {
            throw new NullPointException('Locale list is empty');
        }

        static::$locale = static::load($priority);
    }

    /**
     * Load language file for specified local
     *
     * @param SplPriorityQueue $priority
     *
     * @return array
     */
    protected static function load(SplPriorityQueue $priority): array
    {
        while ($priority->valid()) {
            $locale = $priority->current();

            foreach (['php', 'ini'] as $type) {
                $path = LOCALE_DIR . '/' . trim($locale) . '.' . $type;

                if (file_exists($path)) {
                    static::$localeCode = $locale;

                    switch ($type) {
                        case 'ini':
                            return parse_ini_file($path, true);

                        case 'php':
                            return require_once $path;
                    }
                }
            }

            $priority->next();
        }

        return [];
    }

    /**
     * For add new strings via plugin
     *
     * @param array $strings
     */
    public static function addStrings(array $strings): void
    {
        static::$locale = array_merge(static::$locale, $strings);
    }
    /**
     * Get language code from header
     *
     * @param string      $header
     * @param string|null $default
     *
     * @return int|string|null
     */
    public static function getLanguageFromHeader(string $header, ?string $default = null)
    {
        preg_match_all('~(?<lang>\w+(?:\-\w+|))(?:\;q=(?<q>\d(?:\.\d|))|)[\,]{0,}~i', $header, $list);

        $data = [];
        foreach (array_combine($list['lang'], $list['q']) as $key => $priority) {
            $data[$key] = (float) ($priority ? $priority : 1);
        }
        arsort($data, SORT_NUMERIC);

        return $data ? key($data) : $default;
    }
}
