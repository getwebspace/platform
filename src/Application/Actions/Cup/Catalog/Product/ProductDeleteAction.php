<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\Catalog\Product;

use App\Application\Actions\Cup\Catalog\CatalogAction;

class ProductDeleteAction extends CatalogAction
{
    protected function action(): \Slim\Psr7\Response
    {
        $product = null;

        if ($this->resolveArg('product') && \Ramsey\Uuid\Uuid::isValid($this->resolveArg('product'))) {
            $product = $this->catalogProductService->read([
                'uuid' => $this->resolveArg('product'),
                'status' => \App\Domain\Types\Catalog\ProductStatusType::STATUS_WORK,
            ]);

            if ($product) {
                $product->setStatus(\App\Domain\Types\Catalog\ProductStatusType::STATUS_DELETE);
                $this->catalogProductService->write($product);
            }
        }

        return $this->respondWithRedirect('/cup/catalog/product' . ($product ? '/' . $product->getCategory() : ''));
    }
}
