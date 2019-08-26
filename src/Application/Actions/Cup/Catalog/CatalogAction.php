<?php

namespace App\Application\Actions\Cup\Catalog;

use AEngine\Support\Str;
use App\Application\Actions\Action;
use Psr\Container\ContainerInterface;

abstract class CatalogAction extends Action
{
    /**
     * @var \Doctrine\Common\Persistence\ObjectRepository|\Doctrine\ORM\EntityRepository
     */
    protected $categoryRepository;

    /**
     * @var \Doctrine\Common\Persistence\ObjectRepository|\Doctrine\ORM\EntityRepository
     */
    protected $productRepository;

    /**
     * @var \Doctrine\Common\Persistence\ObjectRepository|\Doctrine\ORM\EntityRepository
     */
    protected $orderRepository;

    /**
     * @var \Doctrine\Common\Persistence\ObjectRepository|\Doctrine\ORM\EntityRepository
     */
    protected $fileRepository;

    /**
     * @inheritDoc
     */
    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);

        $this->categoryRepository = $this->entityManager->getRepository(\App\Domain\Entities\Catalog\Category::class);
        $this->productRepository = $this->entityManager->getRepository(\App\Domain\Entities\Catalog\Product::class);
        $this->orderRepository = $this->entityManager->getRepository(\App\Domain\Entities\Catalog\Order::class);
        $this->fileRepository = $this->entityManager->getRepository(\App\Domain\Entities\File::class);
    }

    /**
     * Upload image files
     *
     * @param $model
     *
     * @throws \Doctrine\ORM\ORMException
     */
    protected function handlerFileUpload($model)
    {
        /** @var \Psr\Http\Message\UploadedFileInterface[] $files */
        $files = $this->request->getUploadedFiles()['files'] ?? [];

        foreach ($files as $file) {
            if ($file->getSize() && !$file->getError()) {
                $salt = uniqid();
                $name = Str::translate(strtolower($file->getClientFilename()));
                $path = UPLOAD_DIR . '/' . $salt;

                if (!file_exists($path)) {
                    mkdir($path);
                }

                // create model
                $fileModel = new \App\Domain\Entities\File([
                    'name' => $name,
                    'type' => $file->getClientMediaType(),
                    'size' => (int)$file->getSize(),
                    'salt' => $salt,
                    'date' => new \DateTime(),
                    'item' => is_a($model, \App\Domain\Entities\Catalog\Category::class) ? \App\Domain\Types\FileItemType::ITEM_CATALOG_CATEGORY : \App\Domain\Types\FileItemType::ITEM_CATALOG_PRODUCT,
                    'item_uuid' => $model->uuid,
                ]);

                $file->moveTo($path . '/' . $name);
                $fileModel->set('hash', sha1_file($path . '/' . $name));

                // save model
                $this->entityManager->persist($fileModel);
            }
        }
    }

    /**
     * @param bool $list
     * if false return key:value
     * if true return key:list
     *
     * @return array|false
     */
    protected function getMeasure($list = false) {
        $measure = $this->getParameter('catalog_measure');
        $result = [];

        if ($measure) {
            preg_match_all('/([\w\d]+)\:\s?([\w\d]+)\;\s?([\w\d]+)\;\s?([\w\d]+)(?>\s|$)/u', $measure, $matches);

            foreach ($matches[1] as $index => $key) {
                $result[$key] = $list ? [$matches[2][$index], $matches[3][$index], $matches[4][$index]] : $matches[2][$index];
            }
        }

        return collect($result);
    }
}
