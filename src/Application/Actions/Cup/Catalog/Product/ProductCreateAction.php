<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\Catalog\Product;

use App\Application\Actions\Cup\Catalog\CatalogAction;
use App\Domain\Service\Catalog\Exception\AddressAlreadyExistsException;
use App\Domain\Service\Catalog\Exception\AttributeNotFoundException;
use App\Domain\Service\Catalog\Exception\MissingCategoryValueException;
use App\Domain\Service\Catalog\Exception\MissingTitleValueException;
use App\Domain\Service\Catalog\Exception\WrongTitleValueException;
use App\Domain\Casts\Reference\Type as ReferenceType;

class ProductCreateAction extends CatalogAction
{
    protected function action(): \Slim\Psr7\Response
    {
        $category = $this->getParam('category', false);

        if ($this->isPost()) {
            try {
                $product = $this->catalogProductService->create([
                    'category_uuid' => $this->getParam('category'),
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
                    'priceWholesaleFrom' => $this->getParam('priceWholesaleFrom'),
                    'discount' => $this->getParam('discount'),
                    'special' => $this->getParam('special'),
                    'quantity' => $this->getParam('quantity'),
                    'quantityMin' => $this->getParam('quantityMin'),
                    'dimension' => $this->getParam('dimension'),
                    'stock' => $this->getParam('stock'),
                    'country' => $this->getParam('country'),
                    'manufacturer' => $this->getParam('manufacturer'),
                    'tags' => $this->getParam('tags'),
                    'order' => $this->getParam('order'),
                    'date' => $this->getParam('date', 'now'),
                    'meta' => $this->getParam('meta'),
                    'external_id' => $this->getParam('external_id'),

                    'attributes' => $this->getParam('attributes', []),
                    'relation' => $this->getParam('relation', []),
                ]);
                $product = $this->processEntityFiles($product);

                $this->container->get(\App\Application\PubSub::class)->publish('cup:catalog:product:create', $product);

                switch (true) {
                    case $this->getParam('save', 'exit') === 'exit':
                        if ($category) {
                            return $this->respondWithRedirect('/cup/catalog/product/' . $category);
                        }

                        return $this->respondWithRedirect('/cup/catalog/product');

                    default:
                        return $this->respondWithRedirect('/cup/catalog/product/' . $product->uuid . '/edit');
                }
            } catch (MissingTitleValueException|WrongTitleValueException $e) {
                $this->addError('title', $e->getMessage());
            } catch (MissingCategoryValueException $e) {
                $this->addError('category', $e->getMessage());
            } catch (AddressAlreadyExistsException $e) {
                $this->addError('address', $e->getMessage());
            } catch (AttributeNotFoundException $e) {
                $this->addError('attributes', $e->getMessage());
            }
        }

        $categories = $this->catalogCategoryService->read([
            'status' => \App\Domain\Casts\Catalog\Status::WORK,
        ]);
        //$attributes = $this->catalogAttributeService->read();

        return $this->respondWithTemplate('cup/catalog/product/form.twig', [
            'categories' => $categories,
            'category' => $categories->firstWhere('uuid', $category),
            'attributes' => collect(), //$attributes,
            'manufacturers' => $this->referenceService->read(['type' => ReferenceType::MANUFACTURER, 'status' => true, 'order' => ['order' => 'asc']]),
            'tax_rates' => $this->referenceService->read(['type' => ReferenceType::TAX_RATE, 'status' => true, 'order' => ['order' => 'asc']]),
            'stock_status' => $this->referenceService->read(['type' => ReferenceType::STOCK_STATUS, 'status' => true, 'order' => ['order' => 'asc']]),
            'length_class' => $this->referenceService->read(['type' => ReferenceType::LENGTH_CLASS, 'status' => true, 'order' => ['order' => 'asc']]),
            'weight_class' => $this->referenceService->read(['type' => ReferenceType::WEIGHT_CLASS, 'status' => true, 'order' => ['order' => 'asc']]),
        ]);
    }
}
