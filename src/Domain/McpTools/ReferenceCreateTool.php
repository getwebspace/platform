<?php declare(strict_types=1);

namespace App\Domain\McpTools;

use App\Application\PubSub;
use App\Domain\AbstractMcpTool;
use App\Domain\Casts\Reference\Type as ReferenceType;
use App\Domain\Models\Reference;
use App\Domain\Service\Reference\ReferenceService;

class ReferenceCreateTool extends AbstractMcpTool
{
    public const NAME = 'reference_create';
    public const TITLE = 'Create reference record';
    public const DESCRIPTION = 'Adds a record to a reference book (order status, payment or delivery method, currency, stock status, manufacturer, ...), see reference_list for the existing ones and their "value" format. '
        . 'Records of type "document" hold templates and are not handled here. Nothing is deleted - disable a record with reference_update status=false. Confirm with the operator first.';
    public const READ_ONLY = false;
    public const PRIVATE = true;
    public const SCOPE = 'reference';

    /**
     * Types a tool may touch: documents carry Twig templates, which are code
     */
    protected const TYPES = [
        ReferenceType::TEXT, ReferenceType::STORE_LOCATION, ReferenceType::CURRENCY, ReferenceType::ORDER_STATUS, ReferenceType::STOCK_STATUS,
        ReferenceType::PAYMENT, ReferenceType::DELIVERY, ReferenceType::COUNTRY, ReferenceType::TAX_RATE, ReferenceType::LENGTH_CLASS,
        ReferenceType::WEIGHT_CLASS, ReferenceType::ADDRESS_FORMAT, ReferenceType::SOCIAL_NETWORK, ReferenceType::MANUFACTURER,
    ];

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'type' => ['type' => 'string', 'enum' => self::TYPES],
                'title' => ['type' => 'string'],
                'value' => ['type' => 'object', 'description' => 'Type specific values, copy the shape of an existing record from reference_list'],
                'order' => ['type' => 'integer', 'description' => 'Sort order, 1 by default'],
                'status' => ['type' => 'boolean', 'description' => 'Enabled, true by default'],
            ],
            'required' => ['type', 'title'],
        ];
    }

    public function execute(array $args = []): mixed
    {
        if (!in_array($args['type'] ?? null, self::TYPES, true)) {
            throw new \InvalidArgumentException('Field type must be one of: ' . implode(', ', self::TYPES));
        }
        if (blank($title = $this->textArgument($args, 'title'))) {
            throw new \InvalidArgumentException('Field title is required');
        }
        if (isset($args['value']) && !is_array($args['value'])) {
            throw new \InvalidArgumentException('Field value must be an object');
        }

        $reference = $this->container->get(ReferenceService::class)->create([
            'type' => $args['type'],
            'title' => $title,
            'value' => $args['value'] ?? [],
            'order' => $this->intArgument($args, 'order', 1, 0, 100000),
            'status' => (bool) ($args['status'] ?? true),
        ]);

        $this->container->get(PubSub::class)->publish('api:reference:create', $reference);
        $this->logger->notice('Reference created via MCP', ['uuid' => (string) $reference->uuid, 'type' => $reference->type]);

        return $this->describe($reference);
    }

    protected function describe(Reference $reference): array
    {
        return [
            'uuid' => (string) $reference->uuid,
            'type' => $reference->type,
            'title' => $reference->title,
            'value' => $reference->value,
            'order' => $reference->order,
            'status' => (bool) $reference->status,
        ];
    }
}
