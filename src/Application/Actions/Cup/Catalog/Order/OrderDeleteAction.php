<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\Catalog\Order;

use App\Application\Actions\Cup\Catalog\CatalogAction;
use App\Domain\Service\Catalog\OrderService as CatalogOrderService;

class OrderDeleteAction extends CatalogAction
{
    protected function action(): \Slim\Http\Response
    {
        if ($this->resolveArg('order') && \Ramsey\Uuid\Uuid::isValid($this->resolveArg('order'))) {
            $this->catalogOrderService->delete($this->resolveArg('order'));
        }

        return $this->response->withRedirect('/cup/catalog/order');
    }
}
