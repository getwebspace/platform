<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\Catalog;

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
