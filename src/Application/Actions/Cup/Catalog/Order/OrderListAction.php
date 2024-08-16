<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\Catalog\Order;

use App\Application\Actions\Cup\Catalog\CatalogAction;
use App\Domain\Casts\Reference\Type as ReferenceType;
use App\Domain\Models\CatalogOrder;
use Illuminate\Database\Eloquent\Builder;

class OrderListAction extends CatalogAction
{
    protected function action(): \Slim\Psr7\Response
    {
        $data = [
            'from' => date(\App\Domain\References\Date::DATE, strtotime('-1 month')),
            'to' => date(\App\Domain\References\Date::DATE),
            'status' => $this->getParam('status', ''),
            'payment' => $this->getParam('payment', ''),
        ];
        $data = array_merge($data, $this->getParam('date', []));

        $query = CatalogOrder::query();

        // filter by date
        $query->where(function (Builder $query) use ($data): void {
            $query->whereDate('date', '>=', $data['from']);
            $query->whereDate('date', '<=', $data['to']);
        });

        // filter by status
        if ($data['status']) {
            $query->where('status_uuid', $data['status']);
        }

        // filter by payment
        if ($data['payment']) {
            $query->where('payment_uuid', $data['payment']);
        }

        $query->orderBy('serial', 'desc');

        return $this->respondWithTemplate('cup/catalog/order/index.twig', [
            'date' => ['from' => $data['from'], 'to' => $data['to']],
            'list' => $query->get(),
            'status' => $data['status'],
            'status_list' => $this->referenceService->read(['type' => ReferenceType::ORDER_STATUS, 'status' => true, 'order' => ['order' => 'asc']]),
            'payment' => $data['payment'],
            'payment_list' => $this->referenceService->read(['type' => ReferenceType::PAYMENT, 'status' => true, 'order' => ['order' => 'asc']]),
        ]);
    }
}
