<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\Catalog;

class CatalogScanAction extends CatalogAction
{
    protected function action(): \Slim\Http\Response
    {
        return $this->respondWithTemplate('cup/catalog/barcode-scan.twig');
    }
}
