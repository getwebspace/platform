<?php

namespace App\Domain\Tasks\Generate;

use Alksily\Entity\Collection;
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
            ->setUrl($this->getParameter('common_homepage', 'http://site.0x12f.com'))
            ->setEmail($this->getParameter('smtp_from', null))
            ->setPlatform('WebSpace Engine CMS');

        $currencies = [];
        $currencies[] = (new Currency())->setId($this->getParameter('integration_merchant_currency', 'RUB'))->setRate(1);

        $categories = [];
        foreach ($this->prepareCategory($data['category']->sortBy('title')) as $item) {
            $categories[$item['id']] = (new Category())
                ->setId($item['id'])
                ->setParentId($item['parent'])
                ->setName($item['title'])
            ;
        }

        $offers = [];
        foreach ($this->prepareProduct($data['product']) as $model) {
            /**
             * @var \App\Domain\Entities\Catalog\Category $category
             * @var \App\Domain\Entities\Catalog\Product  $model
             */
            $category = $data['category']->firstWhere('uuid', $model->category);

            $homepage = rtrim($this->getParameter('common_homepage', 'http://site.0x12f.com/'), '/');
            $url = $homepage . '/' . $this->getParameter('catalog_address', 'catalog') . '/' . $model->address;
            $pictures = [];

            foreach (
                $model->hasFiles() ?
                    $model->getFiles() :
                    (
                        $category->hasFiles() ?
                            $category->getFiles() :
                            []
                    ) as $file
            ) {
                /** @var \App\Domain\Entities\File $file */
                $pictures[] = $homepage . $file->getPublicPath();
            }

            $offers[$model->buf] = (new OfferSimple())
                ->setId($model->buf)
                ->setVendor($model->manufacturer ? $model->manufacturer : null)
                ->setVendorCode($model->vendorcode ? $model->vendorcode : null)
                ->setAvailable(!!$model->stock)
                ->setUrl($url)
                ->setPrice($model->price)
                ->setCurrencyId($this->getParameter('integration_merchant_currency', 'RUB'))
                ->setCategoryId($category->buf)
                ->setName($model->title)
                ->setDescription(
                    trim(strip_tags($model->description ? $model->description : ($model->extra ? $model->extra : $model->title)))
                )
                ->setPictures($pictures);
        }

        $deliveries = [];
        $deliveries[] = (new Delivery())
            ->setCost($this->getParameter('integration_merchant_delivery_cost', '0'))
            ->setDays($this->getParameter('integration_merchant_delivery_days', '0'));

        (new Generator($settings))->generate($shopInfo, $currencies, $categories, $offers, $deliveries);

        $this->setStatusDone();
    }

    protected $indexCategory = 0;
    protected function prepareCategory(Collection &$categories, $parent = \Ramsey\Uuid\Uuid::NIL)
    {
        $result = [];

        foreach ($categories->where('parent', $parent) as $model) {
            /** @var \App\Domain\Entities\Catalog\Category $model */
            $result[] = [
                'id' => $model->buf = ++$this->indexCategory,
                'parent' => $categories->firstWhere('uuid', $model->parent)->buf ?? null,
                'title' => $model->title,
            ];

            $result = array_merge($result, $this->prepareCategory($categories, $model->uuid));
        }

        return $result;
    }

    protected $indexProduct = 0;
    protected function prepareProduct(Collection $products)
    {
        foreach ($products as $model) {
            /** @var \App\Domain\Entities\Catalog\Product $model */
            $model->buf = ++$this->indexProduct;
        }

        return $products;
    }
}
