<?php declare(strict_types=1);

namespace App\Domain\McpTools\Concerns;

use App\Domain\Casts\Reference\Type as ReferenceType;
use App\Domain\Models\Reference;
use App\Domain\Service\Reference\ReferenceService;
use Illuminate\Support\Collection;

/**
 * Order statuses are reference books: enabled ones, in the order of the admin panel
 *
 * @property \Psr\Container\ContainerInterface $container
 */
trait FindsOrderStatus
{
    /**
     * @return Collection<int, Reference>
     */
    protected function orderStatuses(): Collection
    {
        return $this->container->get(ReferenceService::class)->read([
            'type' => ReferenceType::ORDER_STATUS,
            'status' => true,
            'order' => ['order' => 'asc'],
        ]);
    }

    protected function firstOrderStatus(): ?Reference
    {
        return $this->orderStatuses()->first();
    }

    /**
     * By uuid or by title (case-insensitive)
     */
    protected function findOrderStatus(string $status): Reference
    {
        $statuses = $this->orderStatuses();
        $found = $statuses->first(fn (Reference $item) => (string) $item->uuid === $status || mb_strtolower($item->title) === mb_strtolower($status));

        if ($found === null) {
            throw new \InvalidArgumentException('Unknown order status, allowed: ' . $statuses->pluck('title')->implode(', '));
        }

        return $found;
    }
}
