<?php

namespace App\Application\Actions\Cup\Catalog;

use App\Application\Actions\Cup\Catalog\CatalogAction;

class CatalogScanAction extends CatalogAction
{
    protected function action(): \Slim\Http\Response
    {
        $channel = $this->resolveArg('channel');

        if ($this->container->get('pushstream')->isOnline($channel)) {
            return $this->respondRender('cup/catalog/barcode-scan.twig', ['channel' => $channel]);
        }

        return $this->response->withAddedHeader('Location', '/cup/catalog/product')->withStatus(301);
    }
}
