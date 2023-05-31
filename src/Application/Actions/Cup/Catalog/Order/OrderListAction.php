<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\Catalog\Order;

use App\Application\Actions\Cup\Catalog\CatalogAction;
use Doctrine\DBAL\ParameterType;

class OrderListAction extends CatalogAction
{
    protected function action(): \Slim\Psr7\Response
    {
        $data = [
            'status' => $this->getParam('status', ''),
            'from' => date(\App\Domain\References\Date::DATE, strtotime('-30 days')),
            'to' => date(\App\Domain\References\Date::DATE),
        ];
        $data = array_merge($data, $this->getParam('date', []));

        $qb = $this->entityManager->createQueryBuilder();
        $query = $qb
            ->select('o')
            ->from(\App\Domain\Entities\Catalog\Order::class, 'o')
            ->where('o.date >= :dateFrom')
            ->andWhere('o.date <= :dateTo')
            ->setParameter('dateFrom', $data['from'], ParameterType::STRING)
            ->setParameter('dateTo', $data['to'], ParameterType::STRING)
            ->orderBy('o.date', 'DESC');

        // filter by status
        if ($data['status']) {
            $query
                ->andWhere('o.status = :status')
                ->setParameter('status', $data['status'], ParameterType::STRING);
        }

        return $this->respondWithTemplate('cup/catalog/order/index.twig', [
            'date' => ['from' => $data['from'], 'to' => $data['to']],
            'list' => collect($query->getQuery()->getResult()),
            'status' => $data['status'],
            'status_list' => $this->catalogOrderStatusService->read(),
        ]);
    }
}
