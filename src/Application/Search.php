<?php declare(strict_types=1);

namespace App\Application;

class Search
{
    public const CACHE_FILE = CACHE_DIR . '/search_idx';

    /**
     * @return bool
     */
    public static function isPossible(): bool
    {
        return file_exists(self::CACHE_FILE);
    }

    /**
     * @param string $query
     *
     * @return array
     */
    public static function search(string $query): array
    {
        $query = static::getIndexedText($query);
        $query_words = explode(' ', static::getIndexedText($query));
        $index = explode(PHP_EOL, file_get_contents(self::CACHE_FILE));

        $results = [];
        foreach ($index as $line) {
            $wordcount = 0;
            $mustfound = 1;
            $mustntfound = 1;

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
                    $mustntfound++;
                }

                // case '-'
                if (mb_stristr($search, '-')) {
                    $search = str_replace('-', '', $search);
                    $mustntfound = 0;
                }

                if (mb_stristr($line, $search)) {
                    $wordcount++;
                    $wordcount = $wordcount * $mustntfound;
                }
            }

            if ($wordcount >= $mustfound) {
                $buf = explode(':', $line);
                $results[$buf[0]][] = $buf[1];
            }
        }

        return $results;
    }

    /**
     * @param array|string $strings
     * @param false        $indexing
     *
     * @return string
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

                $text = str_replace(['a', 'e', 'i', 'o', 'u', 'y'], '', $text);
                $text = str_replace(['а', 'е', 'и', 'о', 'у', 'ы', 'э', 'ю', 'я'], '', $text);
                $text = explode(' ', $text);

                foreach ($text as $i => &$word) {
                    if (mb_strlen($word) < 3) {
                        unset($text[$i]);
                    }
                }

                $text = array_unique($text);
                $text = implode(' ', $text);
                $text = trim($text);

                $index[] = rtrim($text);
            }
        }

        return implode(' | ', $index);
    }
}
