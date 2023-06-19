<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\File;

use App\Domain\AbstractAction;
use App\Domain\Service\File\FileService;
use Psr\Container\ContainerInterface;

abstract class FileAction extends AbstractAction
{
    protected FileService $fileService;

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);

        $this->fileService = $container->get(FileService::class);
    }
}
