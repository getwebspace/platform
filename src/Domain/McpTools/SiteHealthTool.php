<?php declare(strict_types=1);

namespace App\Domain\McpTools;

use App\Domain\AbstractMcpTool;
use App\Domain\Casts\Task\Status as TaskStatus;
use App\Domain\Models\Task;
use Illuminate\Database\Connection;

class SiteHealthTool extends AbstractMcpTool
{
    public const NAME = 'site_health';
    public const TITLE = 'Site health';
    public const DESCRIPTION = 'Technical state of the site: version, PHP, free disk space, size of the database and of the logs, background tasks (queue, running, failed) and the errors of the last log file. '
        . 'A quick answer to "is everything fine with the site". Requires a full access API key.';
    public const PRIVATE = true;

    public function execute(array $args = []): mixed
    {
        $db = $this->container->get(Connection::class);
        $driver = $db->getDriverName();

        $logs = glob(LOG_DIR . '/app-*.log') ?: [];
        rsort($logs);
        $errors = 0;

        if ($logs) {
            // only the end of the newest file: it can be huge
            $tail = (string) @file_get_contents($logs[0], false, null, max(0, (int) filesize($logs[0]) - 1024 * 1024));
            $errors = preg_match_all('/^\[[^\]]+]\s+\S+\.(ERROR|CRITICAL|ALERT|EMERGENCY):/m', $tail);
        }

        return [
            'version' => ['branch' => $_ENV['COMMIT_BRANCH'] ?? null, 'commit' => !empty($_ENV['COMMIT_SHA']) ? mb_substr($_ENV['COMMIT_SHA'], 0, 7) : null],
            'php' => PHP_VERSION,
            'time' => datetime()->format('Y-m-d H:i:s T'),
            'database' => array_filter(['driver' => $driver, 'size' => $driver === 'sqlite' ? $this->size((string) $db->getDatabaseName()) : null]),
            'disk' => ['free' => $this->size(null, (float) disk_free_space(VAR_DIR)), 'total' => $this->size(null, (float) disk_total_space(VAR_DIR))],
            'logs' => ['files' => count($logs), 'size' => $this->size(null, (float) array_sum(array_map('filesize', $logs))), 'errors_in_the_last_file' => $errors],
            'tasks' => [
                'queued' => Task::where('status', TaskStatus::QUEUE)->count(),
                'running' => Task::where('status', TaskStatus::WORK)->count(),
                'failed' => Task::where('status', TaskStatus::FAIL)->count(),
            ],
        ];
    }

    private function size(?string $path, ?float $bytes = null): string
    {
        $bytes ??= (float) @filesize((string) $path);

        foreach (['B', 'KB', 'MB', 'GB', 'TB'] as $unit) {
            if ($bytes < 1024 || $unit === 'TB') {
                return round($bytes, 1) . ' ' . $unit;
            }

            $bytes /= 1024;
        }

        return '';
    }
}
