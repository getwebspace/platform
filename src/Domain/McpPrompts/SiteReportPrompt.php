<?php declare(strict_types=1);

namespace App\Domain\McpPrompts;

use App\Domain\AbstractMcpPrompt;

class SiteReportPrompt extends AbstractMcpPrompt
{
    public const NAME = 'site_report';
    public const TITLE = 'Site report';
    public const DESCRIPTION = 'A short report: what happened on the site for a period and what needs attention.';
    public const TOOLS = ['site_overview', 'catalog_order_list'];

    public function getArguments(): array
    {
        return [
            ['name' => 'days', 'description' => 'Period in days, default 7', 'required' => false],
        ];
    }

    public function render(array $args = []): string
    {
        $days = max(1, min(90, (int) ($args['days'] ?? 7)));
        $from = datetime()->subDays($days)->format('Y-m-d');

        return "Prepare a report about the site for the last {$days} days (from {$from}).\n\n"
            . "1. Call site_overview: take the queues that need attention and the numbers.\n"
            . "2. Call catalog_order_list with date_from={$from}: count the orders, the total amount and the average check, name the biggest orders. If there are more pages, follow has_more.\n"
            . "3. Write the report: 3-5 lines of the main numbers, then \"needs attention\" as a short list with what to do first. Only facts from the tools, no guesses.\n"
            . $this->rules();
    }
}
