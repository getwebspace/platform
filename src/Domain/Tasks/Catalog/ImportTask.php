<?php declare(strict_types=1);

namespace App\Domain\Tasks\Catalog;

use App\Domain\AbstractTask;
use App\Domain\Service\Catalog\CategoryService as CatalogCatalogService;
use App\Domain\Service\Catalog\Exception\AddressAlreadyExistsException;
use App\Domain\Service\Catalog\Exception\CategoryNotFoundException;
use App\Domain\Service\Catalog\Exception\MissingTitleValueException;
use App\Domain\Service\Catalog\Exception\ProductNotFoundException;
use App\Domain\Service\Catalog\Exception\TitleAlreadyExistsException;
use App\Domain\Service\Catalog\ProductService as CatalogProductService;
use App\Domain\Service\File\Exception\FileNotFoundException;
use App\Domain\Service\File\FileService;

class ImportTask extends AbstractTask
{
    public const TITLE = 'Импорт каталога из Excel файла';

    public function execute(array $params = []): \App\Domain\Entities\Task
    {
        $default = [
            'file' => null,
        ];
        $params = array_merge($default, $params);

        return parent::execute($params);
    }

    protected function action(array $args = [])
    {
        $fileService = FileService::getWithContainer($this->container);

        try {
            $file = $fileService->read(['uuid' => $args['file']]);
        } catch (FileNotFoundException $e) {
            return $this->setStatusFail();
        }

        $catalogCategoryService = CatalogCatalogService::getWithContainer($this->container);
        $catalogProductService = CatalogProductService::getWithContainer($this->container);

        // parse excel file
        if (($data = $this->getParsedExcelData($file->getInternalPath())) !== []) {
            $action = $this->parameter('catalog_import_action', 'update');
            $key_field = $this->parameter('catalog_import_key', 'vendorcode');
            $pagination = $this->parameter('catalog_category_pagination', 10);
            $template = [
                'category' => $this->parameter('catalog_category_template', 'catalog.category.twig'),
                'product' => $this->parameter('catalog_product_template', 'catalog.product.twig'),
            ];

            $now = new \DateTime();
            $category = null;
            $nested = false;

            foreach ($data as $index => $item) {
                switch ($item['type']) {
                    case 'category':
                        if ($action === 'insert') {
                            try {
                                $this->logger->info('Search category', ['title' => $item]);
                                $category = $catalogCategoryService->read(['title' => $item]);
                            } catch (CategoryNotFoundException $e) {
                                $this->logger->info('Create category', ['title' => $item]);

                                try {
                                    $category = $catalogCategoryService->create([
                                        'title' => $item['title'],
                                        'parent' => $nested === true ? $category->getUuid() : \Ramsey\Uuid\Uuid::NIL,
                                        'pagination' => $pagination,
                                        'template' => $template,
                                        'export' => 'excel',
                                    ]);
                                } catch (TitleAlreadyExistsException|MissingTitleValueException $e) {
                                    $this->logger->warning('Category wrong title value');
                                } catch (AddressAlreadyExistsException $e) {
                                    $this->logger->warning('Category wrong address value');
                                }
                            }
                        }

                        $nested = true;

                        break;

                    case 'product':
                        $product = null;
                        $data = $item['data'] ?? [];

                        if (isset($data[$key_field])) {
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
                                    ])
                                    ->first();
                            } catch (ProductNotFoundException $e) {
                                if ($action === 'insert') {
                                    $this->logger->info('Create product', [$key_field => $data[$key_field]]);

                                    try {
                                        $create = [];
                                        foreach ($data as $key => $value) {
                                            if (
                                                $key !== 'empty' &&
                                                in_array($key, \App\Domain\References\Catalog::IMPORT_FIELDS, true) &&
                                                !in_array($value, \App\Domain\References\Catalog::IMPORT_FIELDS, true) &&
                                                ($value === null) === false
                                            ) {
                                                $create[$key] = $value['raw'];
                                            }
                                        }
                                        $product = $catalogProductService->create(
                                            array_merge(
                                                $create,
                                                [
                                                    'category' => $category->getUuid(),
                                                    'date' => $now,
                                                    'export' => 'excel',
                                                ]
                                            )
                                        );
                                    } catch (MissingTitleValueException|TitleAlreadyExistsException $e) {
                                        $this->logger->warning('Product wrong title value');
                                    } catch (AddressAlreadyExistsException $e) {
                                        $this->logger->warning('Product wrong address value');
                                    }

                                    continue 2;
                                }
                            } finally {
                                if ($product) {
                                    $this->logger->info('Update product data', [$key_field => $data[$key_field]['formatted']]);

                                    $update = ['date' => $now];
                                    foreach ($data as $key => $value) {
                                        if (
                                            $key !== 'empty' &&
                                            in_array($key, \App\Domain\References\Catalog::IMPORT_FIELDS, true) &&
                                            !in_array($value, \App\Domain\References\Catalog::IMPORT_FIELDS, true) &&
                                            ($value === null) === false
                                        ) {
                                            $update[$key] = $value['raw'];
                                        }
                                    }
                                    $catalogProductService->update($product, $update);
                                }
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

        return $this->setStatusDone();
    }

    protected function getCellIndex($index)
    {
        static $alphabet;

        if (!$alphabet) {
            $alphabet = range('A', 'Z');
        }

        return array_search($index, $alphabet, true);
    }

    /**
     * @param string $path
     *
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @throws \PhpOffice\PhpSpreadsheet\Reader\Exception
     *
     * @return array
     */
    protected function getParsedExcelData($path = '')
    {
        $fields = trim($this->parameter('catalog_import_columns', ''));

        if ($fields) {
            $fields = array_map('trim', explode(PHP_EOL, $fields));
            $offset = [
                'rows' => max(1, +$this->parameter('catalog_import_export_offset_rows', 1)),
                'cols' => max(0, +$this->parameter('catalog_import_export_offset_cols', 0)),
            ];

            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);

            $output = [];
            foreach ($spreadsheet->getActiveSheet()->getRowIterator() as $row) {
                if ($row->getRowIndex() <= $offset['rows'] + 1) {
                    continue;
                }

                $count = 0;
                $buf = [];
                foreach ($row->getCellIterator() as $column => $cell) {
                    $column = $this->getCellIndex($column) - $offset['cols'];

                    if ($column < 0) {
                        continue;
                    }
                    if ($column >= count($fields)) {
                        break;
                    }

                    $value = trim((string) $cell->getValue());

                    if ($value) {
                        if ($column !== 'empty') {
                            $buf[$fields[$column]] = [
                                'raw' => $value,
                                'formatted' => (string) $cell->getFormattedValue(),
                                'trimmed' => trim((string) $cell->getFormattedValue()),
                            ];
                        }
                        $count++;
                    }
                }

                switch ($count) {
                    case 1:
                        $output[] = ['type' => 'category', 'title' => array_first($buf)];

                        break;

                    case count($fields):
                        $output[] = ['type' => 'product', 'data' => $buf];

                        break;
                }
            }

            return $output;
        }

        return [];
    }
}
