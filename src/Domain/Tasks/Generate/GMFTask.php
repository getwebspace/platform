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
        $data = [
            'category' => collect($categoryRepository->findBy(['status' => \App\Domain\Types\Catalog\CategoryStatusType::STATUS_WORK])),
            'product' => collect($productRepository->findBy(['status' => \App\Domain\Types\Catalog\ProductStatusType::STATUS_WORK])),
        ];

        $feed = new Feed(
            $this->getParameter('integration_merchant_shop_title', 'Shop on WebSpace Engine CMS'),
            $this->getParameter('common_homepage', 'http://site.0x12f.com'),
            $this->getParameter('integration_merchant_shop_description', 'http://site.0x12f.com')
        );

        // Put products to the feed ($products - some data from database for example)
        foreach ($data['product'] as $model) {
            /** @var \App\Domain\Entities\Catalog\Category $category */
            /** @var \App\Domain\Entities\Catalog\Product $model */
            $category = $data['category']->firstWhere('uuid', $model->category);

            $url = $this->getParameter('common_homepage', 'http://site.0x12f.com/') . 'catalog/';
            if ($category) {
                $url .= $category->address;
            }
            $url .= '/' . $model->address;

            $item = new Product();

            // Set common product properties
            $item->setId($model->uuid->toString());
            $item->setTitle($model->title);
            if ($model->description) {
                $item->setDescription($model->description);
            }
            $item->setLink($url);
            if ($model->hasFiles()) {
                $item->setImage($model->getFiles()->first()->getPublicPath());
            }
            if ($model->stock > 0) {
                $item->setAvailability(Availability::IN_STOCK);
            } else {
                $item->setAvailability(Availability::OUT_OF_STOCK);
            }
            $item->setPrice("{$model->price} RUB");
            if ($category) {
                $item->setGoogleCategory($category->title);
            }
            if ($model->manufacturer) {
                $item->setBrand($model->manufacturer);
            }
            if ($model->barcode) {
                $item->setGtin($model->barcode);
            }
            $item->setCondition('new');

            // Add this product to the feed
            $feed->addProduct($item);
        }

        file_put_contents(VAR_DIR . '/xml/gmf.xml', $feed->build());

        $this->setStatusDone();
    }
}
