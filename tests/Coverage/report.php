<?php declare(strict_types=1);

/**
 * Merges phpunit's clover report (code run by the tests themselves) with the
 * lines php-fpm executed while serving the API tests, and prints the coverage
 * by directory
 *
 * php report.php <clover.xml> <fpm-dir> [--min=<percent>] [--list=<regex>]
 *
 * --list prints, for the files whose path under src/ matches the expression, the lines nobody executed
 */
[, $cloverFile, $fpmDir] = $argv + [null, null, null];

if (!$cloverFile || !$fpmDir || !is_file($cloverFile)) {
    fwrite(STDERR, "usage: report.php <clover.xml> <fpm-dir> [--min=<percent>]\n");
    exit(2);
}

$min = null;
$list = null;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--min=')) {
        $min = (float) substr($arg, 6);
    }
    if (str_starts_with($arg, '--list=')) {
        $list = substr($arg, 7);
    }
}

$clover = simplexml_load_file($cloverFile);

$executable = [];
$byTests = [];
foreach ($clover->xpath('//file') as $file) {
    $name = (string) $file['name'];

    foreach ($file->line as $line) {
        if ((string) $line['type'] !== 'stmt') {
            continue;
        }

        $number = (int) $line['num'];
        $executable[$name][$number] = true;

        if ((int) $line['count'] > 0) {
            $byTests[$name][$number] = true;
        }
    }
}

$byFpm = [];
foreach (glob(rtrim($fpmDir, '/') . '/*.json') ?: [] as $json) {
    foreach (json_decode((string) file_get_contents($json), true) ?: [] as $name => $lines) {
        foreach ($lines as $number) {
            $byFpm[$name][$number] = true;
        }
    }
}

/**
 * A multi-line array literal is one operation for pcov (reported on one of its lines), but every line of it is a statement
 * for phpunit, so an array that did run would leave its other lines "uncovered". The lines of a literal that has
 * no code inside it (a closure, a match) count as run together with the one pcov saw
 *
 * @return array<int, array{0: int, 1: int}>
 */
function literalSpans(string $file): array
{
    $tokens = array_values(array_filter(
        \PhpToken::tokenize((string) file_get_contents($file)),
        static fn (\PhpToken $t): bool => !$t->is([T_WHITESPACE, T_COMMENT, T_DOC_COMMENT])
    ));

    $spans = [];
    $stack = [];
    foreach ($tokens as $i => $token) {
        $previous = $tokens[$i - 1] ?? null;

        if ($token->text === '[' && !($previous && ($previous->is([T_VARIABLE, T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED]) || in_array($previous->text, [']', ')', '}'], true)))) {
            $stack[] = [$token->line, false];
        } elseif ($token->is(T_ARRAY) && ($tokens[$i + 1]->text ?? '') === '(') {
            $stack[] = [$token->line, false];
        } elseif ($token->text === '[' || $token->text === '(') {
            $stack[] = [null, false];
        } elseif ($token->is([T_FUNCTION, T_FN, T_MATCH])) {
            foreach ($stack as $k => $_) {
                $stack[$k][1] = true;
            }
        } elseif (($token->text === ']' || $token->text === ')') && $stack) {
            [$start, $code] = array_pop($stack);

            if ($start !== null && !$code && $token->line > $start) {
                $spans[] = [$start, $token->line];
            }
        }
    }

    // a call chain written on several lines is one statement as well
    $statement = null;
    $line = null;
    foreach ($tokens as $i => $token) {
        if ($token->line !== $line) {
            $line = $token->line;

            if ($token->is([T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR])) {
                $statement ??= [$tokens[$i - 1]->line ?? $line, false];
            } elseif ($statement !== null) {
                if (!$statement[1]) {
                    $spans[] = [$statement[0], $statement[2] ?? $line];
                }
                $statement = null;
            }
        }

        if ($statement !== null) {
            $statement[2] = $token->line;
            if ($token->is([T_FUNCTION, T_FN, T_MATCH]) || $token->text === '{') {
                $statement[1] = true;
            }
        }
    }

    return $spans;
}

foreach ([&$byTests, &$byFpm] as &$source) {
    foreach ($executable as $name => $lines) {
        if (!isset($source[$name]) || !is_file($name)) {
            continue;
        }

        foreach (literalSpans($name) as [$start, $end]) {
            $hit = false;
            for ($n = $start; $n <= $end; ++$n) {
                $hit = $hit || isset($source[$name][$n]);
            }

            for ($n = $start; $hit && $n <= $end; ++$n) {
                if (isset($lines[$n])) {
                    $source[$name][$n] = true;
                }
            }
        }
    }
}
unset($source);

if ($list !== null) {
    foreach ($executable as $name => $lines) {
        $relative = str_replace('/var/container/src/', '', $name);
        if (preg_match('#' . $list . '#', $relative) !== 1) {
            continue;
        }

        $missing = array_keys(array_filter($lines, static fn ($_, int $number): bool => !isset($byTests[$name][$number]) && !isset($byFpm[$name][$number]), ARRAY_FILTER_USE_BOTH));
        sort($missing);

        // 3,4,5,9 => 3-5,9
        $ranges = [];
        foreach ($missing as $number) {
            if ($ranges && $number === end($ranges)[1] + 1) {
                $ranges[array_key_last($ranges)][1] = $number;
            } else {
                $ranges[] = [$number, $number];
            }
        }

        printf("%-45s %3d of %-3d  %s\n", $relative, count($missing), count($lines), implode(',', array_map(static fn (array $r): string => $r[0] === $r[1] ? (string) $r[0] : $r[0] . '-' . $r[1], $ranges)));
    }

    echo "\n";
}

$groups = [];
foreach ($executable as $name => $lines) {
    $parts = explode('/', str_replace('/var/container/src/', '', $name));
    array_pop($parts);
    $group = implode('/', array_slice($parts, 0, 3)) ?: '(root)';

    $groups[$group] ??= ['lines' => 0, 'tests' => 0, 'all' => 0];

    foreach (array_keys($lines) as $number) {
        $tests = isset($byTests[$name][$number]);

        ++$groups[$group]['lines'];
        $groups[$group]['tests'] += $tests ? 1 : 0;
        $groups[$group]['all'] += ($tests || isset($byFpm[$name][$number])) ? 1 : 0;
    }
}

uasort($groups, static fn (array $a, array $b): int => $b['lines'] <=> $a['lines']);

printf("%7s %7s %7s  %s\n", 'covered', 'phpunit', 'lines', 'directory');
$total = ['lines' => 0, 'tests' => 0, 'all' => 0];
foreach ($groups as $group => $data) {
    foreach ($total as $key => $_) {
        $total[$key] += $data[$key];
    }

    printf(
        "%6.1f%% %6.1f%% %7d  %s\n",
        100 * $data['all'] / $data['lines'],
        100 * $data['tests'] / $data['lines'],
        $data['lines'],
        $group
    );
}

$percent = $total['lines'] ? 100 * $total['all'] / $total['lines'] : 0.0;
printf(
    "\nTOTAL %.1f%% (%d of %d lines); %.1f%% by phpunit alone, the rest by php-fpm\n",
    $percent,
    $total['all'],
    $total['lines'],
    $total['lines'] ? 100 * $total['tests'] / $total['lines'] : 0.0
);

if ($min !== null && $percent < $min) {
    fwrite(STDERR, sprintf("coverage %.1f%% is below the required %.1f%%\n", $percent, $min));
    exit(1);
}
