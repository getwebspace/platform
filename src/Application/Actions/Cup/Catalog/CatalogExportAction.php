<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\Catalog;

class CatalogExportAction extends CatalogAction
{
    protected function createSpreadSheet()
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();

        $spreadsheet->getProperties()
            ->setCreator('WebSpace Engine CMS')
            ->setTitle('Export catalog data ' . date(\App\Domain\References\Date::DATETIME))
            ->setCategory('Export');

        $spreadsheet->setActiveSheetIndex(0)->setTitle('Export');

        return $spreadsheet;
    }

    protected function getCellCoordinate($index, $row = 1)
    {
        static $alphabet;

        if (!$alphabet) {
            $alphabet = range('A', 'Z');
        }

        return $alphabet[$index] . ($row + 1);
    }

    protected function action(): \Slim\Http\Response
    {
        // Fields
        $fields = trim($this->getParameter('catalog_export_columns', ''));

        if ($fields) {
            $fields = array_map('trim', explode(PHP_EOL, $fields));
            $offset = [
                'rows' => max(0, +$this->getParameter('catalog_import_export_offset_rows', 0)),
                'cols' => max(0, +$this->getParameter('catalog_import_export_offset_cols', 0)),
            ];

            $categories = collect($this->categoryRepository->findBy([
                'status' => \App\Domain\Types\Catalog\CategoryStatusType::STATUS_WORK,
            ]));

            // Products
            switch (($category = $this->request->getParam('category', false))) {
                default:
                    if (!\Ramsey\Uuid\Uuid::isValid($category)) {
                        goto false;
                    }

                    $category = $categories->firstWhere('uuid', $category);
                    $products = collect($this->productRepository->findBy([
                        'category' => \App\Domain\Entities\Catalog\Category::getChildren($categories, $category)->pluck('uuid')->all(),
                        'status' => \App\Domain\Types\Catalog\ProductStatusType::STATUS_WORK,
                    ]));

                    break;

                    false:
                case false:
                    $products = collect($this->productRepository->findBy([
                        'status' => \App\Domain\Types\Catalog\ProductStatusType::STATUS_WORK,
                    ]));

                    break;
            }

            $wizard = new \PhpOffice\PhpSpreadsheet\Helper\Html();
            $spreadsheet = $this->createSpreadSheet();
            $sheet = $spreadsheet->getActiveSheet();

            // Write header row
            foreach ($fields as $index => $field) {
                $sheet
                    ->getCell($this->getCellCoordinate($index + $offset['cols'], 0 + $offset['rows']))
                    ->setValue($field)
                    ->getStyle()
                    ->getFont()
                    ->setBold(true);
            }

            $row = 0;
            $lastCategory = null;

            // Write table data row by row
            foreach ($products->sortBy('category') as $model) {
                /** @var \App\Domain\Entities\Catalog\Product $model */
                if ($lastCategory !== $model->category->toString()) {
                    // merge cells
                    $sheet->mergeCells($this->getCellCoordinate(0 + $offset['cols'], $row + 1 + $offset['rows']) . ':' . $this->getCellCoordinate(count($fields) - 1 + $offset['cols'], $row + 1 + $offset['rows']));

                    // get header cell
                    $cell = $sheet->getCell($this->getCellCoordinate(0 + $offset['cols'], $row + 1 + $offset['rows']));

                    // set default vertical aligment
                    $cell
                        ->getStyle()
                        ->getAlignment()
                        ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);

                    // set back color
                    $cell
                        ->getStyle()
                        ->getFill()
                        ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                        ->getStartColor()->setRGB('D9DDDC');

                    // set value
                    $cell
                        ->setValue($categories->firstWhere('uuid', $model->category)->title ?? 'unknown');

                    $lastCategory = $model->category->toString();
                    $row++;
                }

                foreach ($fields as $index => $field) {
                    if (trim($field)) {
                        $cell = $sheet->getCell($this->getCellCoordinate($index + $offset['cols'], $row + 1 + $offset['rows']));

                        // set default vertical aligment
                        $cell
                            ->getStyle()
                            ->getAlignment()
                            ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_TOP);

                        switch ($field) {
                            case 'category':
                                $cell->setValue($categories->firstWhere('uuid', $model->category)->title ?? 'unknown');

                                break;

                            case 'description':
                            case 'extra':
                                $cell
                                    ->setValue(trim($wizard->toRichTextObject($model->get($field))->getPlainText()));

                                break;

                            case 'priceFirst':
                            case 'price':
                            case 'priceWholesale':
                                $cell
                                    ->setValue($model->get($field))
                                    ->getStyle()
                                    ->getNumberFormat()
                                    ->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED2);

                                break;

                            case 'vendorcode':
                            case 'barcode':
                                $cell
                                    ->setValue($model->get($field))
                                    ->getStyle()
                                    ->getAlignment()
                                    ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);

                                break;

                            case 'volume':
                            case 'stock':
                            case 'order':
                                $cell
                                    ->setValue($model->get($field))
                                    ->getStyle()
                                    ->getNumberFormat()
                                    ->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER_00);

                                break;

                            case 'date':
                                $cell
                                    ->setValue(\PhpOffice\PhpSpreadsheet\Shared\Date::PHPToExcel($model->date))
                                    ->getStyle()
                                    ->getNumberFormat()
                                    ->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_DATE_DATETIME);

                                break;

                            default:
                                $cell->setValue($model->get($field));

                                break;
                        }
                    }
                }

                $row++;
            }

            return $this->response
                ->withAddedHeader('Content-type', 'application/vnd.ms-excel')
                ->withAddedHeader('Content-Disposition', 'attachment; filename="export ' . date(\App\Domain\References\Date::DATETIME) . '.xls"')
                ->write(\PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xls')->save('php://output'));
        }

        return $this->response->withAddedHeader('Location', $_SERVER['HTTP_REFERER'] ?? '/cup/catalog/product')->withStatus(301);
    }
}
