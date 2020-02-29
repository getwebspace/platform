<?php

namespace App\Domain\Tasks\TradeMaster;

use App\Domain\Tasks\Task;

class DownloadImageTask extends Task
{
    public function execute(array $params = []): \App\Domain\Entities\Task
    {
        $default = [
            'photo' => '',
            'type' => '',
            'uuid' => '',
        ];
        $params = array_merge($default, $params);

        return parent::execute($params);
    }

    /**
     * @var \App\Application\TradeMaster
     */
    protected $trademaster;

    /**
     * @var \Doctrine\Common\Persistence\ObjectRepository|\Doctrine\ORM\EntityRepository
     */
    private $catalogCategoryRepository;

    /**
     * @var \Doctrine\Common\Persistence\ObjectRepository|\Doctrine\ORM\EntityRepository
     */
    private $catalogProductRepository;

    /**
     * @var \Doctrine\Common\Persistence\ObjectRepository|\Doctrine\ORM\EntityRepository
     */
    protected $fileRepository;

    protected function action(array $args = [])
    {
        $this->trademaster = $this->container->get('trademaster');
        $this->catalogCategoryRepository = $this->entityManager->getRepository(\App\Domain\Entities\Catalog\Category::class);
        $this->catalogProductRepository = $this->entityManager->getRepository(\App\Domain\Entities\Catalog\Product::class);
        $this->fileRepository = $this->entityManager->getRepository(\App\Domain\Entities\File::class);

        if ($this->getParameter('file_is_enabled', 'no') === 'yes') {
            if ($args['photo']) {
                /**
                 * @var \App\Domain\Entities\Catalog\Category|\App\Domain\Entities\Catalog\Product $model
                 */
                switch ($args['type']) {
                    case 'category':
                        $entity = $this->catalogCategoryRepository->findOneBy(['uuid' => $args['uuid']]);
                        break;
                    case 'product':
                        $entity = $this->catalogProductRepository->findOneBy(['uuid' => $args['uuid']]);
                        break;
                }

                if (!empty($entity)) {
                    if ($entity->hasFiles()) {
                        $entity->clearFiles();
                    }

                    foreach (explode(';', $args['photo']) as $name) {
                        $path = $this->trademaster->getFilePath($name);

                        if (($model = \App\Domain\Entities\File::getFromPath($path)) !== null) {
                            $entity->addFile($model);

                            $this->entityManager->persist($model);
                            $this->entityManager->persist($entity);

                            // is image
                            if (\Alksily\Support\Str::start('image/', $model->type)) {
                                // add task convert
                                $task = new \App\Domain\Tasks\ConvertImageTask($this->container);
                                $task->execute(['uuid' => $model->uuid]);
                            }

                            $this->setStatusDone();
                        } else {
                            $this->logger->warning('TradeMaster: file not loaded (%s)', ['path' => $path]);
                            $this->setStatusFail();
                        }
                    }
                } else {
                    $this->logger->warning('TradeMaster: entity not found and file not loaded', [
                        'type' => $args['type'],
                        'uuid' => $args['uuid'],
                    ]);
                    $this->setStatusFail();
                }
            } else {
                $this->setStatusFail();
            }
        } else {
            $this->setStatusDone();
        }
    }
}
