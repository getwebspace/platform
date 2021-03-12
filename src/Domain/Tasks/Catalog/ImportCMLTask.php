<?php declare(strict_types=1);

namespace App\Domain\Tasks\Catalog;

use App\Domain\AbstractTask;
use App\Domain\Service\Catalog\CategoryService as CatalogCatalogService;
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

        // rm excel file
        $fileService->delete($file);

        return $this->setStatusDone();
    }
}
