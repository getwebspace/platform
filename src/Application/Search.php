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
     * @return string
     */
    public static function getIndexedText(array|string $strings, bool $indexing = false): array
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
                $text = explode(' ', trim($text));

                foreach ($text as $i => &$word) {
                    if (mb_strlen($word) < 2) {
                        unset($text[$i]);
                    }
                }

                $index = array_merge($index, $text);
            }
        }

        return array_unique($index);
    }

    public static function search(string $query, bool $strong = false): array
    {
        $query_words = explode(' ', $query);

        if ($query_words && !$strong) {
            $query_words = array_map(fn ($word) => (mb_strlen($word) > 3 ? $word . '*' : $word), $query_words);
        }

        $query_words = static::getIndexedText($query_words);
        $index = explode(PHP_EOL, file_get_contents(self::CACHE_FILE));

        $results = [];

        // sort words
        usort($query_words, fn ($word) => (str_starts_with($word, '-') || str_starts_with($word, '+')) ? 1 : -1);

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

                if (preg_match("/\\b{$search}\\b/iu", $line)) {
                    ++$wordCount;
                    $wordCount = $wordCount * $mustNotFound;
                }
            }

            if ($wordCount >= $mustFound) {
                foreach (static::permutations($query_words) as $permutation) {
                    if (preg_match("/\\b{$permutation}\\b/iu", $line)) {
                        ++$comboCount;
                    }
                }

                $buf = explode(':', $line);
                $results[$buf[0]][] = ['uuid' => $buf[1], 'order' => $comboCount + $wordCount];
            }
        }

        foreach ($results as $type => $rows) {
            $buf = array_pluck($rows, 'order', 'uuid');
            arsort($buf);
            $results[$type] = array_keys($buf);
        }

        return $results;
    }

    private static function permutations($query_words): array
    {
        $results = [[]];

        foreach ($query_words as $i => $word) {
            foreach ($results as $combination) {
                $results[] = $combination + [$i => $word];
            }
        }

        return array_filter(
            array_map(
                fn ($words) => str_replace('*', '', implode(' ', $words)),
                array_values($results)
            ),
            fn ($word) => (bool) $word
        );
    }
}
