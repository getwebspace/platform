<?php declare(strict_types=1);

namespace App\Domain\McpTools\Concerns;

use Carbon\Carbon;

/**
 * Period of a report: date_from / date_to as YYYY-MM-DD (whole days, both ends included)
 */
trait ParsesPeriod
{
    /**
     * @return array{0: Carbon, 1: Carbon} start of the first day, end of the last day
     */
    protected function period(array $args, int $defaultDays = 30, int $maxDays = 366): array
    {
        $to = $this->day($args, 'date_to') ?? datetime();
        $from = $this->day($args, 'date_from') ?? $to->copy()->subDays($defaultDays - 1);

        $from = $from->startOfDay();
        $to = $to->endOfDay();

        if ($from->greaterThan($to)) {
            throw new \InvalidArgumentException('date_from is later than date_to');
        }
        if ($from->diffInDays($to) > $maxDays) {
            throw new \InvalidArgumentException("The period is too long, at most {$maxDays} days");
        }

        return [$from, $to];
    }

    private function day(array $args, string $key): ?Carbon
    {
        if (blank($args[$key] ?? null)) {
            return null;
        }

        $value = trim((string) $args[$key]);

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1 || strtotime($value) === false) {
            throw new \InvalidArgumentException("Field {$key} must be YYYY-MM-DD");
        }

        return datetime($value);
    }

    protected function periodSchema(int $defaultDays = 30): array
    {
        return [
            'date_from' => ['type' => 'string', 'description' => "YYYY-MM-DD, first day; by default {$defaultDays} days up to date_to"],
            'date_to' => ['type' => 'string', 'description' => 'YYYY-MM-DD, last day included; by default today'],
        ];
    }
}
