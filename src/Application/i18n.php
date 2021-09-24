<?php declare(strict_types=1);

namespace App\Application;

use App\Domain\Exceptions\NullPointException;
use App\Domain\Service\File\Exception\FileNotFoundException;
use Illuminate\Support\Collection;
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
    public static ?string $localeCode = null;

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
     * @throws FileNotFoundException
     *
     * @return array
     */
    protected static function load($priority)
    {
        while ($locale = $priority->extract()) {
            foreach (['php', 'ini'] as $type) {
                $path = LOCALE_DIR . '/' . trim($locale) . '.' . $type;

                if ($path) {
                    static::$localeCode = $locale;

                    switch ($type) {
                        case 'ini':
                            return parse_ini_file($path, true);

                        case 'php':
                            return require_once $path;
                    }
                }
            }
        }

        throw new FileNotFoundException('Could not find a language file');
    }

    /**
     * Get language code from header
     *
     * @param string $header
     * @param string $default
     *
     * @return mixed|string
     */
    public static function getLanguageFromHeader($header, $default = null)
    {
        preg_match_all('~(?<lang>\w+(?:\-\w+|))(?:\;q=(?<q>\d(?:\.\d|))|)[\,]{0,}~i', $header, $list);

        $data = [];
        foreach (array_combine($list['lang'], $list['q']) as $key => $priority) {
            $data[$key] = (float) ($priority ? $priority : 1);
        }
        arsort($data, SORT_NUMERIC);

        return $data ? key($data) : $default;
    }

    protected static ?array $log = null;
    protected static string $log_file = VAR_DIR . '/locale_strings.log';

    protected static function translate_log($string): string
    {
        if (($_ENV['DEBUG'] ?? false) && strlen(trim($string)) > 0) {
            if (static::$log === null) {
                static::$log = [];
                file_put_contents(static::$log_file, '');
            }
            static::$log = explode(PHP_EOL, file_get_contents(static::$log_file));
            static::$log[] = trim($string);
            static::$log = array_unique(static::$log);

            file_put_contents(static::$log_file, implode(PHP_EOL, static::$log));
        }

        return $string;
    }

    /**
     * @param string|array|Collection $string
     *
     * @return string|array
     */
    public static function translate($string)
    {
        switch (true) {
            case is_a($string, Collection::class):
            case is_array($string):
                $buf = [];
                foreach ($string as $key => $item) {
                    if (is_numeric($key) && in_array($item, array_keys(i18n::$locale), true)) {
                        $key = $item;
                    }
                    $buf[$key] = i18n::$locale[$item] ?? static::translate_log($item);
                }

                return $buf;

            case is_string($string):
                return i18n::$locale[$string] ?? static::translate_log($string);
        }

        return $string;
    }
}

