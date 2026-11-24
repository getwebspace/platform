<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\Catalog\Order;

use App\Domain\Models\CatalogOrder;

/**
 * The orders as a board: a column per status, a card per order
 *
 * The filters are those of the list, except the status: the columns are the statuses
 */
class OrderBoardAction extends OrderListAction
{
    /** a board is for what is going on now: two weeks, and the newest of them if there are too many */
    protected int $defaultDays = 14;

    protected ?int $limit = 5000;

    protected function action(): \Slim\Psr7\Response
    {
        $data = $this->filters();

        $columns = [];
        foreach ($data['status_list'] as $status) {
            $columns[] = [
                'uuid' => (string) $status->uuid,
                'title' => $status->title,
                'color' => $status->value['color'] ?? null,
            ];
        }

        $known = array_column($columns, 'uuid');
        $orders = [];

        /** @var CatalogOrder $order */
        foreach ($data['list'] as $order) {
            $orders[] = [
                'uuid' => (string) $order->uuid,
                'serial' => $order->serial,
                'client' => (string) ($order->delivery['client'] ?? ''),
                'phone' => (string) $order->phone,
                'date' => $order->date?->format('d.m.Y H:i'),
                'payment' => $order->payment?->title,
                // an order of a status that is switched off is shown with those who have none
                'status_uuid' => in_array((string) $order->status_uuid, $known, true) ? (string) $order->status_uuid : '',
                'total' => round($order->totalSum(), 2),
                'count' => $order->products->count(),
            ];
        }

        return $this->respondWithTemplate('cup/catalog/order/board.twig', $data + [
            'board' => ['columns' => $columns, 'orders' => $orders],
            'limit' => $this->limit,
            'cut' => count($orders) >= $this->limit,
        ]);
    }
}
