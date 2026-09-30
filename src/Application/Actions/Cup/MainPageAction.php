<?php declare(strict_types=1);

namespace App\Application\Actions\Cup;

use App\Application\Dashboard;
use App\Domain\AbstractAction;
use App\Domain\Casts\User\Status as UserStatus;
use App\Domain\Models\CatalogCategory;
use App\Domain\Models\CatalogOrder;
use App\Domain\Models\CatalogProduct;
use App\Domain\Models\File;
use App\Domain\Models\Form;
use App\Domain\Models\FormData;
use App\Domain\Models\GuestBook;
use App\Domain\Models\Page;
use App\Domain\Models\Publication;
use App\Domain\Models\User;

class MainPageAction extends AbstractAction
{
    protected function action(): \Slim\Psr7\Response
    {
        /** @var User $user */
        $user = $this->request->getAttribute('user', false);

        $dashboard = new Dashboard($this->container);
        $enabled = $dashboard->enabled();

        $weekAgo = datetime()->subDays(7);

        return $this->respondWithTemplate('cup/main/index.twig', [
            'enabled' => $enabled,
            'notepad' => $this->parameter('notepad_' . $user->username, ''),
            'attention' => $dashboard->attention($enabled),
            'kpi' => $dashboard->kpi($enabled, $weekAgo),
            'activity' => $this->getActivity($enabled),
            'revenue' => $enabled['catalog'] ? $dashboard->revenue() : null,
            'stats' => [
                'pages' => Page::count(),
                'users' => User::where('status', UserStatus::WORK)->count(),
                'publications' => Publication::count(),
                'guestbook' => GuestBook::count(),
                'catalog' => [
                    'category' => CatalogCategory::count(),
                    'product' => CatalogProduct::count(),
                    'order' => CatalogOrder::count(),
                ],
                'forms' => Form::count(),
                'files' => File::count(),
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

    /**
     * Recent rows per section for the "latest activity" cards.
     */
    private function getActivity(array $enabled): array
    {
        $activity = [
            'users' => User::orderByDesc('register')->limit(6)->get(),
        ];

        if ($enabled['catalog']) {
            $activity['orders'] = CatalogOrder::with(['status', 'products'])
                ->orderByDesc('date')
                ->limit(6)
                ->get();
        }

        if ($enabled['form']) {
            $activity['forms'] = FormData::with('form')
                ->orderByDesc('date')
                ->limit(6)
                ->get();
        }

        if ($enabled['guestbook']) {
            $activity['guestbook'] = GuestBook::orderByDesc('date')->limit(6)->get();
        }

        if ($enabled['publication']) {
            $activity['publications'] = Publication::orderByDesc('date')->limit(6)->get();
        }

        return $activity;
    }
}
