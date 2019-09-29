<?php

namespace App\Domain\Tasks\TradeMaster;

use App\Domain\Tasks\Task;
use Psr\Container\ContainerInterface;

class DownloadImageTask extends Task
{
    /**
     * @var \App\Application\TradeMaster
     */
    protected $trademaster;

    /**
     * @var \Doctrine\Common\Persistence\ObjectRepository|\Doctrine\ORM\EntityRepository
     */
    protected $fileRepository;

    protected function action()
    {
        $this->trademaster = $this->container->get('trademaster');
        $this->fileRepository = $this->entityManager->getRepository(\App\Domain\Entities\File::class);

        foreach (explode(';', $this->entity->params['photo']) as $name) {
            $file = $this->trademaster->getFile($name);

            if ($file) {
                $file_model = new \App\Domain\Entities\File([
                    'name' => $name,
                    'type' => $file['type'],
                    'size' => $file['size'],
                    'salt' => $file['salt'],
                    'hash' => $file['hash'],
                    'date' => new \DateTime(),
                    'item' => $this->entity->params['item'],
                    'item_uuid' => $this->entity->params['item_uuid'],
                ]);

                $this->entityManager->persist($file_model);
            }
        }

        $this->status_done();
    }
}
