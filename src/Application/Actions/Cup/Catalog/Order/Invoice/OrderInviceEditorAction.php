<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\Catalog\Order\Invoice;

use App\Application\Actions\Cup\Catalog\CatalogAction;
use App\Domain\Service\Catalog\Exception\MissingTitleValueException;

class OrderInviceEditorAction extends CatalogAction
{
    protected function action(): \Slim\Psr7\Response
    {
        return $this->respondWithTemplate('cup/catalog/order/invoice/index.twig', [
            'invoice' => $this->parameter('catalog_invoice', ''),
        ]);
    }
}
