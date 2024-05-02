<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\Catalog\Product;

use App\Application\Actions\Cup\Catalog\CatalogAction;

class ProductImportAction extends CatalogAction
{
    protected function action(): \Slim\Psr7\Response
    {
        if ($this->isPost()) {
            // Fields
            $fields = array_map('trim', explode(PHP_EOL, $this->parameter('catalog_import_columns', '')));

            if ($fields) {
                /** @var \App\Domain\Models\File $file */
                $file = array_first($this->getUploadedFiles('excel', 0));

                if ($file) {
                    // add import task
                    $task = new \App\Domain\Tasks\Catalog\ProductImportTask($this->container);
                    $task->execute([
                        'fields' => $fields, // todo check is later
                        'file' => $file->uuid,
                    ]);

                    // run worker
                    \App\Domain\AbstractTask::worker($task);
                }
            }
        }

        return $this->response->withAddedHeader('Location', $_SERVER['HTTP_REFERER'] ?? '/cup/catalog/product')->withStatus(301);
    }
}
