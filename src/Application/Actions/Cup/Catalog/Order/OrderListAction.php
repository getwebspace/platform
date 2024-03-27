<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\Catalog\Order;

use App\Application\Actions\Cup\Catalog\CatalogAction;
use App\Domain\Casts\Reference\Type as ReferenceType;

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

        $qb = $this->entityManager->createQueryBuilder();
        $query = $qb
            ->select('o')
            ->from(\App\Domain\Entities\Catalog\Order::class, 'o')
            ->where('o.date >= :dateFrom')
            ->andWhere('o.date <= :dateTo')
            ->setParameter('dateFrom', $data['from'] . ' 00:00:00', ParameterType::STRING)
            ->setParameter('dateTo', $data['to'] . ' 23:59:59', ParameterType::STRING)
            ->orderBy('o.date', 'DESC');

        // filter by status
        if ($data['status']) {
            $query
                ->andWhere('o.status_uuid = :status')
                ->setParameter('status', $data['status'], ParameterType::STRING);
        }

        // filter by payment
        if ($data['payment']) {
            $query
                ->andWhere('o.payment_uuid = :payment')
                ->setParameter('payment', $data['payment'], ParameterType::STRING);
        }

        return $this->respondWithTemplate('cup/catalog/order/index.twig', [
            'date' => ['from' => $data['from'], 'to' => $data['to']],
            'list' => collect($query->getQuery()->getResult()),
            'status' => $data['status'],
            'status_list' => $this->referenceService->read(['type' => ReferenceType::ORDER_STATUS, 'status' => true, 'order' => ['order' => 'asc']]),
            'payment' => $data['payment'],
            'payment_list' => $this->referenceService->read(['type' => ReferenceType::PAYMENT, 'status' => true, 'order' => ['order' => 'asc']]),
        ]);
    }
}
