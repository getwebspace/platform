<?php

namespace Application\Actions\Cup;

use Application\Actions\Action;
use DateTime;
use Exception;
use Psr\Container\ContainerInterface;

class MainPageAction extends Action
{
    protected function action(): \Slim\Http\Response
    {
        return $this->respondRender('cup/layout.twig', [
            'stats' => [
                'pages' => $this->entityManager->getRepository(\Domain\Entities\Page::class)->count([]),
                'users' => $this->entityManager->getRepository(\Domain\Entities\User::class)->count([]),
                'publications' => $this->entityManager->getRepository(\Domain\Entities\Publication::class)->count([]),
                'comments' => 0,
                'files' => $this->entityManager->getRepository(\Domain\Entities\File::class)->count([]),
            ],
            'properties' => [
                'os' => @implode(' ', [php_uname('s'), php_uname('r'), php_uname('m')]),
                'php' => PHP_VERSION,
                'memory_limit' => ini_get('memory_limit'),
                'disable_functions' => ini_get('disable_functions'),
                'disable_classes' => ini_get('disable_classes'),
                'upload_max_filesize' => ini_get('upload_max_filesize'),
                'max_file_uploads' => ini_get('max_file_uploads'),
            ],
        ]);
    }
}
