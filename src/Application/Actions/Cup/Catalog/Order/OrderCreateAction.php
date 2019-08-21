<?php

namespace Application\Actions\Cup\Catalog\Order;

use AEngine\Support\Str;
use Application\Actions\Cup\Catalog\CatalogAction;
use Exception;

class OrderCreateAction extends CatalogAction
{
    protected function action(): \Slim\Http\Response
    {
        if ($this->resolveArg('uuid') && \Ramsey\Uuid\Uuid::isValid($this->resolveArg('uuid'))) {
            /** @var \Domain\Entities\Catalog\Category $category */
            $category = $this->categoryRepository->findOneBy(['uuid' => $this->resolveArg('uuid')]);

            if (!$category->isEmpty()) {
                if ($this->request->isPost()) {
                    $data = [
                        'category' => $category->uuid,
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
                        'external_id' => $this->request->getParam('external_id'),
                    ];

                    $check = \Domain\Filters\Catalog\Product::check($data);

                    if ($check === true) {
                        try {
                            $model = new \Domain\Entities\Catalog\Product($data);
                            $this->entityManager->persist($model);
                            $this->handlerFileUpload($model);
                            $this->entityManager->flush();

                            return $this->response->withAddedHeader('Location', '/cup/catalog/' . $category->uuid . '/product');
                        } catch (Exception $e) {
                            // todo nothing
                        }
                    }
                }
            }

            return $this->respondRender('cup/catalog/product/form.twig', ['category' => $category, 'measure' => $this->getMeasure()]);
        }

        return $this->response->withAddedHeader('Location', '/cup/catalog');
    }
}
