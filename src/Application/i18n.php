<?php declare(strict_types=1);

namespace App\Application;

use Illuminate\Support\Collection;

class i18n
{
    /**
     * Current possible locale options
     */
    public static array $accept = ['en-US'];

    /**
     * Locale code
     */
    public static string $localeCode = '';

    /**
     * Buffer storage of the language file
     */
    public static array $locale = [];

    /**
     * Buffer storage of letters
     */
    public static array $letters = [];

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
        $priority = new \SplPriorityQueue();

        if ($config['force'] && in_array($config['force'], static::$accept, true)) {
            $priority->insert($config['force'], 10);
        }

        if ($config['locale'] && in_array($config['locale'], static::$accept, true)) {
            $priority->insert($config['locale'], 5);
        }

        if ($config['default'] && in_array($config['default'], static::$accept, true)) {
            $priority->insert($config['default'], 0);
        }

        // add default locale
        static::addLocaleFromFile('en-US', SRC_LOCALE_DIR . '/en-US.php');

        // load
        static::$locale = static::load($priority);
    }

    /**
     * Add new lang-code
     */
    public static function addLocale(string $code, array $strings): void
    {
        static::$accept[] = $code;
        static::$accept = array_unique(static::$accept);
        static::$locale[$code] = array_merge(static::$locale[$code] ?? [], $strings);
    }

    /**
     * Add new lang-code
     */
    public static function addLocaleFromFile(string $code, string $path): void
    {
        $strings = [];

        if (file_exists($path)) {
            $info = pathinfo($path);

            switch ($info['extension']) {
                case 'json':
                    $strings = json_decode(file_get_contents($path), true);

                    break;

                case 'ini':
                    $strings = parse_ini_file($path, true);

                    break;

                case 'php':
                    $strings = require_once $path;

                    break;
            }
        }

        static::addLocale($code, $strings);
    }

    /**
     * Add letters for translate
     */
    public static function addLocaleTranslateLetters(string $code, array $original, array $latin): void
    {
        if (in_array($code, static::$accept, true)) {
            static::$letters[$code] = ['from' => $original, 'to' => $latin];
        }
    }

    /**
     * Load language file for specified local
     */
    protected static function load(\SplPriorityQueue $priority): array
    {
        while ($priority->valid()) {
            $lang = $priority->current();

            if (isset(static::$locale[$lang])) {
                static::$localeCode = $lang;

                return static::$locale[$lang];
            }

            $priority->next();
        }

        return [];
    }

    /**
     * Get language code from header
     *
     * @return null|int|string
     */
    public static function getLanguageFromHeader(string $header, ?string $default = null)
    {
        preg_match_all('~(?<lang>\w+(?:\-\w+|))(?:\;q=(?<q>\d(?:\.\d|))|)[\,]{0,}~i', $header, $list);

        $data = [];
        foreach (array_combine($list['lang'], $list['q']) as $key => $priority) {
            $data[$key] = (float) ($priority ?: 1);
        }
        arsort($data, SORT_NUMERIC);

        return $data ? key($data) : $default;
    }

    public static function getLocale(array|string|Collection $singular, ?string $plural = null, ?int $count = null)
    {
        $string = $plural && $count > 1 ? $plural : $singular;

        switch (true) {
            case is_a($string, Collection::class):
            case is_array($string):
                $buf = [];
                foreach ($string as $key => $item) {
                    if (is_numeric($key) && in_array($item, array_keys(self::$locale), true)) {
                        $key = $item;
                    }
                    $buf[$key] = self::$locale[$item] ?? $item;
                }

                return $buf;

            case is_string($string):
                return self::$locale[$string] ?? $string;
        }

        return $string;
    }

    public static function getTranslatedText(string $str): string
    {
        if (!empty(static::$letters[static::$localeCode])) {
            return str_replace(
                static::$letters[static::$localeCode]['from'],
                static::$letters[static::$localeCode]['to'],
                $str
            );
        }

        return $str;
    }
}
