<?php declare(strict_types=1);

namespace App\Application;

class Search
{
    public const CACHE_FILE = CACHE_DIR . '/search_idx';

    public static function isPossible(): bool
    {
        return file_exists(self::CACHE_FILE);
    }

    public static function search(string $query): array
    {
        $query = static::getIndexedText($query);
        $query_words = explode(' ', static::getIndexedText($query));
        $index = explode(PHP_EOL, file_get_contents(self::CACHE_FILE));

        $results = [];

        // sort words
        usort($query_words, fn ($word) => str_start_with($word, ['-', '+']) ? 1 : -1);

        foreach ($index as $line) {
            $wordCount = 0;
            $mustFound = 1;
            $mustNtFound = 1;

            foreach ($query_words as $word) {
                // case '*'
                if (mb_stristr($word, '*') && str_end_with($word, '*')) {
                    $search = str_replace('*', '', $word);
                } else {
                    $search = ' ' . $word . ' ';
                }

                // case '+'
                if (mb_stristr($search, '+')) {
                    $search = str_replace('+', '', $search);

                    if (str_start_with($word, '+')) {
                        ++$mustNtFound;
                    }
                }

                // case '-'
                if (mb_stristr($search, '-')) {
                    $search = str_replace('-', '', $search);

                    if (str_start_with($word, '-')) {
                        $mustNtFound = 0;
                    }
                }

                if (mb_stristr($line, $search)) {
                    ++$wordCount;
                    $wordCount = $wordCount * $mustNtFound;
                }
            }

            if ($wordCount >= $mustFound) {
                $buf = explode(':', $line);
                $results[$buf[0]][] = $buf[1];
            }
        }

        return $results;
    }

    /**
     * @param array|string $strings
     * @param false        $indexing
     */
    public static function getIndexedText($strings, $indexing = false): string
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
                    if (mb_strlen($word) < 3) {
                        unset($text[$i]);
                    }
                }

                $index = array_merge($index, $text);
            }
        }

        $index = array_unique($index);

        return trim(implode(' ', $index));
    }
}
