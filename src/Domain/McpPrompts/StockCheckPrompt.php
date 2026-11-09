<?php declare(strict_types=1);

namespace App\Domain\McpPrompts;

use App\Domain\AbstractMcpPrompt;

class StockCheckPrompt extends AbstractMcpPrompt
{
    public const NAME = 'catalog_check';
    public const TITLE = 'Catalog check';
    public const DESCRIPTION = 'Find the products with problems (no stock, no price, no image, no description) and fix them with the operator.';
    public const TOOLS = ['catalog_stock_report', 'catalog_product_update'];

    public function render(array $args = []): string
    {
        return "Check the catalog and help me fix what is wrong.\n\n"
            . "1. Call catalog_stock_report. Give a summary of every check with the numbers.\n"
            . "2. Say what is likely a real problem and what may be normal (stock 0 for services). Ask me which problem to work on first.\n"
            . "3. For the chosen one take the examples (ask for more with the examples argument if needed), read a product with catalog_product_get when you need details, and suggest the fix: a price or stock I tell you, a description written from the data of the product.\n"
            . "4. Apply with catalog_product_update one product at a time, only after my confirmation. Never invent prices or stock.\n"
            . $this->rules();
    }
}
