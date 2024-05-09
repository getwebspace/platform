<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\Catalog\Order;

use App\Application\Actions\Cup\Catalog\CatalogAction;

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
            $query = \App\Domain\Models\CatalogOrder::query();
            $query->whereBetween('date', [datetime($data['from']), datetime($data['to'])]);
            $query->orderBy('date', 'DESC');

            // filter by status
            if ($data['status']) {
                $query->where('status', $data['status']);
            }

            $orders = $query->get();

            if ($orders->count()) {
                $fields = ['serial', 'date', 'delivery.client', 'phone', 'email', 'total', 'discount', 'tax', 'shipping', 'delivery.address', 'external_id'];

                $spreadsheet = $this->createSpreadSheet();
                $sheet = $spreadsheet->getActiveSheet();

                // write header row
                foreach ($fields as $index => $field) {
                    $sheet
                        ->getCell($this->getCellCoordinate($index, 0))
                        ->setValue($field)
                        ->getStyle()
                        ->getFont()
                        ->setBold(true);
                }

                /** @var \App\Domain\Models\CatalogOrder $model */
                foreach ($orders->sortBy('date', SORT_REGULAR, true) as $row => $model) {
                    foreach ($fields as $index => $field) {
                        $cell = $sheet->getCell($this->getCellCoordinate($index, $row + 1));

                        switch ($field) {
                            case 'date':
                            case 'shipping':
                                $cell
                                    ->setValue(\PhpOffice\PhpSpreadsheet\Shared\Date::PHPToExcel($model[$field]))
                                    ->getStyle()
                                    ->getNumberFormat()
                                    ->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_DATE_DATETIME);

                                break;

                            case 'total':
                                $cell
                                    ->setValue($model->totalSum())
                                    ->getStyle()
                                    ->getNumberFormat()
                                    ->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED2);

                                break;

                            case 'discount':
                                $cell
                                    ->setValue($model->totalDiscount())
                                    ->getStyle()
                                    ->getNumberFormat()
                                    ->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED2);

                                break;

                            case 'tax':
                                $cell
                                    ->setValue($model->totalTax())
                                    ->getStyle()
                                    ->getNumberFormat()
                                    ->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED2);

                                break;

                            default:
                                $cell->setValue(array_get($model, $field));

                                break;
                        }
                    }
                }

                header('Content-Type: application/vnd.ms-excel');
                header('Content-Disposition: attachment; filename="order ' . date(\App\Domain\References\Date::DATETIME) . '.xls"');

                \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx')->save('php://output');

                exit;
            }
        }

        return $this->response->withAddedHeader('Location', $_SERVER['HTTP_REFERER'] ?? '/cup/catalog/order')->withStatus(301);
    }
}
