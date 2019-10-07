<?php

namespace App\Domain\Tasks\Generate;

use App\Domain\Tasks\Task;
use Vitalybaev\GoogleMerchant\Feed;
use Vitalybaev\GoogleMerchant\Product;
use Vitalybaev\GoogleMerchant\Product\Availability\Availability;

class GMFTask extends Task
{
    public function execute(array $params = []): \App\Domain\Entities\Task
    {
        $default = [
            // nothing
        ];
        $params = array_merge($default, $params);

        return parent::execute($params);
    }

    protected function action(array $args = [])
    {
        /**
         * @var \Doctrine\Common\Persistence\ObjectRepository|\Doctrine\ORM\EntityRepository $categoryRepository
         * @var \Doctrine\Common\Persistence\ObjectRepository|\Doctrine\ORM\EntityRepository $productRepository
         * @var \Doctrine\Common\Persistence\ObjectRepository|\Doctrine\ORM\EntityRepository $fileRepository
         */
        $categoryRepository = $this->entityManager->getRepository(\App\Domain\Entities\Catalog\Category::class);
        $productRepository = $this->entityManager->getRepository(\App\Domain\Entities\Catalog\Product::class);
        $fileRepository = $this->entityManager->getRepository(\App\Domain\Entities\File::class);
        $data = [
            'category' => collect($categoryRepository->findAll()),
            'product' => collect($productRepository->findAll()),
        ];
        $data['file'] = collect($fileRepository->findBy(['item' => 'catalog_product', 'item_uuid' => $data['product']->pluck('uuid')->all()]));

        $feed = new Feed(
            $this->getParameter('integration_merchant_shop_title', 'Shop on CMS 0x12f'),
            $this->getParameter('common_homepage', 'http://shop.0x12f.com'),
            $this->getParameter('integration_merchant_shop_description', 'http://shop.0x12f.com')
        );

        // Put products to the feed ($products - some data from database for example)
        foreach ($data['product'] as $model) {
            /** @var \App\Domain\Entities\Catalog\Category $category */
            /** @var \App\Domain\Entities\Catalog\Product $model */
            $category = $data['category']->firstWhere('uuid', $model->category);

            $url = $this->getParameter('common_homepage', 'http://shop.0x12f.com/') . 'catalog/';
            if ($category) {
                $url .= $category->address;
            }
            $url .= '/' . $model->address;

            $item = new Product();

            // Set common product properties
            $item->setId($model->uuid->toString());
            $item->setTitle($model->title);
            $item->setDescription($model->description);
            $item->setLink($url);
            $item->setImage($data['file']->firstWhere('item_uuid', $model->uuid));
            if ($model->stock > 0) {
                $item->setAvailability(Availability::IN_STOCK);
            } else {
                $item->setAvailability(Availability::OUT_OF_STOCK);
            }
            $item->setPrice("{$model->price} USD");
            if ($category) {
                $item->setGoogleCategory($category->title);
            }
            $item->setBrand($model->manufacturer);
            $item->setGtin($model->barcode);
            $item->setCondition('new');

            // Add this product to the feed
            $feed->addProduct($item);
        }

        file_put_contents(PUBLIC_DIR . '/gmf.xml', $feed->build());

        $this->status_done();
    }
}
