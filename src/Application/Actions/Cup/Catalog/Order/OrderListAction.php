<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\Catalog\Order;

use App\Application\Actions\Cup\Catalog\CatalogAction;
use App\Domain\Casts\Reference\Type as ReferenceType;

class OrderListAction extends CatalogAction
{
    /** the days the list shows until another period is chosen */
    protected int $defaultDays = 30;

    /** the most orders one page takes (null - all of them) */
    protected ?int $limit = null;

    protected function action(): \Slim\Psr7\Response
    {
        return $this->respondWithTemplate('cup/catalog/order/index.twig', $this->filters());
    }

    /**
     * The filters of the list and of the board: the same period, search, status and payment
     *
     * @return array{period: string, date: array, q: string, status: string, payment: string, status_list: mixed, payment_list: mixed, list: mixed}
     */
    protected function filters(): array
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
            '30days' => [date(\App\Domain\References\Date::DATE, strtotime('-30 days')), $today],
            'all' => [null, null],
            // only a day is a day: anything else would reach the query as text to be parsed
            'custom' => [$this->day($custom['from'] ?? null), $this->day($custom['to'] ?? null)],
            default => [date(\App\Domain\References\Date::DATE, strtotime("-{$this->defaultDays} days")), $today],
        };
        $period = $period ?: $this->defaultDays . 'days';

        $status = (string) $this->getParam('status', '');
        $payment = (string) $this->getParam('payment', '');
        $q = trim((string) $this->getParam('q', ''));

        $criteria = [
            'search' => $q ?: null,
            'status_uuid' => $status ?: null,
            'payment_uuid' => $payment ?: null,
            'order' => ['serial' => 'desc'],
            'limit' => $this->limit,
        ];
        if ($range[0]) {
            $criteria['date_from'] = $range[0];
        }
        if ($range[1]) {
            $criteria['date_to'] = $range[1];
        }

        return [
            'list' => $this->catalogOrderService->read($criteria),
            'period' => $period,
            'date' => ['from' => $range[0], 'to' => $range[1]],
            'q' => $q,
            'status' => $status,
            'status_list' => $this->referenceService->read(['type' => ReferenceType::ORDER_STATUS, 'status' => true, 'order' => ['order' => 'asc']]),
            'payment' => $payment,
            'payment_list' => $this->referenceService->read(['type' => ReferenceType::PAYMENT, 'status' => true, 'order' => ['order' => 'asc']]),
        ];
    }

    private function day(mixed $value): ?string
    {
        return is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 ? $value : null;
    }
}
