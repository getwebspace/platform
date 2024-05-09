<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\Catalog\Product;

use App\Application\Actions\Cup\Catalog\CatalogAction;
use App\Domain\Service\Catalog\Exception\ProductNotFoundException;

class ProductDeleteAction extends CatalogAction
{
    protected function action(): \Slim\Psr7\Response
    {
        $product = null;

        if ($this->resolveArg('product') && \Ramsey\Uuid\Uuid::isValid($this->resolveArg('product'))) {
            try {
                $product = $this->catalogProductService->read([
                    'uuid' => $this->resolveArg('product'),
                    'status' => \App\Domain\Casts\Catalog\Status::WORK,
                ]);

                if ($product) {
                    $this->catalogProductService->update($product, [
                        'status' => \App\Domain\Casts\Catalog\Status::DELETE,
                    ]);

                    $this->container->get(\App\Application\PubSub::class)->publish('cup:catalog:product:delete', $product);
                }

                return $this->respondWithRedirect('/cup/catalog/product' . ($product ? '/' . $product->category->uuid : ''));
            } catch (ProductNotFoundException $e) {
                // nothing
            }
        }

        return $this->respondWithRedirect('/cup/catalog/product');
    }
}
