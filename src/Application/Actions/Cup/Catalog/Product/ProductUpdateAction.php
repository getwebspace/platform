<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\Catalog\Product;

use App\Application\Actions\Cup\Catalog\CatalogAction;
use App\Domain\Service\Catalog\Exception\AddressAlreadyExistsException;
use App\Domain\Service\Catalog\Exception\AttributeNotFoundException;
use App\Domain\Service\Catalog\Exception\MissingTitleValueException;

class ProductUpdateAction extends CatalogAction
{
    protected function action(): \Slim\Psr7\Response
    {
        if ($this->resolveArg('product') && \Ramsey\Uuid\Uuid::isValid($this->resolveArg('product'))) {
            $product = $this->catalogProductService->read([
                'uuid' => $this->resolveArg('product'),
                'status' => \App\Domain\Types\Catalog\ProductStatusType::STATUS_WORK,
            ]);

            if ($product) {
                if ($this->isPost()) {
                    try {
                        $product = $this->catalogProductService->update($product, [
                            'category' => $this->getParam('category'),
                            'title' => $this->getParam('title'),
                            'type' => $this->getParam('type'),
                            'description' => $this->getParam('description'),
                            'extra' => $this->getParam('extra'),
                            'address' => $this->getParam('address'),
                            'vendorcode' => $this->getParam('vendorcode'),
                            'barcode' => $this->getParam('barcode'),
                            'tax' => $this->getParam('tax'),
                            'priceFirst' => $this->getParam('priceFirst'),
                            'price' => $this->getParam('price'),
                            'priceWholesale' => $this->getParam('priceWholesale'),
                            'special' => $this->getParam('special'),
                            'volume' => $this->getParam('volume'),
                            'unit' => $this->getParam('unit'),
                            'stock' => $this->getParam('stock'),
                            'field1' => $this->getParam('field1'),
                            'field2' => $this->getParam('field2'),
                            'field3' => $this->getParam('field3'),
                            'field4' => $this->getParam('field4'),
                            'field5' => $this->getParam('field5'),
                            'country' => $this->getParam('country'),
                            'manufacturer' => $this->getParam('manufacturer'),
                            'tags' => $this->getParam('tags'),
                            'order' => $this->getParam('order'),
                            'date' => $this->getParam('date'),
                            'meta' => $this->getParam('meta'),
                            'external_id' => $this->getParam('external_id'),
                        ]);
                        $this->catalogProductAttributeService->proccess(
                            $product,
                            $this->getParam('attributes', [])
                        );
                        $this->catalogProductRelationService->proccess(
                            $product, $this->getParam('relation', [])
                        );
                        $product = $this->processEntityFiles($product);

                        switch (true) {
                            case $this->getParam('save', 'exit') === 'exit':
                                return $this->respondWithRedirect('/cup/catalog/product');

                            default:
                                return $this->respondWithRedirect('/cup/catalog/product/' . $product->getUuid() . '/edit');
                        }
                    } catch (MissingTitleValueException $e) {
                        $this->addError('title', $e->getMessage());
                    } catch (AddressAlreadyExistsException $e) {
                        $this->addError('address', $e->getMessage());
                    } catch (AttributeNotFoundException $e) {
                        $this->addError('attribute', $e->getMessage());
                    }
                }

                $categories = $this->catalogCategoryService->read([
                    'status' => \App\Domain\Types\Catalog\CategoryStatusType::STATUS_WORK,
                ]);
                $attributes = $this->catalogAttributeService->read();

                return $this->respondWithTemplate('cup/catalog/product/form.twig', [
                    'category' => $categories->firstWhere('uuid', $product->getCategory()),
                    'categories' => $categories,
                    'attributes' => $attributes,
                    'measure' => $this->getMeasure(),
                    'item' => $product,
                ]);
            }

            return $this->respondWithRedirect('/cup/catalog/product/' . $product->getCategory());
        }

        return $this->respondWithRedirect('/cup/catalog/product');
    }
}
