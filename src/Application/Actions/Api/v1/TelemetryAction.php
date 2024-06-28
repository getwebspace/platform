<?php declare(strict_types=1);

namespace App\Application\Actions\Api\v1;

use App\Application\Actions\Api\ActionApi;
use App\Domain\AbstractException;
use App\Domain\Service\Catalog\ProductService as CatalogProductService;
use App\Domain\Service\Page\PageService;
use App\Domain\Service\Publication\PublicationService;
use Illuminate\Support\Collection;
use Psr\Container\ContainerExceptionInterface;

class TelemetryAction extends ActionApi
{
    protected function action(): \Slim\Psr7\Response
    {
        return $this->respondWithJson([
            'version' => [
                'branch' => ($_ENV['COMMIT_BRANCH'] ?? 'other'),
                'commit' => ($_ENV['COMMIT_SHA'] ?? false),
            ],
            'environment' => [
                'os' => @implode(' ', [php_uname('s'), php_uname('r'), php_uname('m')]),
                'php' => PHP_VERSION,
                'memory_limit' => ini_get('memory_limit'),
            ],
            'stats' => [
                'pages' => \App\Domain\Models\Page::count(),
                'publications' => [
                    'category' => \App\Domain\Models\PublicationCategory::count(),
                    'publication' => \App\Domain\Models\Publication::count(),
                ],
                'users' => [
                    'user' => \App\Domain\Models\User::count(),
                    'group' => \App\Domain\Models\UserGroup::count(),
                    'subscriber' => \App\Domain\Models\UserSubscriber::count(),
                    'integration' => \App\Domain\Models\UserIntegration::count(),
                    'token' => \App\Domain\Models\UserToken::count(),
                ],
                'forms' => \App\Domain\Models\Form::count(),
                'catalog' => [
                    'category' => \App\Domain\Models\CatalogCategory::count(),
                    'product' => \App\Domain\Models\CatalogProduct::count(),
                    'attributes' => \App\Domain\Models\CatalogAttribute::count(),
                    'order' => \App\Domain\Models\CatalogOrder::count(),
                ],
                'files' => \App\Domain\Models\File::count(),
                'guestbook' => \App\Domain\Models\GuestBook::count(),
                'reference' => \App\Domain\Models\Reference::count(),
                'params' => \App\Domain\Models\Parameter::count(),
            ],
            'plugins' => $this->container->get('plugin')->get()->map(fn ($plugin) => $plugin->getCredentials('name'))->values(),
        ]);
    }
}
