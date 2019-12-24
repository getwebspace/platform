<?php

namespace App\Domain\Tasks\Generate;

use App\Domain\Tasks\Task;
use Bukashk0zzz\YmlGenerator\Generator;
use Bukashk0zzz\YmlGenerator\Model\Category;
use Bukashk0zzz\YmlGenerator\Model\Currency;
use Bukashk0zzz\YmlGenerator\Model\Delivery;
use Bukashk0zzz\YmlGenerator\Model\Offer\OfferSimple;
use Bukashk0zzz\YmlGenerator\Model\ShopInfo;
use Bukashk0zzz\YmlGenerator\Settings;

class YMLTask extends Task
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
         */
        $categoryRepository = $this->entityManager->getRepository(\App\Domain\Entities\Catalog\Category::class);
        $productRepository = $this->entityManager->getRepository(\App\Domain\Entities\Catalog\Product::class);
        $data = [
            'category' => collect($categoryRepository->findBy(['status' => \App\Domain\Types\Catalog\CategoryStatusType::STATUS_WORK])),
            'product' => collect($productRepository->findBy(['status' => \App\Domain\Types\Catalog\ProductStatusType::STATUS_WORK])),
        ];

        $settings = new Settings();
        $settings
            ->setOutputFile(VAR_DIR . '/xml/yml.xml')
            ->setEncoding('UTF-8');

        $shopInfo = new ShopInfo();
        $shopInfo
            ->setName($this->getParameter('integration_merchant_shop_title', 'Shop on WebSpace Engine CMS'))
            ->setCompany($this->getParameter('integration_merchant_company_title', 'My own company'))
            ->setUrl($this->getParameter('common_homepage', 'http://site.0x12f.com'));

        $currencies = [];
        $currencies[] = (new Currency())->setId($this->getParameter('integration_merchant_currency', 'RUB'))->setRate(1);

        $categories = [];
        foreach ($data['category'] as $model) {
            /** @var \App\Domain\Entities\Catalog\Category $model */
            $categories[] = (new Category())
                ->setId($model->uuid->toString())
                ->setParentId($model->parent->toString())
                ->setName($model->title);
        }

        $offers = [];
        foreach ($data['product'] as $model) {
            /** @var \App\Domain\Entities\Catalog\Product $model */
            $category = $data['category']->firstWhere('uuid', $model->category);

            $url = $this->getParameter('common_homepage', 'http://site.0x12f.com/') . 'catalog/';
            if ($category) {
                $url .= $category->address;
            }
            $url .= '/' . $model->address;

            $offers[] = (new OfferSimple())
                ->setId($model->uuid->toString())
                ->setAvailable(!!$model->stock)
                ->setUrl($url)
                ->setPrice($model->price)
                ->setCurrencyId($this->getParameter('integration_merchant_currency', 'RUB'))
                ->setCategoryId($model->category->toString())
                ->setName($model->title);
        }

        $deliveries = [];
        $deliveries[] = (new Delivery())
            ->setCost($this->getParameter('integration_merchant_delivery_cost', '0'))
            ->setDays($this->getParameter('integration_merchant_delivery_days', '0'));

        (new Generator($settings))->generate($shopInfo, $currencies, $categories, $offers, $deliveries);

        $this->status_done();
    }
}
