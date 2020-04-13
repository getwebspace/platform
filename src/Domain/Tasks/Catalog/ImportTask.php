<?php declare(strict_types=1);

namespace App\Domain\Tasks\Catalog;

use App\Domain\Tasks\Task;

class ImportTask extends Task
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
        /** @var \App\Domain\Entities\File $file */
        $file = $this->entityManager->getRepository(\App\Domain\Entities\File::class)->findOneBy([
            'uuid' => $args['file'],
        ]);

        // parse excel file
        if (($data = $this->getParsedExcelData($file->getInternalPath())) !== []) {
            $action = $this->getParameter('catalog_import_action', 'update');
            $key_field = $this->getParameter('catalog_import_key', 'vendorcode');
            $pagination = $this->getParameter('catalog_category_pagination', 10);
            $template = [
                'category' => $this->getParameter('catalog_category_template', 'catalog.category.twig'),
                'product' => $this->getParameter('catalog_product_template', 'catalog.product.twig'),
            ];

            $now = new \DateTime();

            $categoryRepository = $this->entityManager->getRepository(\App\Domain\Entities\Catalog\Category::class);
            $productRepository = $this->entityManager->getRepository(\App\Domain\Entities\Catalog\Product::class);

            /** @var \App\Domain\Entities\Catalog\Category $lastCategory */
            $lastCategory = null;
            $nested = false;

            foreach ($data as $index => $item) {
                switch ($item['type']) {
                    case 'category':
                        if ($action === 'insert') {
                            $this->logger->info('Search category', ['title' => $item]);

                            /** @var \App\Domain\Entities\Catalog\Category $category */
                            $category = $categoryRepository->findOneBy(['title' => $item]);

                            if (!$category) {
                                $this->logger->info('Create category', ['title' => $item]);

                                $data = [
                                    'title' => $item['title'],
                                    'parent' => $nested === true ? $lastCategory->uuid : \Ramsey\Uuid\Uuid::NIL,
                                    'description' => '',
                                    'field1' => '', 'field2' => '', 'field3' => '',
                                    'pagination' => $pagination,
                                    'order' => 1,
                                    'template' => $template,
                                    'external_id' => '',
                                    'export' => 'excel',
                                ];
                                $check = \App\Domain\Filters\Catalog\Category::check($data);

                                if ($check === true) {
                                    $lastCategory = $category = new \App\Domain\Entities\Catalog\Category($data);
                                    $this->entityManager->persist($category);
                                } else {
                                    $this->logger->warning('Catalog wrong data', $check);
                                }
                            }
                        }

                        $nested = true;

                        break;

                    case 'product':
                        $data = $item['data'] ?? [];

                        if (isset($data[$key_field])) {
                            $this->logger->info('Search product', [$key_field => $data[$key_field], 'item' => $data]);

                            /** @var \App\Domain\Entities\Catalog\Product $product */
                            $product = $productRepository->findOneBy([$key_field => [$data[$key_field], +$data[$key_field]]]);

                            if (!$product && $action === 'insert') {
                                $this->logger->info('Create product', [$key_field => $data[$key_field]]);

                                $data = array_merge([
                                    'title' => '',
                                    'external_id' => '',
                                    'category' => $lastCategory->uuid,
                                    'description' => '', 'extra' => '',
                                    'address' => '',
                                    'vendorcode' => '', 'barcode' => '',
                                    'priceFirst' => '', 'price' => '', 'priceWholesale' => '',
                                    'volume' => '', 'unit' => '', 'stock' => '',
                                    'field1' => '', 'field2' => '', 'field3' => '', 'field4' => '', 'field5' => '',
                                    'country' => '', 'manufacturer' => '',
                                    'order' => 1,
                                    'date' => $now,
                                    'export' => 'excel',
                                ], $data);

                                $check = \App\Domain\Filters\Catalog\Product::check($data);

                                if ($check === true) {
                                    $product = new \App\Domain\Entities\Catalog\Product();
                                    $this->entityManager->persist($product);
                                } else {
                                    $this->logger->warning('Product wrong data', $check);
                                }
                            }

                            if ($product) {
                                $this->logger->info('Update product data', [$key_field => $data[$key_field]]);

                                foreach ($data as $key => $value) {
                                    if (
                                        $key !== 'empty' &&
                                        in_array($key, \App\Domain\References\Catalog::IMPORT_FIELDS, true) &&
                                        ($value === null) === false
                                    ) {
                                        $product->set($key, $value);
                                    }
                                }
                                $product->date = $now;
                            }
                        }

                        $nested = false;

                        break;
                }

                $this->setProgress($index, count($data));
            }
        }

        // rm excel file
        $file->unlink();
        $this->entityManager->remove($file);
        $this->entityManager->flush();

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
        $fields = trim($this->getParameter('catalog_import_columns', ''));

        if ($fields) {
            $fields = array_map('trim', explode(PHP_EOL, $fields));
            $offset = [
                'rows' => max(1, +$this->getParameter('catalog_import_export_offset_rows', 1)),
                'cols' => max(0, +$this->getParameter('catalog_import_export_offset_cols', 0)),
            ];

            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);

            $output = [];
            foreach ($spreadsheet->getActiveSheet()->getRowIterator() as $row) {
                if ($row->getRowIndex() < $offset['rows'] + 1) {
                    continue;
                }

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
                        $buf[$fields[$column]] = $value;
                    }
                }

                switch (count($buf)) {
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
