<?php declare(strict_types=1);

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
            //$action = $this->getParameter('catalog_import_action', 'update');

            // todo здесь еще должна быть генерация категорий из прайслиста

            $key_field = $this->getParameter('catalog_import_key', null);
            $productRepository = $this->entityManager->getRepository(\App\Domain\Entities\Catalog\Product::class);

            foreach ($data['products'] as $item) {
                $this->logger->info('Search product', [$key_field => $item[$key_field]]);

                /** @var \App\Domain\Entities\Catalog\Product $product */
                $product = $productRepository->findOneBy([$key_field => [$item[$key_field], +$item[$key_field]]]);

                if ($product) {
                    $this->logger->info('Update product data', $item);

                    foreach ($item as $key => $value) {
                        if (
                            $key !== 'empty' &&
                            in_array($key, [
                                'uuid', 'category', 'external_id',
                                'title', 'description', 'extra',
                                'address',
                                'barcode', 'vendorcode',
                                'priceFirst', 'price', 'priceWholesale',
                                'volume', 'unit', 'stock',
                                'field1', 'field2', 'field3', 'field4', 'field5',
                                'country', 'manufacturer',
                                'order',
                            ], true) &&
                            ($value === null) === false
                        ) {
                            $product->set($key, $value);
                        }
                    }
                    $product->date = new \DateTime();
                }
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
        $fields = array_map('trim', explode(PHP_EOL, $this->getParameter('catalog_import_columns', '')));
        $offset = [
            'rows' => max(1, +$this->getParameter('catalog_import_offset_rows', 1)),
            'cols' => max(0, +$this->getParameter('catalog_import_offset_cols', 0)),
        ];

        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);

        $output = [
            'products' => [],
        ];
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

                $buf[$fields[$column]] = $cell->getValue();
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
