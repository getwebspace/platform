<?php declare(strict_types=1);

namespace App\Domain\McpResources;

use App\Domain\AbstractMcpResource;
use App\Domain\Casts\Reference\Type as ReferenceType;
use App\Domain\Models\Reference;
use App\Domain\Service\Reference\ReferenceService;

class OrderStatusesResource extends AbstractMcpResource
{
    public const URI = 'platform://reference/order_statuses';
    public const NAME = 'order_statuses';
    public const TITLE = 'Order statuses';
    public const DESCRIPTION = 'Statuses an order can have, in the order of the admin panel (the first one is "new").';
    public const SCOPE = 'reference';

    public function read(): string
    {
        return $this->json(
            $this->container->get(ReferenceService::class)
                ->read(['type' => ReferenceType::ORDER_STATUS, 'status' => true, 'order' => ['order' => 'asc']])
                ->map(fn (Reference $status) => ['uuid' => (string) $status->uuid, 'title' => $status->title])
                ->values()
                ->all()
        );
    }
}
