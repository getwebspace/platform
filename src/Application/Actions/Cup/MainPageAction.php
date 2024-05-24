<?php declare(strict_types=1);

namespace App\Application\Actions\Cup;

use App\Domain\AbstractAction;
use App\Domain\Models\User;
use Carbon\Carbon;

class MainPageAction extends AbstractAction
{
    protected function action(): \Slim\Psr7\Response
    {
        /** @var User $user */
        $user = $this->request->getAttribute('user', false);

        // stats
        $chart = $this->db
            ->table('catalog_order as co')
            ->select(
                $this->db->raw('DATE(co.date) as date'),
                $this->db->raw('COUNT(DISTINCT co.uuid) as order_count'),
                $this->db->raw('SUM(
                    CASE
                        WHEN cop.tax_included = false THEN (cop.price + cop.tax - cop.discount) * cop.count
                        ELSE (cop.price - cop.discount) * cop.count
                    END
                ) as sum'),
                $this->db->raw('AVG(
                    CASE
                        WHEN cop.tax_included = false THEN (cop.price + cop.tax - cop.discount) * cop.count
                        ELSE (cop.price - cop.discount) * cop.count
                    END
                ) as average_check')
            )
            ->leftJoin('catalog_order_product as cop', 'co.uuid', '=', 'cop.order_uuid')
            ->leftJoin('catalog_product as cp', 'cop.product_uuid', '=', 'cp.uuid')
            ->where('co.date', '>=', Carbon::now()->subDays(30))
            ->groupBy($this->db->raw('DATE(co.date)'))
            ->orderBy($this->db->raw('DATE(co.date)'))
            ->get();

        return $this->respondWithTemplate('cup/layout.twig', [
            'notepad' => $this->parameter('notepad_' . $user->username, ''),
            'stats' => [
                'pages' => \App\Domain\Models\Page::count(),
                'users' => \App\Domain\Models\User::where(['status' => \App\Domain\Casts\User\Status::WORK])->count(),
                'publications' => \App\Domain\Models\Publication::count(),
                'guestbook' => \App\Domain\Models\GuestBook::count(),
                'catalog' => [
                    'category' => \App\Domain\Models\CatalogCategory::count(),
                    'product' => \App\Domain\Models\CatalogProduct::count(),
                    'order' => \App\Domain\Models\CatalogOrder::count(),
                ],
                'forms' => \App\Domain\Models\Form::count(),
                'files' => \App\Domain\Models\File::count(),
            ],
            'chart' => $chart,
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
