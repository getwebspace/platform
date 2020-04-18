<?php declare(strict_types=1);

namespace App\Application\Actions\Cup;

use App\Application\Actions\Action;
use App\Domain\Entities\User;

class MainPageAction extends Action
{
    protected function action(): \Slim\Http\Response
    {
        /** @var User $user */
        $user = $this->request->getAttribute('user', false);

        return $this->respondWithTemplate('cup/layout.twig', [
            'notepad' => $this->getParameter('notepad_' . $user->getUsername(), ''),
            'stats' => [
                'pages' => $this->entityManager->getRepository(\App\Domain\Entities\Page::class)->count([]),
                'users' => $this->entityManager->getRepository(\App\Domain\Entities\User::class)->count([]),
                'publications' => $this->entityManager->getRepository(\App\Domain\Entities\Publication::class)->count([]),
                'guestbook' => $this->entityManager->getRepository(\App\Domain\Entities\GuestBook::class)->count([]),
                'catalog' => [
                    'category' => $this->entityManager->getRepository(\App\Domain\Entities\Catalog\Category::class)->count(['status' => \App\Domain\Types\Catalog\CategoryStatusType::STATUS_WORK]),
                    'product' => $this->entityManager->getRepository(\App\Domain\Entities\Catalog\Product::class)->count(['status' => \App\Domain\Types\Catalog\ProductStatusType::STATUS_WORK]),
                    'order' => $this->entityManager->getRepository(\App\Domain\Entities\Catalog\Order::class)->count([]),
                ],
                'files' => $this->entityManager->getRepository(\App\Domain\Entities\File::class)->count([]),
            ],
            'properties' => [
                'version' => ($_ENV['COMMIT_BRANCH'] ?? 'other') . ' (' . ($_ENV['COMMIT_SHA'] ?? 'specific') . ')',
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
