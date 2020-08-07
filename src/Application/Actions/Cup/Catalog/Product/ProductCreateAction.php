<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\Catalog\Product;

use App\Application\Actions\Cup\Catalog\CatalogAction;
use App\Domain\Service\Catalog\Exception\AddressAlreadyExistsException;
use App\Domain\Service\Catalog\Exception\MissingTitleValueException;
use App\Domain\Service\Catalog\Exception\TitleAlreadyExistsException;

class ProductCreateAction extends CatalogAction
{
    protected function action(): \Slim\Http\Response
    {
        $category = $this->request->getParam('category', false);

        if ($this->request->isPost()) {
            try {
                $product = $this->catalogProductService->create([
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
                ]);
                $product = $this->handlerEntityFiles($product);

                switch (true) {
                    case $this->request->getParam('save', 'exit') === 'exit':
                        return $this->response->withRedirect('/cup/catalog/product');
                    default:
                        return $this->response->withRedirect('/cup/catalog/product/' . $product->getUuid() . '/edit');
                }
            } catch (TitleAlreadyExistsException|MissingTitleValueException $e) {
                $this->addError('title', $e->getMessage());
            } catch (AddressAlreadyExistsException $e) {
                $this->addError('address', $e->getMessage());
            }
        }

        $categories = $this->catalogCategoryService->read([
            'status' => \App\Domain\Types\Catalog\CategoryStatusType::STATUS_WORK,
        ]);

        return $this->respondWithTemplate('cup/catalog/product/form.twig', [
            'category' => $categories->firstWhere('uuid', $category),
            'categories' => $categories,
            'measure' => $this->getMeasure(),
        ]);
    }
}
