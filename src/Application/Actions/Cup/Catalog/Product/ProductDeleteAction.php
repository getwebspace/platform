<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\Catalog\Product;

use App\Application\Actions\Cup\Catalog\CatalogAction;
use App\Domain\Service\Catalog\ProductService as CatalogProductService;

class ProductDeleteAction extends CatalogAction
{
    protected function action(): \Slim\Http\Response
    {
        $product = null;

        if ($this->resolveArg('product') && \Ramsey\Uuid\Uuid::isValid($this->resolveArg('product'))) {
            $catalogProductService = CatalogProductService::getWithContainer($this->container);
            $product = $catalogProductService->read(['uuid' => $this->resolveArg('product')]);
            $catalogProductService->delete($this->resolveArg('product'));
        }

        return $this->response->withRedirect('/cup/catalog/product' . ($product ? '/' . $product->getCategory() : ''));
    }
}
