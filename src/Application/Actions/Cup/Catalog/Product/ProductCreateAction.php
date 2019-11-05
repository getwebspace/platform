<?php

namespace App\Application\Actions\Cup\Catalog\Product;

use App\Application\Actions\Cup\Catalog\CatalogAction;
use Exception;

class ProductCreateAction extends CatalogAction
{
    protected function action(): \Slim\Http\Response
    {
        $category = $this->request->getParam('category', false);

        if ($this->request->isPost()) {
            $data = [
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
                'tags' => $this->request->getParam('tags'),
                'order' => $this->request->getParam('order'),
                'date' => $this->request->getParam('date'),
                'external_id' => $this->request->getParam('external_id'),
            ];

            $check = \App\Domain\Filters\Catalog\Product::check($data);

            if ($check === true) {
                $model = new \App\Domain\Entities\Catalog\Product($data);
                $this->entityManager->persist($model);
                $this->handlerFileUpload(\App\Domain\Types\FileItemType::ITEM_CATALOG_PRODUCT, $model->uuid);
                $this->entityManager->flush();

                switch (true) {
                    case $this->request->getParam('save', 'exit') === 'exit':
                        return $this->response->withAddedHeader('Location', '/cup/catalog/product/' . $model->category)->withStatus(301);
                    default:
                        return $this->response->withAddedHeader('Location', '/cup/catalog/product/' . $model->uuid . '/edit')->withStatus(301);
                }
            } else {
                $this->addErrorFromCheck($check);
            }
        }

        $categories = collect($this->categoryRepository->findAll());

        return $this->respondRender('cup/catalog/product/form.twig', [
            'category' => $categories->firstWhere('uuid', $category),
            'categories' => $categories,
            'measure' => $this->getMeasure()
        ]);
    }
}
