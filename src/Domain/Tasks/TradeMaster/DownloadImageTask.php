<?php

namespace App\Domain\Tasks\TradeMaster;

use App\Domain\Tasks\Task;

class DownloadImageTask extends Task
{
    public function execute(array $params = []): \App\Domain\Entities\Task
    {
        $default = [
            'photo' => '',
            'item' => '',
            'item_uuid' => \Ramsey\Uuid\Uuid::NIL,
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
    protected $fileRepository;

    protected function action(array $args = [])
    {
        $this->trademaster = $this->container->get('trademaster');
        $this->fileRepository = $this->entityManager->getRepository(\App\Domain\Entities\File::class);

        if ($args['photo']) {
            if ($this->getParameter('file_is_enabled', 'no') === 'yes') {
                foreach (explode(';', $args['photo']) as $name) {
                    $path = $this->trademaster->getFilePath($name);
                    $file_model = \App\Domain\Entities\File::getFromPath($path);

                    if ($file_model) {
                        $file_model->replace([
                            'item' => $args['item'],
                            'item_uuid' => $args['item_uuid'],
                        ]);
                        $this->entityManager->persist($file_model);

                        // add task convert
                        $task = new \App\Domain\Tasks\ConvertImageTask($this->container);
                        $task->execute(['uuid' => $file_model->uuid]);
                    } else {
                        $this->logger->info('TradeMaster: file not loaded (%s)', ['path' => $path]);
                    }
                }
            }

            $this->status_done();
        } else {
            $this->status_fail();
        }
    }
}
