<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\Catalog;

class CatalogImportAction extends CatalogAction
{
    protected function action(): \Slim\Psr7\Response
    {
        if ($this->isPost()) {
            // Fields
            $fields = array_map('trim', explode(PHP_EOL, $this->parameter('catalog_import_columns', '')));

            if ($fields) {
                /** @var \App\Domain\Entities\File $file */
                $file = array_first($this->getUploadedFiles('excel', 0));

                if ($file) {
                    // add import task
                    $task = new \App\Domain\Tasks\Catalog\ImportTask($this->container);
                    $task->execute(['file' => $file->getUuid()->toString()]);

                    // run worker
                    \App\Domain\AbstractTask::worker(); // run all queue
                }
            }
        }

        return $this->response->withAddedHeader('Location', $_SERVER['HTTP_REFERER'] ?? '/cup/catalog/product')->withStatus(301);
    }
}
