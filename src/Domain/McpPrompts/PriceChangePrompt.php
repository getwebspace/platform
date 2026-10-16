<?php declare(strict_types=1);

namespace App\Domain\McpPrompts;

use App\Domain\AbstractMcpPrompt;

class PriceChangePrompt extends AbstractMcpPrompt
{
    public const NAME = 'change_prices';
    public const TITLE = 'Change prices of a category';
    public const DESCRIPTION = 'Change prices of a category by a percent or an amount with a preview and a confirmation.';
    public const TOOLS = ['catalog_category_list', 'catalog_price_bulk_update'];

    public function getArguments(): array
    {
        return [
            ['name' => 'category', 'description' => 'Category title', 'required' => true],
            ['name' => 'change', 'description' => 'How to change, ex: +10% or -500', 'required' => true],
        ];
    }

    public function render(array $args = []): string
    {
        $category = trim((string) ($args['category'] ?? ''));
        $change = trim((string) ($args['change'] ?? ''));

        if ($category === '' || $change === '') {
            throw new \InvalidArgumentException('Arguments category and change are required');
        }
        if (preg_match('/^[+-]?\s*\d+([.,]\d+)?\s*%?$/', $change) !== 1) {
            throw new \InvalidArgumentException('Argument change must look like +10%, -5% or -500');
        }

        $isPercent = str_ends_with($change, '%');
        $number = str_replace([',', '%', ' ', '+'], ['.', '', '', ''], $change);
        $parameter = $isPercent ? "percent={$number}" : "amount={$number}";

        return "Change the prices in the category \"{$category}\": {$change}.\n\n"
            . "1. Find the category uuid with catalog_category_list (if several match, ask me which one).\n"
            . "2. Call catalog_price_bulk_update with category_uuid, {$parameter} and WITHOUT apply - it is a preview.\n"
            . "3. Show me the number of products and the examples \"was -> became\". Say clearly that it changes the prices on the live site.\n"
            . "4. Only after my explicit yes call it again with the same arguments, apply=true and expected_count equal to the previewed total.\n"
            . "5. Report how many products were changed.\n"
            . $this->rules();
    }
}
