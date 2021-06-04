<?php declare(strict_types=1);

use App\Domain\AbstractEntity;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

if (!function_exists('array_add')) {
    /**
     * Add an element to an array using "dot" notation if it doesn't exist.
     *
     * @param mixed  $value
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
     * @param mixed         $default
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
     * @param int   $depth
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
     * @param mixed         $default
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
     * @param mixed  $default
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
     * @param mixed  $value
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

if (!function_exists('str_start_with')) {
    /**
     * Determine if a given string starts with a given substring.
     *
     * @param array|string $needles
     */
    function str_start_with(string $haystack, $needles): bool
    {
        foreach ((array) $needles as $needle) {
            if ($needle !== '' && mb_substr($haystack, 0, mb_strlen($needle)) === (string) $needle) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('str_end_with')) {
    /**
     * Determine if a given string ends with a given substring.
     *
     * @param array|string $needles
     */
    function str_end_with(string $haystack, $needles): bool
    {
        foreach ((array) $needles as $needle) {
            if (mb_substr($haystack, -mb_strlen($needle)) === (string) $needle) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('str_translate')) {
    /**
     * Transliterate a russian string
     *
     * @return array|string|string[]
     */
    function str_translate(string $input, bool $back = false)
    {
        $russian = [
            'А', 'Б', 'В', 'Г', 'Д', 'Е', 'Ё', 'Ж', 'З', 'И', 'Й', 'К', 'Л', 'М', 'Н', 'О', 'П', 'Р', 'С', 'Т', 'У',
            'Ф', 'Х', 'Ц', 'Ч', 'Ш', 'Щ', 'Ъ', 'Ы', 'Ь', 'Э', 'Ю', 'Я', 'а', 'б', 'в', 'г', 'д', 'е', 'ё', 'ж', 'з',
            'и', 'й', 'к', 'л', 'м', 'н', 'о', 'п', 'р', 'с', 'т', 'у', 'ф', 'х', 'ц', 'ч', 'ш', 'щ', 'ъ', 'ы', 'ь',
            'э', 'ю', 'я',
        ];
        $latin = [
            'A', 'B', 'V', 'G', 'D', 'E', 'E', 'Zh', 'Z', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'R', 'S', 'T', 'U',
            'F', 'Kh', 'C', 'Ch', 'Sh', 'Sch', '', 'Y', '', 'E', 'Yu', 'Ya', 'a', 'b', 'v', 'g', 'd', 'e', 'e', 'zh',
            'z', 'i', 'j', 'k', 'l', 'm', 'n', 'o', 'p', 'r', 's', 't', 'u', 'f', 'kh', 'c', 'ch', 'sh', 'sch', '',
            'y', '', 'e', 'yu', 'ya',
        ];

        return !$back ? str_replace($russian, $latin, $input) : str_replace($latin, $russian, $input);
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
        foreach ($array as $key => $item) {
            switch (true) {
                case is_array($item):
                case is_a($item, Collection::class):
                    $array[$key] = array_serialize($item);

                    break;

                case is_a($item, \Ramsey\Uuid\Uuid::class):
                    $array[$key] = (string) $item;

                    break;

                case is_a($item, \Doctrine\ORM\PersistentCollection::class):
                    $array[$key] = array_serialize($item->toArray());

                    break;

                case is_a($item, AbstractEntity::class):
                    $array[$key] = $item->toArray();

                    break;

                case is_a($item, \DateTime::class):
                    $array[$key] = $item->format(\App\Domain\References\Date::DATETIME);

                    break;
            }
        }

        return $array;
    }
}

if (!function_exists('sys_self_check_health')) {
    function sys_self_check_health(): array
    {
        $fileAccess = [
            BASE_DIR => 755,
            BIN_DIR => 755,
            CONFIG_DIR => 755,
            LOCLAE_DIR => 755,
            PLUGIN_DIR => 777,
            PUBLIC_DIR => 755,
            UPLOAD_DIR => 777,
            SRC_DIR => 755,
            VIEW_DIR => 755,
            VIEW_ERROR_DIR => 755,
            THEME_DIR => 777,
            VAR_DIR => 777,
            CACHE_DIR => 777,
            LOG_DIR => 777,
            VENDOR_DIR => 755,
        ];

        foreach ($fileAccess as $folder => $value) {
            if (realpath($folder)) {
                $chmod_value = @decoct(@fileperms($folder)) % 1000;

                if ($chmod_value === $value) {
                    $fileAccess[$folder] = true;
                }
            }
        }

        return [
            'php' => version_compare(phpversion(), '7.4', '>='),
            'extensions' => [
                'pdo' => extension_loaded('pdo'),
                // 'pdo_mysql' => extension_loaded('pdo_mysql'),
                // 'pdo_pgsql' => extension_loaded('pdo_pgsql'),
                // 'sqlite3' => extension_loaded('sqlite3'),
                'curl' => extension_loaded('curl'),
                'json' => extension_loaded('json'),
                'mbstring' => extension_loaded('mbstring'),
                'gd' => extension_loaded('gd'),
                'imagick' => extension_loaded('imagick'),
                'xml' => extension_loaded('xml'),
                'yaml' => extension_loaded('yaml'),
                'zip' => extension_loaded('zip'),
            ],
            'folders' => $fileAccess,
        ];
    }
}
