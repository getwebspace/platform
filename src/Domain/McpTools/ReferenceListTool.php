<?php declare(strict_types=1);

namespace App\Domain\McpTools;

use App\Domain\AbstractMcpTool;
use App\Domain\Casts\Reference\Type as ReferenceType;
use App\Domain\Models\Reference;
use App\Domain\Service\Reference\ReferenceService;

class ReferenceListTool extends AbstractMcpTool
{
    public const NAME = 'reference_list';
    public const TITLE = 'Reference books';
    public const DESCRIPTION = 'Site reference books: order statuses, payment and delivery methods, currencies, stock statuses, tax rates, countries, manufacturers, etc. '
        . 'Use it to learn allowed values (e.g. order status titles) before filtering or changing data.';
    public const SCOPE = 'reference';

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'type' => ['type' => 'string', 'enum' => ReferenceType::LIST],
                'include_disabled' => ['type' => 'boolean', 'default' => false],
            ] + $this->paginationSchema(),
            'required' => ['type'],
        ];
    }

    public function execute(array $args = []): mixed
    {
        if (!in_array($args['type'] ?? null, ReferenceType::LIST, true)) {
            throw new \InvalidArgumentException('Field type is required, allowed: ' . implode(', ', ReferenceType::LIST));
        }

        /** @var ReferenceService $referenceService */
        $referenceService = $this->container->get(ReferenceService::class);

        $filter = ['type' => $args['type']];

        if (empty($args['include_disabled'])) {
            $filter['status'] = true;
        }

        return $this->paginate(
            $args,
            fn (int $limit, int $offset) => $referenceService->read($filter + [
                'order' => ['order' => 'asc', 'title' => 'asc'],
                'limit' => $limit,
                'offset' => $offset,
            ]),
            fn (Reference $reference) => [
                'uuid' => (string) $reference->uuid,
                'title' => $reference->title,
                'value' => $reference->value,
                'enabled' => $reference->status,
            ]
        );
    }
}
