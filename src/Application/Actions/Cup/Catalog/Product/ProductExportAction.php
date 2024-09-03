<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\Catalog\Product;

use App\Application\Actions\Cup\Catalog\CatalogAction;

class ProductExportAction extends CatalogAction
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
        return \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($index + 1) . ($row + 1);
    }

    protected function action(): \Slim\Psr7\Response
    {
        // Fields
        $fields = trim($this->parameter('catalog_export_columns', implode(PHP_EOL, \App\Domain\References\Catalog::IMPORT_EXPORT_FIELDS_DEFAULT)));

        if ($fields) {
            $fields = array_map('trim', explode(PHP_EOL, $fields));
            $offset = [
                'rows' => max(0, +$this->parameter('catalog_import_export_offset_rows', 0)),
                'cols' => max(0, +$this->parameter('catalog_import_export_offset_cols', 0)),
            ];

            $categories = $this->catalogCategoryService->read([
                'status' => \App\Domain\Casts\Catalog\Status::WORK,
                'order' => [
                    'order' => 'ASC',
                ],
            ]);

            // Products
            switch (($category = $this->getParam('category', false))) {
                default:
                    if (!\Ramsey\Uuid\Uuid::isValid((string) $category)) {
                        goto false;
                    }

                    $category = $categories->firstWhere('uuid', $category);
                    $products = $this->catalogProductService->read([
                        'category_uuid' => $category->nested()->pluck('uuid')->all(),
                        'status' => \App\Domain\Casts\Catalog\Status::WORK,
                        'order' => [
                            'order' => 'ASC',
                        ],
                    ]);

                    break;

                case false:
                    false:
                    $products = $this->catalogProductService->read([
                        'status' => \App\Domain\Casts\Catalog\Status::WORK,
                        'order' => [
                            'order' => 'ASC',
                        ],
                    ]);

                    break;
            }

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
                /** @var \App\Domain\Models\CatalogProduct $model */
                if ($lastCategory !== $model->category_uuid) {
                    // get header cell
                    $sheet
                        ->getCell($this->getCellCoordinate(0 + $offset['cols'], $row + 1 + $offset['rows']))
                        ->setValue($model->category->title)
                        ->getStyle()
                        ->getAlignment()
                        ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

                    $sheet->mergeCells(
                        $this->getCellCoordinate(0 + $offset['cols'], $row + 1 + $offset['rows']) .
                        ':' .
                        $this->getCellCoordinate(count($fields) - 1 + $offset['cols'], $row + 1 + $offset['rows'])
                    );

                    $lastCategory = $model->category_uuid;
                    ++$row;
                }

                // product attributes
                $attributes = $model->attributes;

                foreach ($fields as $index => $field) {
                    if (trim($field)) {
                        $cell = $sheet->getCell($this->getCellCoordinate($index + $offset['cols'], $row + 1 + $offset['rows']));

                        // set default vertical aligment
                        $cell
                            ->getStyle()
                            ->getAlignment()
                            ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_TOP);

                        switch ($field) {
                            case 'uuid':
                                $cell->setValue($model->uuid);

                                break;

                            case 'category':
                                $cell->setValue($model->category->title);

                                break;

                            case 'priceFirst':
                            case 'price':
                            case 'priceWholesale':
                            case 'priceWholesaleFrom':
                            case 'tax':
                            case 'discount':
                            case 'quantity':
                            case 'quantityMin':
                                $cell
                                    ->setValue($model->{$field})
                                    ->getStyle()
                                    ->getNumberFormat()
                                    ->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED2);

                                break;

                            case 'special':
                            case 'tax_included':
                                $cell
                                    ->setValueExplicit((bool) array_get($model, $field), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_BOOL);

                                break;

                            case 'vendorcode':
                            case 'barcode':
                                $cell
                                    ->setValue($model->{$field})
                                    ->getStyle()
                                    ->getAlignment()
                                    ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);

                                break;

                            case 'volume':
                            case 'stock':
                                $cell
                                    ->setValue($model->{$field})
                                    ->getStyle()
                                    ->getNumberFormat()
                                    ->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER_00);

                                break;

                            case 'order':
                                $cell
                                    ->setValue($model->{$field})
                                    ->getStyle()
                                    ->getNumberFormat()
                                    ->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER);

                                break;

                            case 'date':
                                $cell
                                    ->setValue(\PhpOffice\PhpSpreadsheet\Shared\Date::PHPToExcel($model->getDate()))
                                    ->getStyle()
                                    ->getNumberFormat()
                                    ->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_DATE_DATETIME);

                                break;

                            default:
                                // entity field value
                                if (isset($model->{$field})) {
                                    $cell->setValue($model->{$field});

                                    continue 2;
                                }

                                // find attribute value
                                if ($attributes->count()) {
                                    /** @var \App\Domain\Models\CatalogAttribute $attribute */
                                    $attribute = $attributes->firstWhere('address', $field);

                                    if ($attribute) {
                                        switch ($attribute->type) {
                                            case \App\Domain\Casts\Catalog\Attribute\Type::STRING:
                                                $cell->setValue($attribute->value());

                                                break;

                                            case \App\Domain\Casts\Catalog\Attribute\Type::INTEGER:
                                                $cell
                                                    ->setValue($attribute->value())
                                                    ->getStyle()
                                                    ->getNumberFormat()
                                                    ->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER_00);

                                                break;

                                            case \App\Domain\Casts\Catalog\Attribute\Type::FLOAT:
                                                $cell
                                                    ->setValue($attribute->value())
                                                    ->getStyle()
                                                    ->getNumberFormat()
                                                    ->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED2);

                                                break;
                                        }
                                    }

                                    continue 2;
                                }

                                $cell->setValue('');

                                break;
                        }
                    }
                }

                ++$row;
            }

            header('Content-Type: application/vnd.ms-excel');
            header('Content-Disposition: attachment; filename="export ' . date(\App\Domain\References\Date::DATETIME) . '.xls"');

            \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx')->save('php://output');

            exit;
        }

        return $this->response->withAddedHeader('Location', $_SERVER['HTTP_REFERER'] ?? '/cup/catalog/product')->withStatus(301);
    }
}
