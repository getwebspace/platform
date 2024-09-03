<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\Catalog\Order;

use App\Application\Actions\Cup\Catalog\CatalogAction;

class OrderDocumentAction extends CatalogAction
{
    protected function action(): \Slim\Psr7\Response
    {
        if (
            $this->resolveArg('order') && \Ramsey\Uuid\Uuid::isValid($this->resolveArg('order'))
            && $this->resolveArg('uuid') && \Ramsey\Uuid\Uuid::isValid($this->resolveArg('uuid'))
        ) {
            $order = $this->catalogOrderService->read(['uuid' => $this->resolveArg('order')]);

            if ($order) {
                $document = $this->referenceService->read(['uuid' => $this->resolveArg('uuid')]);

                return $this->respondWithTemplate('cup/catalog/order/document-view.twig', [
                    'order' => $order,
                    'document' => $document,
                    'template' => $document->value['template'] ?? '',
                ]);
            }
        }

        return $this->respondWithRedirect('/cup/catalog/order');
    }
}
