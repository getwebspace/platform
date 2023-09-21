<?php declare(strict_types=1);

use App\Application\i18n;
use App\Domain\AbstractEntity;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

if (!function_exists('array_add')) {
    /**
     * Add an element to an array using "dot" notation if it doesn't exist.
     *
     * @param mixed $value
     */
    function array_add(array $array, string $key, $value): array
    {
        return Arr::add($array, $key, $value);
    }
}

if (!function_exists('array_collapse')) {
    /**
     * Collapse an array of arrays into a single array.
     */
    function array_collapse(array $array): array
    {
        return Arr::collapse($array);
    }
}

if (!function_exists('array_divide')) {
    /**
     * Divide an array into two arrays. One with keys and the other with values.
     */
    function array_divide(array $array): array
    {
        return Arr::divide($array);
    }
}

if (!function_exists('array_dot')) {
    /**
     * Flatten a multi-dimensional associative array with dots.
     *
     * @param string $prepend
     */
    function array_dot(array $array, $prepend = ''): array
    {
        return Arr::dot($array, $prepend);
    }
}

if (!function_exists('array_except')) {
    /**
     * Get all of the given array except for a specified array of keys.
     *
     * @param array|string $keys
     */
    function array_except(array $array, $keys): array
    {
        return Arr::except($array, $keys);
    }
}

if (!function_exists('array_first')) {
    /**
     * Return the first element in an array passing a given truth test.
     *
     * @param mixed $default
     *
     * @return mixed
     */
    function array_first(array $array, callable $callback = null, $default = null)
    {
        return Arr::first($array, $callback, $default);
    }
}

if (!function_exists('array_flatten')) {
    /**
     * Flatten a multi-dimensional array into a single level.
     *
     * @param int $depth
     */
    function array_flatten(array $array, $depth = INF): array
    {
        return Arr::flatten($array, $depth);
    }
}

if (!function_exists('array_forget')) {
    /**
     * Remove one or many array items from a given array using "dot" notation.
     *
     * @param array|string $keys
     */
    function array_forget(array &$array, $keys): void
    {
        Arr::forget($array, $keys);
    }
}

if (!function_exists('array_get')) {
    /**
     * Get an item from an array using "dot" notation.
     *
     * @param array|ArrayAccess $array
     * @param mixed             $default
     *
     * @return mixed
     */
    function array_get($array, string $key, $default = null)
    {
        return Arr::get($array, $key, $default);
    }
}

if (!function_exists('array_has')) {
    /**
     * Check if an item or items exist in an array using "dot" notation.
     *
     * @param array|ArrayAccess $array
     * @param array|string      $keys
     */
    function array_has($array, $keys): bool
    {
        return Arr::has($array, $keys);
    }
}

if (!function_exists('array_last')) {
    /**
     * Return the last element in an array passing a given truth test.
     *
     * @param mixed $default
     *
     * @return mixed
     */
    function array_last(array $array, callable $callback = null, $default = null)
    {
        return Arr::last($array, $callback, $default);
    }
}

if (!function_exists('array_only')) {
    /**
     * Get a subset of the items from the given array.
     *
     * @param array|string $keys
     */
    function array_only(array $array, $keys): array
    {
        return Arr::only($array, $keys);
    }
}

if (!function_exists('array_pluck')) {
    /**
     * Pluck an array of values from an array.
     *
     * @param array|string      $value
     * @param null|array|string $key
     */
    function array_pluck(array $array, $value, $key = null): array
    {
        return Arr::pluck($array, $value, $key);
    }
}

if (!function_exists('array_prepend')) {
    /**
     * Push an item onto the beginning of an array.
     *
     * @param mixed $value
     * @param mixed $key
     */
    function array_prepend(array $array, $value, $key = null): array
    {
        return Arr::prepend($array, $value, $key);
    }
}

if (!function_exists('array_pull')) {
    /**
     * Get a value from the array, and remove it.
     *
     * @param mixed $default
     *
     * @return mixed
     */
    function array_pull(array &$array, string $key, $default = null)
    {
        return Arr::pull($array, $key, $default);
    }
}

if (!function_exists('array_random')) {
    /**
     * Get a random value from an array.
     *
     * @param null|int $num
     *
     * @return mixed
     */
    function array_random(array $array, $num = null)
    {
        return Arr::random($array, $num);
    }
}

if (!function_exists('array_set')) {
    /**
     * Set an array item to a given value using "dot" notation.
     *
     * If no key is given to the method, the entire array will be replaced.
     *
     * @param mixed $value
     */
    function array_set(array &$array, string $key, $value): array
    {
        return Arr::set($array, $key, $value);
    }
}

if (!function_exists('array_sort')) {
    /**
     * Sort the array by the given callback or attribute name.
     *
     * @param null|callable|string $callback
     */
    function array_sort(array $array, $callback = null): array
    {
        return Arr::sort($array, $callback);
    }
}

if (!function_exists('array_sort_desc')) {
    /**
     * Sort the array by the given callback or attribute name.
     *
     * @param null|callable|string $callback
     */
    function array_sort_desc(array $array, $callback = null): array
    {
        return Arr::sortDesc($array, $callback);
    }
}

if (!function_exists('array_sort_recursive')) {
    /**
     * Recursively sort an array by keys and values.
     */
    function array_sort_recursive(array $array): array
    {
        return Arr::sortRecursive($array);
    }
}

if (!function_exists('array_where')) {
    /**
     * Filter the array using the given callback.
     */
    function array_where(array $array, callable $callback): array
    {
        return Arr::where($array, $callback);
    }
}

if (!function_exists('str_escape')) {
    /**
     * Escape strings
     */
    function str_escape(array|string $input): array|string
    {
        if (is_array($input)) {
            foreach ($input as $key => $value) {
                $input[$key] = str_escape($value);
            }
        } else {
            $input = htmlspecialchars($input, ENT_QUOTES);
        }

        return $input;
    }
}

if (!function_exists('str_mask_email')) {
    /**
     * Mask email
     */
    function str_mask_email(string $email): string
    {
        if ($email) {
            $email = explode('@', $email);
            $name = implode('@', array_slice($email, 0, count($email) - 1));
            $len = (int) floor(mb_strlen($name) / 2);

            return mb_substr($name, 0, $len) . str_repeat('*', $len) . '@' . end($email);
        }

        return '';
    }
}

if (!function_exists('str_rem_tags')) {
    /**
     * Mask email
     */
    function str_rem_tags(string $str): string
    {
        $str = preg_replace('/<[^>]*>/', ' ', $str);
        $str = html_entity_decode($str);
        $str = str_replace(["\r", "\0", "\x0B", "\xC2", "\xA0"], '', $str);
        $str = str_replace(["\n", "\t"], ' ', $str);

        return trim(preg_replace('/ {2,}/', ' ', $str));
    }
}

if (!function_exists('blank')) {
    /**
     * Determine if the given value is "blank".
     *
     * @param mixed $value
     */
    function blank($value): bool
    {
        if (is_null($value)) {
            return true;
        }
        if (is_string($value)) {
            return trim($value) === '';
        }
        if (is_numeric($value) || is_bool($value)) {
            return false;
        }
        if ($value instanceof Countable) {
            return count($value) === 0;
        }

        return empty($value);
    }
}

if (!function_exists('__')) {
    /**
     * Locale helper
     */
    function __(array|string|null|Collection $singular, ?string $plural = null, ?int $count = null): array|string|Collection
    {
        return $singular ? i18n::getLocale($singular, $plural, $count) : '';
    }
}

if (!function_exists('from_service_to_array')) {
    /**
     * Helper for read from Service class and return always array
     *
     * @param AbstractEntity|Collection $object
     */
    function from_service_to_array($object): array
    {
        switch (true) {
            case is_a($object, Collection::class):
                return $object->toArray();

            case is_a($object, AbstractEntity::class):
                return [$object];
        }

        return [];
    }
}

if (!function_exists('array_serialize')) {
    /**
     * @param array|Collection $array
     *
     * @return array
     */
    function array_serialize($array)
    {
        // switch type
        switch (true) {
            case is_a($array, Collection::class):
                $array = $array->all();
                break;

            case is_a($array, AbstractEntity::class):
                $array = $array->toArray();
                break;
        }

        // decode data
        foreach ($array as $key => $item) {
            switch (true) {
                case is_array($item):
                case is_a($item, AbstractEntity::class):
                    $array[$key] = array_serialize($item);

                    break;

                case is_a($item, Collection::class):
                    $array[$key] = array_serialize($item->all());

                    break;

                case is_a($item, \Ramsey\Uuid\Uuid::class):
                case is_a($item, \Ramsey\Uuid\Lazy\LazyUuidFromString::class):
                   $array[$key] = (string) $item;

                    break;

                case is_a($item, \Doctrine\ORM\PersistentCollection::class):
                    $array[$key] = array_serialize($item->toArray());

                    break;

                case is_a($item, \DateTime::class):
                    $array[$key] = $item->format(\App\Domain\References\Date::DATETIME);

                    break;
            }
        }

        return $array;
    }
}

if (!function_exists(('datetime'))) {
    function datetime($value = 'now', $timezone = 'UTC'): DateTime
    {
        date_default_timezone_set($timezone);

        return match (true) {
            is_string($value), is_numeric($value) => new DateTime($value),
            is_a($value, DateTime::class) => clone $value,
            default => new DateTime('now'),
        };
    }
}

if (!function_exists('convertPhpToJsMomentFormat')) {
    function convertPhpToJsMomentFormat(string $phpFormat): string
    {
        $replacements = [
            'A' => 'A',      // for the sake of escaping below
            'a' => 'a',      // for the sake of escaping below
            'B' => '',       // Swatch internet time (.beats), no equivalent
            'c' => 'YYYY-MM-DD[T]HH:mm:ssZ', // ISO 8601
            'D' => 'ddd',
            'd' => 'DD',
            'e' => 'zz',     // deprecated since version 1.6.0 of moment.js
            'F' => 'MMMM',
            'G' => 'H',
            'g' => 'h',
            'H' => 'HH',
            'h' => 'hh',
            'I' => '',       // Daylight Saving Time? => moment().isDST();
            'i' => 'mm',
            'j' => 'D',
            'L' => '',       // Leap year? => moment().isLeapYear();
            'l' => 'dddd',
            'M' => 'MMM',
            'm' => 'MM',
            'N' => 'E',
            'n' => 'M',
            'O' => 'ZZ',
            'o' => 'YYYY',
            'P' => 'Z',
            'r' => 'ddd, DD MMM YYYY HH:mm:ss ZZ', // RFC 2822
            'S' => 'o',
            's' => 'ss',
            'T' => 'z',      // deprecated since version 1.6.0 of moment.js
            't' => '',       // days in the month => moment().daysInMonth();
            'U' => 'X',
            'u' => 'SSSSSS', // microseconds
            'v' => 'SSS',    // milliseconds (from PHP 7.0.0)
            'W' => 'W',      // for the sake of escaping below
            'w' => 'e',
            'Y' => 'YYYY',
            'y' => 'YY',
            'Z' => '',       // time zone offset in minutes => moment().zone();
            'z' => 'DDD',
        ];

        // Converts escaped characters.
        foreach ($replacements as $from => $to) {
            $replacements['\\' . $from] = '[' . $from . ']';
        }

        return strtr($phpFormat, $replacements);
    }
}

if (!function_exists('ErrorHandler')) {
    function ErrorHandler(\Psr\Container\ContainerInterface $container)
    {
        /**
         * @var \Psr\Log\LoggerInterface $logger
         */
        $logger = $container->get(\Psr\Log\LoggerInterface::class);

        return function ($code, $str, $file, $line) use ($logger): void {
            $level = match ($code) {
                E_PARSE, E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR => \Monolog\Logger::ERROR,
                E_WARNING, E_USER_WARNING, E_COMPILE_WARNING, E_RECOVERABLE_ERROR => \Monolog\Logger::WARNING,
                E_NOTICE, E_USER_NOTICE, E_STRICT, E_DEPRECATED, E_USER_DEPRECATED => \Monolog\Logger::NOTICE,
                default => \Monolog\Logger::INFO,
            };

            $logger->log($level, "{$str} ({$file}:{$line})");
        };
    }
}

if (!function_exists('ExceptionHandler')) {
    function ExceptionHandler(\Psr\Container\ContainerInterface $container)
    {
        /**
         * @var \Psr\Log\LoggerInterface $logger
         */
        $logger = $container->get(\Psr\Log\LoggerInterface::class);

        return function (Throwable $ex) use ($logger): void {
            $logger->critical("{$ex->getMessage()} ({$ex->getFile()}:{$ex->getLine()})");
        };
    }
}
