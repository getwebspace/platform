<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\Catalog\Order;

use App\Application\Actions\Cup\Catalog\CatalogAction;
use Doctrine\DBAL\ParameterType;

class OrderExportAction extends CatalogAction
{
    protected function createSpreadSheet()
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();

        $spreadsheet->getProperties()
            ->setCreator('WebSpace Engine CMS')
            ->setTitle('Export order list ' . date(\App\Domain\References\Date::DATETIME))
            ->setCategory('Export');

        $spreadsheet->setActiveSheetIndex(0)->setTitle('Export');

        return $spreadsheet;
    }

    protected function getCellCoordinate($index, $row = 1)
    {
        return \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($index + 1) . ($row + 1);
    }

    protected function action(): \Slim\Psr7\Response
    {
        $data = [
            'status' => $this->getParam('status', ''),
            'from' => $this->getParam('from', ''),
            'to' => $this->getParam('to', ''),
        ];

        if ($data['from'] && $data['to']) {
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

            $orders = collect($query->getQuery()->getResult());

            if ($orders->count()) {
                $fields = ['serial', 'external_id', 'date', 'delivery.client', 'phone', 'email', 'total', 'shipping', 'delivery.address', 'system'];

                $spreadsheet = $this->createSpreadSheet();
                $sheet = $spreadsheet->getActiveSheet();

                // Write header row
                foreach ($fields as $index => $field) {
                    $sheet
                        ->getCell($this->getCellCoordinate($index, 0))
                        ->setValue($field)
                        ->getStyle()
                        ->getFont()
                        ->setBold(true);
                }

                foreach ($orders->sortBy('date', SORT_REGULAR, true) as $row => $model) {
                    /** @var \App\Domain\Entities\Catalog\Order $model */

                    foreach ($fields as $index => $field) {
                        $cell = $sheet->getCell($this->getCellCoordinate($index, $row + 1));

                        switch ($field) {
                            case 'date':
                            case 'shipping':
                                $cell
                                    ->setValue(\PhpOffice\PhpSpreadsheet\Shared\Date::PHPToExcel($model->getDate()))
                                    ->getStyle()
                                    ->getNumberFormat()
                                    ->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_DATE_DATETIME);

                                break;

                            case 'total':
                                $cell
                                    ->setValue($model->getTotalPrice())
                                    ->getStyle()
                                    ->getNumberFormat()
                                    ->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED2);

                                break;

                            default:
                                $cell->setValue(array_get($model->toArray(), $field));

                                break;
                        }
                    }
                }

                header('Content-Type: application/vnd.ms-excel');
                header('Content-Disposition: attachment; filename="export ' . date(\App\Domain\References\Date::DATETIME) . '.xls"');

                \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx')->save('php://output');

                exit;
            }
        }

        return $this->response->withAddedHeader('Location', $_SERVER['HTTP_REFERER'] ?? '/cup/catalog/order')->withStatus(301);
    }
}
