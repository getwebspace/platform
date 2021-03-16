<?php declare(strict_types=1);

namespace App\Domain\Tasks\Catalog;

use App\Domain\AbstractTask;
use App\Domain\Service\Catalog\AttributeService as CatalogAttributeService;
use App\Domain\Service\Catalog\CategoryService as CatalogCatalogService;
use App\Domain\Service\Catalog\Exception\ProductNotFoundException;
use App\Domain\Service\Catalog\ProductAttributeService as CatalogProductAttributeService;
use App\Domain\Service\Catalog\ProductService as CatalogProductService;
use App\Domain\Service\File\Exception\FileNotFoundException;
use App\Domain\Service\File\FileService;

class ImportCMLTask extends AbstractTask
{
    public const TITLE = 'Импорт из Commerce ML';

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
        $catalogAttributeService = CatalogAttributeService::getWithContainer($this->container);
        $catalogProductAttributeService = CatalogProductAttributeService::getWithContainer($this->container);

        $attributes = $catalogAttributeService->read();

        $comm = new \A_Gallyamov\CommerceML\CommerceML();
        $comm->addXmls(UPLOAD_DIR . '/import0_1.xml');

        foreach ($comm->getProducts() as $item) {
            try {
                $product = $catalogProductService->read(['external_id' => $item->id]);
            } catch (ProductNotFoundException $e) {
                $product = $catalogProductService->create([
                    'category' => \Ramsey\Uuid\Uuid::NIL,
                    'title' => $item->name,
                    'description' => $item->description,
                    'vendorcode' => $item->sku,
                    'barcode' => '',
                    'priceFirst' => 0.0,
                    'price' => 0.0,
                    'priceWholesale' => 0.0,
                    'volume' => 0.0,
                    'unit' => $item->unit,
                    'stock' => (float) $item->quantity,
                    'external_id' => $item->id,
                    'export' => '1c',
                ]);
            } finally {
                dump($product);
            }
        }

        // rm excel file
        $fileService->delete($file);

        return $this->setStatusDone();
    }
}
