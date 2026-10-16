<?php declare(strict_types=1);

namespace App\Domain\McpPrompts;

use App\Domain\AbstractMcpPrompt;

class NewOrdersPrompt extends AbstractMcpPrompt
{
    public const NAME = 'handle_new_orders';
    public const TITLE = 'Handle new orders';
    public const DESCRIPTION = 'Go through the orders nobody has handled yet and move them to the right status.';
    public const TOOLS = ['catalog_order_list', 'catalog_order_get', 'reference_list', 'catalog_order_update_status'];

    public function getArguments(): array
    {
        return [
            ['name' => 'status', 'description' => 'Status to set after checking (title). If empty, ask the operator which one to use', 'required' => false],
        ];
    }

    public function render(array $args = []): string
    {
        $status = trim((string) ($args['status'] ?? ''));

        return "Help me handle the new orders of the shop.\n\n"
            . "1. Call reference_list with type=order_status to learn the statuses of this shop.\n"
            . "2. Call catalog_order_list with new_only=true and show a short table: number, date, client, phone, items, total.\n"
            . "3. For the orders I choose, call catalog_order_get and check the contents: products, delivery, comment. Point out anything odd (empty phone, zero total, a comment that asks for something).\n"
            . ($status !== '' ? "4. After I confirm, set the status \"{$status}\" with catalog_order_update_status, one order at a time.\n" : "4. Ask me which status to set, then after I confirm use catalog_order_update_status, one order at a time.\n")
            . $this->rules();
    }
}
