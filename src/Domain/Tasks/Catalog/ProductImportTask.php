<?php declare(strict_types=1);

namespace App\Domain\Tasks\Catalog;

use App\Domain\AbstractTask;
use App\Domain\Service\Catalog\AttributeService as CatalogAttributeService;
use App\Domain\Service\Catalog\CategoryService as CatalogCategoryService;
use App\Domain\Service\Catalog\Exception\AddressAlreadyExistsException;
use App\Domain\Service\Catalog\Exception\CategoryNotFoundException;
use App\Domain\Service\Catalog\Exception\MissingTitleValueException;
use App\Domain\Service\Catalog\Exception\ProductNotFoundException;
use App\Domain\Service\Catalog\ProductService as CatalogProductService;
use App\Domain\Service\File\Exception\FileNotFoundException;
use App\Domain\Service\File\FileService;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ProductImportTask extends AbstractTask
{
    public const TITLE = 'Import directory from Excel file';

    public function execute(array $params = []): \App\Domain\Models\Task
    {
        $default = [
            'file' => null,
        ];
        $params = array_merge($default, $params);

        return parent::execute($params);
    }

    /**
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @throws \Psr\Container\NotFoundExceptionInterface
     * @throws \App\Domain\Service\Task\Exception\TaskNotFoundException
     * @throws \PhpOffice\PhpSpreadsheet\Reader\Exception
     */
    protected function action(array $args = []): bool
    {
        $fileService = $this->container->get(FileService::class);

        try {
            $file = $fileService->read(['uuid' => $args['file']]);
        } catch (FileNotFoundException $e) {
            return $this->setStatusFail();
        }

        /** @var CatalogCategoryService $catalogCategoryService */
        $catalogCategoryService = $this->container->get(CatalogCategoryService::class);

        /** @var CatalogProductService $catalogProductService */
        $catalogProductService = $this->container->get(CatalogProductService::class);

        /** @var CatalogAttributeService $catalogAttributeService */
        $catalogAttributeService = $this->container->get(CatalogAttributeService::class);

        // parse excel file
        /** @var Collection $data */
        if (($data = $this->getParsedExcelData($file->internal_path())) !== []) {
            $action = $this->parameter('catalog_import_action', 'update');
            $key_field = $this->parameter('catalog_import_key', 'vendorcode');
            $pagination = $this->parameter('catalog_category_pagination', 10);
            $template = [
                'category' => $this->parameter('catalog_category_template', 'catalog.category.twig'),
                'product' => $this->parameter('catalog_product_template', 'catalog.product.twig'),
            ];
            $attributes = $catalogAttributeService->read();

            $now = datetime();
            $category = null;
            $nested = false;

            foreach ($data as $index => $item) {
                switch ($item['type']) {
                    case 'category':
                        try {
                            $this->logger->info('Search category', ['item' => $item]);
                            $category = $catalogCategoryService
                                ->read([
                                    'title' => [
                                        '' . $item['raw'],
                                        '' . $item['formatted'],
                                        '' . $item['trimmed'],
                                        floatval($item['raw']),
                                    ],
                                    'status' => \App\Domain\Casts\Catalog\Status::WORK,
                                ])
                                ->first();

                            if ($category === null) throw new CategoryNotFoundException();
                        } catch (CategoryNotFoundException $e) {
                            if ($action === 'insert') {
                                $this->logger->info('Create category', $item);

                                try {
                                    $category = $catalogCategoryService->create([
                                        'title' => $item['trimmed'] ?? $item['raw'],
                                        'parent_uuid' => $nested === true ? $category->uuid : null,
                                        'pagination' => $pagination,
                                        'template' => $template,
                                        'export' => 'excel',
                                    ]);
                                } catch (MissingTitleValueException $e) {
                                    $this->logger->warning('Category wrong title value');
                                } catch (AddressAlreadyExistsException $e) {
                                    $this->logger->warning('Category wrong address value');
                                }
                            }
                        }

                        $nested = (bool) $category;

                        break;

                    case 'item':
                        $product = null;
                        $data = collect($item['data'] ?? []);

                        try {
                            $this->logger->info('Search product', [$key_field => '' . $data[$key_field]['formatted'], 'item' => $data]);
                            $product = $catalogProductService
                                ->read([
                                    $key_field => [
                                        '' . $data[$key_field]['raw'],
                                        '' . $data[$key_field]['formatted'],
                                        '' . $data[$key_field]['trimmed'],
                                        floatval($data[$key_field]['raw']),
                                    ],
                                    'status' => \App\Domain\Casts\Catalog\Status::WORK,
                                ])
                                ->first();

                            if ($product === null) throw new ProductNotFoundException();
                        } catch (ProductNotFoundException $e) {
                            if ($action === 'insert') {
                                $this->logger->info('Create product', $data->toArray());

                                try {
                                    $create = [];
                                    foreach ($data as $key => $value) {
                                        if (
                                            $key !== 'empty'
                                            && in_array($key, \App\Domain\References\Catalog::IMPORT_FIELDS, true)
                                            && !in_array($value, \App\Domain\References\Catalog::IMPORT_FIELDS, true)
                                            && ($value === null) === false
                                        ) {
                                            $create[$key] = $value['raw'];
                                        }
                                    }

                                    if ($create) {
                                        $catalogProductService->create(
                                            array_merge(
                                                $create,
                                                [
                                                    'category_uuid' => $category->uuid,
                                                    'date' => $now,
                                                    'export' => 'excel',
                                                    'attributes' => $data
                                                        ->intersectByKeys($attributes->pluck('title', 'address'))
                                                        ->map(fn ($el) => $el['raw'])
                                                        ->all(),
                                                ]
                                            )
                                        );
                                    }
                                } catch (MissingTitleValueException $e) {
                                    $this->logger->warning('Product wrong title value');
                                } catch (AddressAlreadyExistsException $e) {
                                    $this->logger->warning('Product wrong address value');
                                }
                            }
                        } finally {
                            if ($product) {
                                $this->logger->info('Update product data', [$key_field => @$data[$key_field]['formatted']]);

                                $update = [];
                                foreach ($data as $key => $value) {
                                    if (
                                        $key !== 'empty'
                                        && in_array($key, \App\Domain\References\Catalog::IMPORT_FIELDS, true)
                                        && !in_array($value, \App\Domain\References\Catalog::IMPORT_FIELDS, true)
                                        && ($value === null) === false
                                    ) {
                                        $update[$key] = $value['raw'];
                                    }
                                }
                                $catalogProductService->update($product, array_merge(
                                    $update,
                                    [
                                        'category' => $category,
                                        'date' => $now,
                                        'attributes' => $data
                                            ->intersectByKeys($attributes->pluck('title', 'address'))
                                            ->map(fn ($el) => $el['raw'])
                                            ->all(),
                                    ]
                                ));
                            }
                        }

                        $nested = false;

                        break;
                }

                $this->setProgress($index, count($data));
            }
        }

        // rm excel file
        $fileService->delete($file);

        $this->container->get(\App\Application\PubSub::class)->publish('task:catalog:import');

        return $this->setStatusDone();
    }

    protected function getCellIndex($column): int
    {
        $column = mb_strtoupper($column);
        $length = mb_strlen($column);
        $number = 0;

        for ($i = 0; $i < $length; ++$i) {
            $char = $column[$i];
            $value = ord($char) - ord('A') + 1;
            $number = $number * 26 + $value;
        }

        return $number - 1;
    }

    /**
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @throws \PhpOffice\PhpSpreadsheet\Reader\Exception
     */
    protected function getParsedExcelData(string $path = ''): array
    {
        $fields = trim($this->parameter('catalog_import_columns', implode(PHP_EOL, \App\Domain\References\Catalog::IMPORT_EXPORT_FIELDS_DEFAULT)));

        if ($fields) {
            $fields = array_map('trim', explode(PHP_EOL, $fields));
            $offset = [
                'rows' => max(0, +$this->parameter('catalog_import_export_offset_rows', 0)),
                'cols' => max(0, +$this->parameter('catalog_import_export_offset_cols', 0)),
            ];

            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);

            $output = [];
            foreach ($spreadsheet->getActiveSheet()->getRowIterator() as $row) {
                if ($row->getRowIndex() <= $offset['rows'] + 1) {
                    continue;
                }

                $empty = true;
                $buf = [
                    'type' => 'item',
                    'data' => [],
                ];

                foreach ($row->getCellIterator() as $column => $cell) {
                    $value = trim((string) $cell->getValue());

                    if ($this->isMergedCell($row->getWorksheet(), $cell)) {
                        $output[] = [
                            'type' => 'category',
                            'raw' => $value,
                            'formatted' => $cell->getFormattedValue(),
                            'trimmed' => trim($cell->getFormattedValue()),
                        ];

                        break;
                    }

                    $column = $this->getCellIndex($column) - $offset['cols'];
                    $empty = $empty === true && $cell->getValue() === null;

                    if ($column < 0) {
                        continue;
                    }
                    if ($column >= count($fields)) {
                        break;
                    }

                    if ($column !== 'empty') {
                        $buf['data'][$fields[$column]] = [
                            'raw' => $value,
                            'formatted' => $cell->getFormattedValue(),
                            'trimmed' => trim($cell->getFormattedValue()),
                        ];
                    }
                }

                if (!$empty) {
                    $output[] = $buf;
                }
            }

            return $output;
        }

        return [];
    }

    /**
     * Check cell is merged or not
     */
    protected function isMergedCell(Worksheet $sheet, Cell $cell): bool
    {
        foreach ($sheet->getMergeCells() as $cells) {
            if ($cell->isInRange($cells)) {
                return true;
            }
        }

        return false;
    }
}
