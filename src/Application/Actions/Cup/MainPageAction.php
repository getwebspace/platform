<?php declare(strict_types=1);

namespace App\Application\Actions\Cup;

use App\Domain\AbstractAction;
use App\Domain\Models\User;

class MainPageAction extends AbstractAction
{
    protected function action(): \Slim\Psr7\Response
    {
        /** @var User $user */
        $user = $this->request->getAttribute('user', false);

        return $this->respondWithTemplate('cup/layout.twig', [
            'notepad' => $this->parameter('notepad_' . $user->username, ''),
            'stats' => [
                'pages' => \App\Domain\Models\Page::count(),
                'users' => \App\Domain\Models\User::where(['status' => \App\Domain\Casts\User\Status::WORK])->count(),
                'publications' => \App\Domain\Models\Publication::count(),
                'guestbook' => \App\Domain\Models\GuestBook::count(),
                'catalog' => [
                    'category' => 0, //$this->entityManager->getRepository(\App\Domain\Entities\Catalog\Category::class)->count(['status' => \App\Domain\Casts\Catalog\Status::WORK]),
                    'product' => 0, //$this->entityManager->getRepository(\App\Domain\Entities\Catalog\Product::class)->count(['status' => \App\Domain\Casts\Catalog\Status::WORK]),
                    'order' => 0, //$this->entityManager->getRepository(\App\Domain\Entities\Catalog\Order::class)->count([]),
                ],
                'forms' => \App\Domain\Models\Form::count(),
                'files' => \App\Domain\Models\File::count(),
            ],
            'properties' => [
                'version' => [
                    'branch' => ($_ENV['COMMIT_BRANCH'] ?? 'other'),
                    'commit' => ($_ENV['COMMIT_SHA'] ?? false),
                ],
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
