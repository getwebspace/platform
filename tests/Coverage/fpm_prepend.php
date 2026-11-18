<?php declare(strict_types=1);

/**
 * auto_prepend_file for php-fpm while coverage is collected
 *
 * The API tests talk to nginx + php-fpm over HTTP, so pcov inside phpunit never
 * sees the code they run. Every request records the lines it executed into its
 * own JSON file; tests/Coverage/report.php merges them with phpunit's own data
 *
 * Started by bin/coverage.sh, never loaded otherwise
 */
if (PHP_SAPI === 'fpm-fcgi' && function_exists('pcov\start')) {
    \pcov\start();

    register_shutdown_function(static function (): void {
        \pcov\stop();

        // pcov\collect() without an explicit file list crashes the worker (SIGSEGV)
        $files = array_values(array_filter(
            get_included_files(),
            static fn (string $file): bool => str_starts_with($file, '/var/container/src/')
        ));

        $executed = [];
        foreach (\pcov\collect(\pcov\inclusive, $files) as $file => $lines) {
            foreach ($lines as $line => $hits) {
                if ($hits > 0) {
                    $executed[$file][] = $line;
                }
            }
        }

        if ($executed) {
            file_put_contents('/tmp/coverage-fpm/' . uniqid('', true) . '.json', json_encode($executed));
        }
    });
}
