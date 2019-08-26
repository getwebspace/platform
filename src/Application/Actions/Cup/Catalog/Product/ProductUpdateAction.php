<?php

namespace Application\Actions\Cup\Catalog\Product;

use Application\Actions\Cup\Catalog\CatalogAction;
use Exception;

class ProductUpdateAction extends CatalogAction
{
    protected function action(): \Slim\Http\Response
    {
        if ($this->resolveArg('product') && \Ramsey\Uuid\Uuid::isValid($this->resolveArg('product'))) {
            /** @var \Domain\Entities\Catalog\Product $product */
            $product = $this->productRepository->findOneBy(['uuid' => $this->resolveArg('product'), 'status' => \Domain\Types\Catalog\ProductStatusType::STATUS_WORK]);

            if (!$product->isEmpty()) {
                if ($this->request->isPost()) {
                    // remove uploaded image
                    if (($uuidFile = $this->request->getParam('delete-image')) !== null && \Ramsey\Uuid\Uuid::isValid($uuidFile)) {
                        /** @var \Domain\Entities\File $file */
                        $file = $this->fileRepository->findOneBy(['uuid' => $uuidFile]);

                        if (!$file->isEmpty()) {
                            try {
                                $this->entityManager->remove($file);
                                $this->entityManager->flush();
                            } catch (Exception $e) {
                                // todo nothing
                            }
                        }
                    } else {
                        $data = [
                            'uuid' => $product->uuid,
                            'category' => $this->request->getParam('category'),
                            'title' => $this->request->getParam('title'),
                            'description' => $this->request->getParam('description'),
                            'extra' => $this->request->getParam('extra'),
                            'address' => $this->request->getParam('address'),
                            'vendorcode' => $this->request->getParam('vendorcode'),
                            'barcode' => $this->request->getParam('barcode'),
                            'priceFirst' => $this->request->getParam('priceFirst'),
                            'price' => $this->request->getParam('price'),
                            'priceWholesale' => $this->request->getParam('priceWholesale'),
                            'volume' => $this->request->getParam('volume'),
                            'unit' => $this->request->getParam('unit'),
                            'stock' => $this->request->getParam('stock'),
                            'field1' => $this->request->getParam('field1'),
                            'field2' => $this->request->getParam('field2'),
                            'field3' => $this->request->getParam('field3'),
                            'field4' => $this->request->getParam('field4'),
                            'field5' => $this->request->getParam('field5'),
                            'country' => $this->request->getParam('country'),
                            'manufacturer' => $this->request->getParam('manufacturer'),
                            'order' => $this->request->getParam('order'),
                            'date' => $this->request->getParam('date'),
                            'external_id' => $this->request->getParam('external_id'),
                        ];

                        $check = \Domain\Filters\Catalog\Product::check($data);

                        if ($check === true) {
                            try {
                                $product->replace($data);
                                $this->entityManager->persist($product);
                                $this->handlerFileUpload($product);
                                $this->entityManager->flush();

                                return $this->response->withAddedHeader('Location', '/cup/catalog/product/' . $product->category);
                            } catch (Exception $e) {
                                // todo nothing
                            }
                        }
                    }
                }

                $categories = collect($this->categoryRepository->findAll());

                $files = collect($this->fileRepository->findBy([
                    'item' => \Domain\Types\FileItemType::ITEM_CATALOG_PRODUCT,
                    'item_uuid' => $product->uuid,
                ]));

                return $this->respondRender('cup/catalog/product/form.twig', [
                    'category' => $categories->firstWhere('uuid', $product->category),
                    'categories' => $categories,
                    'measure' => $this->getMeasure(),
                    'files' => $files,
                    'item' => $product,
                ]);
            }

            return $this->response->withAddedHeader('Location', '/cup/catalog/product/' . $product->category);
        }

        return $this->response->withAddedHeader('Location', '/cup/catalog');
    }
}
