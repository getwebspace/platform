<?php declare(strict_types=1);

namespace App\Application\Actions\Cup;

use App\Domain\AbstractAction;

class LogPageAction extends AbstractAction
{
    /**
     * How many trailing lines of every log file to show.
     */
    private const TAIL_LINES = 1000;

    /**
     * Monolog level name => severity bucket the template uses for colouring.
     */
    private const SEVERITY = [
        'DEBUG' => 'debug',
        'INFO' => 'info',
        'NOTICE' => 'info',
        'WARNING' => 'warning',
        'ERROR' => 'error',
        'CRITICAL' => 'error',
        'ALERT' => 'error',
        'EMERGENCY' => 'error',
    ];

    protected function action(): \Slim\Psr7\Response
    {
        $files = [];

        foreach (glob(LOG_DIR . '/app-*.log') ?: [] as $path) {
            $files[basename($path)] = $this->readTail($path, self::TAIL_LINES);
        }

        // newest file first
        krsort($files);

        return $this->respondWithTemplate('cup/logs.twig', [
            'files' => $files,
        ]);
    }

    /**
     * Read the last $limit lines of a log file and classify each one by severity,
     * so the template only has to render — no parsing on the Twig side.
     *
     * @return array{lines: list<array{severity: string, text: string}>, errors: int, warnings: int}
     */
    private function readTail(string $path, int $limit): array
    {
        $raw = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        $raw = array_slice($raw, -$limit);

        $lines = [];
        $errors = 0;
        $warnings = 0;

        foreach ($raw as $line) {
            $level = preg_match('/^\[[^\]]+]\s+\S+\.(?<level>[A-Z]+):/', $line, $match) ? $match['level'] : '';
            $severity = self::SEVERITY[$level] ?? 'plain';

            if ($severity === 'error') {
                $errors++;
            } elseif ($severity === 'warning') {
                $warnings++;
            }

            $lines[] = [
                'severity' => $severity,
                'text' => rtrim($line),
            ];
        }

        return [
            'lines' => $lines,
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }
}
