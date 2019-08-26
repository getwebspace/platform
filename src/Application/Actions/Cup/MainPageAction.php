<?php

namespace App\Application\Actions\Cup;

use App\Application\Actions\Action;

class MainPageAction extends Action
{
    protected function action(): \Slim\Http\Response
    {
        return $this->respondRender('cup/layout.twig', [
            'notepad' => $this->getParameter('notepad_' . $this->request->getAttribute('user')->username, ''),
            'stats' => [
                'pages' => $this->entityManager->getRepository(\App\Domain\Entities\Page::class)->count([]),
                'users' => $this->entityManager->getRepository(\App\Domain\Entities\User::class)->count([]),
                'publications' => $this->entityManager->getRepository(\App\Domain\Entities\Publication::class)->count([]),
                'guestbook' => $this->entityManager->getRepository(\App\Domain\Entities\GuestBook::class)->count([]),
                'catalog' => [
                    'category' => $this->entityManager->getRepository(\App\Domain\Entities\Catalog\Category::class)->count([]),
                    'product' => $this->entityManager->getRepository(\App\Domain\Entities\Catalog\Product::class)->count([]),
                    'order' => $this->entityManager->getRepository(\App\Domain\Entities\Catalog\Order::class)->count([]),
                ],
                'files' => $this->entityManager->getRepository(\App\Domain\Entities\File::class)->count([]),
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
