<?php declare(strict_types=1);

namespace App\Application;

class Search
{
    public const CACHE_FILE = CACHE_DIR . '/search.idx';

    public static function isPossible(): bool
    {
        return file_exists(self::CACHE_FILE);
    }

    /**
     * Prepare text
     *
     * @param array|string $strings
     * @param bool         $indexing
     *
     * @return string
     */
    public static function getIndexedText(array|string $strings, bool $indexing = false): string
    {
        $index = [];

        if (!is_array($strings)) {
            $strings = [$strings];
        }

        foreach ($strings as $text) {
            if ($text) {
                $text = mb_strtolower($text);
                $text = strip_tags($text);
                $text = str_replace(['&nbsp', "\n", "\r", "\t"], ' ', $text);
                $text = str_replace(['.', ',', '&', '!', '?', ':', ';', '(', ')', '"', '\''], '', $text);

                if ($indexing) {
                    $text = str_replace(['-', '+'], '', $text);
                }

                $text = str_replace(['a', 'e', 'i', 'o', 'u', 'y'], '', $text); // english
                $text = str_replace(['а', 'е', 'ё', 'и', 'о', 'у', 'ы', 'э', 'ю', 'я', 'ь', 'ъ'], '', $text); // russian
                $text = str_replace(['а', 'е', 'є', 'и', 'і', 'ї', 'о', 'у', 'ю', 'я'], '', $text); // ukrainian
                $text = explode(' ', $text);

                foreach ($text as $i => &$word) {
                    if (mb_strlen($word) < 2) {
                        unset($text[$i]);
                    }
                }

                $index = array_merge($index, $text);
            }
        }

        $index = array_unique($index);

        return trim(implode(' ', $index));
    }

    public static function search(string $query, bool $strong = false): array
    {
        if ($query && !$strong) {
            $query = array_map(fn($word) => (mb_strlen($word) > 3 ? $word . '*' : $word), explode(' ', $query));
        }
        $query_words = explode(' ', static::getIndexedText($query));
        $index = explode(PHP_EOL, file_get_contents(self::CACHE_FILE));

        $results = [];

        // sort words
        usort($query_words, fn($word) => (str_starts_with($word, '-') || str_starts_with($word, '+')) ? 1 : -1);

        foreach ($index as $line) {
            $wordCount = 0;
            $comboCount = 0;
            $mustFound = 1;
            $mustNotFound = 1;

            foreach ($query_words as $word) {
                // case '*'
                if (mb_stristr($word, '*')) {
                    $search = str_replace('*', '', $word);
                } else {
                    $search = ' ' . $word . ' ';
                }

                // case '+'
                if (mb_stristr($search, '+')) {
                    $search = str_replace('+', '', $search);

                    if (str_starts_with($word, '+')) {
                        ++$mustNotFound;
                    }
                }

                // case '-'
                if (mb_stristr($search, '-')) {
                    $search = str_replace('-', '', $search);

                    if (str_starts_with($word, '-')) {
                        $mustNotFound = 0;
                    }
                }

                if (mb_stristr($line, $search)) {
                    ++$wordCount;
                    $wordCount = $wordCount * $mustNotFound;
                }
            }

            foreach (static::permutations($query_words) as $permutation) {
                if (mb_stristr($line, $permutation)) {
                    ++$comboCount;
                }
            }

            if ($strong && !mb_stristr($line, str_replace('*', '', implode(' ', $query_words)))) {
                $wordCount = 0;
            }

            if ($wordCount > $mustFound) {
                $buf = explode(':', $line);
                $results[$buf[0]][] = ['uuid' => $buf[1], 'order' => $comboCount + $wordCount];
            }
        }

        return $results;
    }

    private static function permutations($query_words): array
    {
        $results = [[]];

        foreach ($query_words as $k => $element) {
            foreach ($results as $combination) {
                $results[] = $combination + [$k => $element];
            }
        }

        return array_map(fn($words) => str_replace('*', '', implode(' ', $words)), array_values(array_filter($results, fn($el) => count($el) >= 2)));
    }
}
