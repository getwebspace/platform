<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\Catalog\Order\Status;

use App\Application\Actions\Cup\Catalog\CatalogAction;

class OrderStatusDeleteAction extends CatalogAction
{
    protected function action(): \Slim\Psr7\Response
    {
        $os = null;

        if ($this->resolveArg('uuid') && \Ramsey\Uuid\Uuid::isValid($this->resolveArg('uuid'))) {
            $os = $this->catalogOrderStatusService->read([
                'uuid' => $this->resolveArg('uuid'),
            ]);

            if ($os) {
                $this->catalogOrderStatusService->delete($os);

                $this->container->get(\App\Application\PubSub::class)->publish('cup:catalog:order:status:delete', $os);
            }
        }

        return $this->respondWithRedirect('/cup/catalog/order');
    }
}
