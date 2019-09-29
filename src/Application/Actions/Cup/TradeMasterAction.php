<?php

namespace App\Application\Actions\Cup;

use App\Application\Actions\Action;

class TradeMasterAction extends Action
{
    protected function action(): \Slim\Http\Response
    {
        switch ($this->request->getParam('action', null)) {
            case 'sync':
                $task = new \App\Domain\Tasks\TradeMaster\CatalogSyncTask($this->container);
                $task->execute([]);
                $this->entityManager->flush();

                exec('php ' . CONFIG_DIR . '/cli-task.php > /dev/null 2>&1 &');

                return $this->response->withAddedHeader('Location', '/cup/catalog/category');
        }

        return $this->response->withAddedHeader('Location', '/cup');
    }
}
