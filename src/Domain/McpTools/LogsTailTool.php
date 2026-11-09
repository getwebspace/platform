<?php declare(strict_types=1);

namespace App\Domain\McpTools;

use App\Domain\AbstractMcpTool;

class LogsTailTool extends AbstractMcpTool
{
    public const NAME = 'logs_tail';
    public const TITLE = 'Application log';
    public const DESCRIPTION = 'The last records of the application log (errors, warnings, what the tools changed: search for "MCP write"). Filter by the minimum level and by a text, for today or a given day. '
        . 'Tokens are hidden. Needed to find out why something failed. Requires a full access API key.';
    public const PRIVATE = true;

    private const LEVELS = ['DEBUG' => 100, 'INFO' => 200, 'NOTICE' => 250, 'WARNING' => 300, 'ERROR' => 400, 'CRITICAL' => 500, 'ALERT' => 550, 'EMERGENCY' => 600];

    /**
     * How much of the end of the file is looked through: a log can be gigabytes
     */
    private const SCAN_BYTES = 2 * 1024 * 1024;

    private const ENTRY_LENGTH = 700;

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'lines' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 500, 'default' => 100],
                'level' => ['type' => 'string', 'enum' => array_keys(self::LEVELS), 'description' => 'This level and above'],
                'contains' => ['type' => 'string', 'description' => 'Only records with this text, ex: "MCP write"'],
                'date' => ['type' => 'string', 'description' => 'YYYY-MM-DD, the log of that day; by default the newest'],
            ],
        ];
    }

    public function execute(array $args = []): mixed
    {
        $lines = $this->intArgument($args, 'lines', 100, 1, 500);
        $level = $args['level'] ?? null;
        $contains = trim((string) ($args['contains'] ?? ''));

        if ($level !== null && !isset(self::LEVELS[$level])) {
            throw new \InvalidArgumentException('Field level must be one of: ' . implode(', ', array_keys(self::LEVELS)));
        }

        $file = $this->file($args['date'] ?? null);

        if ($file === null) {
            return ['file' => null, 'records' => [], 'note' => 'No log file'];
        }

        $records = array_filter(
            $this->records($file),
            fn (array $record) => ($level === null || $record['weight'] >= self::LEVELS[$level])
                && ($contains === '' || mb_stripos($record['text'], $contains) !== false)
        );

        return [
            'file' => basename($file),
            'records' => array_map(fn (array $record) => $record['text'], array_slice(array_values($records), -$lines)),
        ];
    }

    private function file(mixed $date): ?string
    {
        if (!blank($date)) {
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', trim((string) $date)) !== 1) {
                throw new \InvalidArgumentException('Field date must be YYYY-MM-DD');
            }

            $path = LOG_DIR . '/app-' . trim((string) $date) . '.log';

            return is_file($path) ? $path : null;
        }

        $files = glob(LOG_DIR . '/app-*.log') ?: [];
        rsort($files);

        return $files[0] ?? null;
    }

    /**
     * Records of the end of the file; a record may take several lines (a stack trace), they are kept together
     *
     * @return array<int, array{text: string, weight: int}>
     */
    private function records(string $path): array
    {
        $size = (int) filesize($path);
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            return [];
        }

        fseek($handle, max(0, $size - self::SCAN_BYTES));
        $raw = (string) stream_get_contents($handle);
        fclose($handle);

        $lines = explode("\n", $raw);
        // the first line may be cut in the middle
        if ($size > self::SCAN_BYTES) {
            array_shift($lines);
        }

        $records = [];

        foreach ($lines as $line) {
            if ($line === '') {
                continue;
            }
            if (preg_match('/^\[[^\]]+]\s+\S+\.(?<level>[A-Z]+):/', $line, $match) === 1) {
                $records[] = ['text' => $line, 'weight' => self::LEVELS[$match['level']] ?? 0];

                continue;
            }
            if ($records) {
                $records[array_key_last($records)]['text'] .= ' | ' . trim($line);
            }
        }

        foreach ($records as &$record) {
            $record['text'] = str_truncate($this->hideTokens($record['text']), self::ENTRY_LENGTH);
        }

        return $records;
    }

    /**
     * JWT (API keys, sessions), Bearer values and password-like fields
     */
    private function hideTokens(string $text): string
    {
        $text = preg_replace('/eyJ[A-Za-z0-9_-]{5,}\.[A-Za-z0-9_-]{5,}\.[A-Za-z0-9_-]{5,}/', '[token]', $text) ?? $text;
        $text = preg_replace('/(Bearer\s+)\S+/i', '$1[hidden]', $text) ?? $text;

        return preg_replace('/("?(?:password|passwd|secret|token|apikey|api_key|key)"?\s*[:=]\s*)"[^"]*"/i', '$1"[hidden]"', $text) ?? $text;
    }
}
