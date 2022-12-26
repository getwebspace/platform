<?php declare(strict_types=1);

namespace App\Application;

class i18n
{
    /**
     * Current possible locale options
     */
    public static array $accept = ['en-US'];

    /**
     * Buffer storage of the language file
     */
    public static array $locale = [];

    /**
     * Locale code
     */
    public static string $localeCode = '';

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
}
