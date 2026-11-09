<?php declare(strict_types=1);

namespace App\Domain\McpPrompts;

use App\Domain\AbstractMcpPrompt;

class SalesAnalysisPrompt extends AbstractMcpPrompt
{
    public const NAME = 'sales_analysis';
    public const TITLE = 'Sales analysis';
    public const DESCRIPTION = 'Sales of a period against the previous one: revenue, orders, average check, best products and categories.';
    public const TOOLS = ['catalog_sales_report', 'catalog_top_products'];

    public function getArguments(): array
    {
        return [
            ['name' => 'days', 'description' => 'Length of the period in days, default 30', 'required' => false],
        ];
    }

    public function render(array $args = []): string
    {
        $days = max(1, min(180, (int) ($args['days'] ?? 30)));
        $to = datetime()->format('Y-m-d');
        $from = datetime()->subDays($days - 1)->format('Y-m-d');
        $previousTo = datetime()->subDays($days)->format('Y-m-d');
        $previousFrom = datetime()->subDays($days * 2 - 1)->format('Y-m-d');

        return "Analyse the sales of the last {$days} days ({$from} - {$to}) against the {$days} days before ({$previousFrom} - {$previousTo}).\n\n"
            . "1. catalog_sales_report for both periods with group_by=status: totals and the split by status. Say which statuses look like cancelled / returned and ask me whether to leave them out; then repeat with the status I name.\n"
            . '2. catalog_sales_report for the current period with group_by=' . ($days > 62 ? 'week' : 'day') . " to see the dynamics, and with group_by=category.\n"
            . "3. catalog_top_products for both periods (sort=revenue).\n"
            . "4. Write the analysis: revenue, orders and average check - the change in numbers and percent; what grew or fell and in which categories; which products entered or left the top. Facts only, mark guesses as guesses.\n"
            . $this->rules();
    }
}
