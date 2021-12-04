<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\Catalog\Order;

use App\Application\Actions\Cup\Catalog\CatalogAction;

class OrderDeleteAction extends CatalogAction
{
    protected function action(): \Slim\Psr7\Response
    {
        if ($this->resolveArg('order') && \Ramsey\Uuid\Uuid::isValid($this->resolveArg('order'))) {
            $this->catalogOrderService->delete($this->resolveArg('order'));
        }

        return $this->respondWithRedirect('/cup/catalog/order');
    }
}
