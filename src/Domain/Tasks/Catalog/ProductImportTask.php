<?php

namespace App\Domain\Tasks\Catalog;

use App\Domain\Tasks\Task;

class ProductImportTask extends Task
{
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
        $data = $this->getParsedExcelData($file->getInternalPath());

        if ($data) {
            $action = $this->getParameter('catalog_import_action', 'update');

            // todo здесь еще должна быть генерация категорий из прайслиста

            $key_field = $this->getParameter('catalog_import_key', null);
            $productRepository = $this->entityManager->getRepository(\App\Domain\Entities\Catalog\Product::class);
            foreach ($data['products'] as $item) {
                /** @var \App\Domain\Entities\Catalog\Product $product */
                $product = $productRepository->findOneBy([$key_field => [$item[$key_field], +$item[$key_field]]]);

                if ($action === 'insert_update') {
                    if (!$product) {
                        $this->logger->info('Import new product', [$key_field => $item[$key_field]]);

                        $default = [
                            'category' => \Ramsey\Uuid\Uuid::NIL,
                            'title' => '', 'description' => '', 'extra' => '',
                            'address' => '',
                            'vendorcode' => '', 'barcode' => '',
                            'priceFirst' => '', 'price' => '', 'priceWholesale' => '',
                            'volume' => '', 'unit' => '', 'stock' => '',
                            'field1' => '', 'field2' => '', 'field3' => '', 'field4' => '', 'field5' => '',
                            'country' => '', 'manufacturer' => '',
                            'tags' => '', 'order' => '', 'date' => '',
                            'external_id' => '',
                        ];
                        $item = array_merge($default, $item);

                        $check = \App\Domain\Filters\Catalog\Product::check($item);

                        if ($check === true) {
                            $product = new \App\Domain\Entities\Catalog\Product();
                            $this->entityManager->persist($product);
                        } else {
                            $this->logger->warning('Import invalid product', ['ch' => $check, 'i' => $item]);

                            return $this->setStatusFail();
                        }
                    }
                }

                if ($product) {
                    $this->logger->info('Update product data', $item);

                    foreach ($item as $key => $value) {
                        if (in_array($key, [
                            'uuid', 'category', 'external_id',
                            'title', 'description', 'extra',
                            'address',
                            'barcode', 'vendorcode',
                            'priceFirst', 'price', 'priceWholesale',
                            'volume', 'unit', 'stock',
                            'field1', 'field2', 'field3', 'field4', 'field5',
                            'country', 'manufacturer',
                            'order',
                        ])) {
                            $this->logger->info('Update product data', $item);
                            $product->set($key, $value);
                        }
                    }
                    $product->date = new \DateTime();
                }
            }
        }

        // rm file
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

        return array_search($index, $alphabet);
    }

    /**
     * @param string $path
     *
     * @return array
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @throws \PhpOffice\PhpSpreadsheet\Reader\Exception
     */
    protected function getParsedExcelData($path = '')
    {
        $fields = array_map('trim', explode(PHP_EOL, $this->getParameter('catalog_import_columns', '')));
        $offset = [
            'rows' => +$this->getParameter('catalog_import_offset_rows', 0),
            'cols' => +$this->getParameter('catalog_import_offset_cols', 0),
        ];

        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);

        $output = [
            'products' => [],
        ];
        foreach ($spreadsheet->getActiveSheet()->getRowIterator() as $row) {
            if ($row->getRowIndex() < $offset['rows']) continue;

            $buf = [];
            foreach ($row->getCellIterator() as $column => $cell) {
                $column = $this->getCellIndex($column) - $offset['cols'];

                if ($column < 0) continue;
                if ($column >= count($fields)) break;

                $value = trim($cell->getValue());

                if ($value) {
                    $buf[$fields[$column]] = $value;
                }
            }

            switch (count($buf)) {
                case 1:
                    // todo добавить генерацию категорий из прайс листа
                    break;

                case count($fields):
                    $output['products'][] = $buf;
                    break;
            }
        }

        return $output;
    }
}
