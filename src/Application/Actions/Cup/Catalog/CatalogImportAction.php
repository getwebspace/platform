<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\Catalog;

class CatalogImportAction extends CatalogAction
{
    protected function action(): \Slim\Http\Response
    {
        if ($this->request->isPost()) {
            // Fields
            $fields = array_map('trim', explode(PHP_EOL, $this->parameter('catalog_import_columns', '')));

            if ($fields) {
                /** @var \App\Domain\Entities\File $file */
                $file = array_first($this->getUploadedFiles('excel'));

                if ($file) {
                    // add import task
                    $task = new \App\Domain\Tasks\Catalog\ImportTask($this->container);
                    $task->execute(['file' => $file->getUuid()->toString()]);

                    $this->entityManager->flush();

                    // run worker
                    \App\Domain\AbstractTask::worker($task);
                }
            }
        }

        return $this->response->withAddedHeader('Location', $_SERVER['HTTP_REFERER'] ?? '/cup/catalog/product')->withStatus(301);
    }
}
