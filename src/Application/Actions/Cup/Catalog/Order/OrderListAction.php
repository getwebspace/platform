<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\Catalog\Order;

use App\Application\Actions\Cup\Catalog\CatalogAction;
use App\Domain\Casts\Reference\Type as ReferenceType;

class OrderListAction extends CatalogAction
{
    protected function action(): \Slim\Psr7\Response
    {
        $period = (string) $this->getParam('period', '');
        $custom = (array) $this->getParam('date', []);
        $today = date(\App\Domain\References\Date::DATE);

        // the old date[from] / date[to] links keep working as a custom period
        if ($period === '' && ($custom['from'] ?? $custom['to'] ?? '') !== '') {
            $period = 'custom';
        }

        $range = match ($period) {
            'today' => [$today, $today],
            'week' => [date(\App\Domain\References\Date::DATE, strtotime('-6 days')), $today],
            'month' => [date(\App\Domain\References\Date::DATE, strtotime('first day of this month')), $today],
            'all' => [null, null],
            'custom' => [$custom['from'] ?? null ?: null, $custom['to'] ?? null ?: null],
            default => [date(\App\Domain\References\Date::DATE, strtotime('-30 days')), $today],
        };
        $period = $period ?: '30days';

        $status = (string) $this->getParam('status', '');
        $payment = (string) $this->getParam('payment', '');
        $q = trim((string) $this->getParam('q', ''));

        $criteria = [
            'search' => $q ?: null,
            'status_uuid' => $status ?: null,
            'payment_uuid' => $payment ?: null,
            'order' => ['serial' => 'desc'],
        ];
        if ($range[0]) {
            $criteria['date_from'] = $range[0];
        }
        if ($range[1]) {
            $criteria['date_to'] = $range[1];
        }

        $list = $this->catalogOrderService->read($criteria);

        return $this->respondWithTemplate('cup/catalog/order/index.twig', [
            'period' => $period,
            'date' => ['from' => $range[0], 'to' => $range[1]],
            'q' => $q,
            'list' => $list,
            'status' => $status,
            'status_list' => $this->referenceService->read(['type' => ReferenceType::ORDER_STATUS, 'status' => true, 'order' => ['order' => 'asc']]),
            'payment' => $payment,
            'payment_list' => $this->referenceService->read(['type' => ReferenceType::PAYMENT, 'status' => true, 'order' => ['order' => 'asc']]),
        ]);
    }
}
