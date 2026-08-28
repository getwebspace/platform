<?php declare(strict_types=1);

namespace App\Domain\References;

use App\Domain\Service\Catalog\AttributeService as CatalogAttributeService;
use App\Domain\Service\Catalog\CategoryService as CatalogCategoryService;
use App\Domain\Service\Catalog\OrderService as CatalogOrderService;
use App\Domain\Service\Catalog\ProductService as CatalogProductService;
use App\Domain\Service\File\FileService;
use App\Domain\Service\GuestBook\GuestBookService;
use App\Domain\Service\Page\PageService;
use App\Domain\Service\Parameter\ParameterService;
use App\Domain\Service\Publication\CategoryService as PublicationCategoryService;
use App\Domain\Service\Publication\PublicationService;
use App\Domain\Service\Reference\ReferenceService;
use App\Domain\Service\Task\TaskService;
use App\Domain\Service\User\GroupService as UserGroupService;
use App\Domain\Service\User\UserService;

/**
 * Single source of truth for what `/api/v1/{entity}` maps onto
 *
 * Read by App\Application\Actions\Api\v1\EntityAction to resolve the service,
 * and by the API key admin screens to list what a key can be scoped to
 */
class ApiEntity
{
    /**
     * Reachable through both /api/v1 (subject to the caller's key scopes)
     * and /cup/api/v1 (always full access, gated by the cup session itself)
     */
    public const MAP = [
        'catalog/attributes' => CatalogAttributeService::class,
        'catalog/category' => CatalogCategoryService::class,
        'catalog/product' => CatalogProductService::class,
        'catalog/order' => CatalogOrderService::class,
        'file' => FileService::class,
        'guestbook' => GuestBookService::class,
        'page' => PageService::class,
        'publication' => PublicationService::class,
        'publication/category' => PublicationCategoryService::class,
        'reference' => ReferenceService::class,
        'task' => TaskService::class,
        'user' => UserService::class,
        'user/group' => UserGroupService::class,
    ];

    /**
     * Settings hold plaintext secrets (SMTP password, payment keys, the API
     * keys' own signing material's neighbours) - reachable only from inside
     * the admin panel, never from an API key or a customer session
     */
    public const CUP_ONLY_MAP = [
        'parameter' => ParameterService::class,
    ];

    /**
     * Human labels for the API key scope picker
     */
    public const LABEL = [
        'catalog/attributes' => 'Catalog: attributes',
        'catalog/category' => 'Catalog: categories',
        'catalog/product' => 'Catalog: products',
        'catalog/order' => 'Catalog: orders',
        'file' => 'Files',
        'guestbook' => 'Guestbook',
        'page' => 'Pages',
        'publication' => 'Publications',
        'publication/category' => 'Publication categories',
        'reference' => 'Reference books',
        'task' => 'Background tasks',
        'user' => 'Users',
        'user/group' => 'User groups',
    ];

    /**
     * @return array<string, string> entity => label, for a <select>
     */
    public static function options(): array
    {
        $options = [];

        foreach (array_keys(self::MAP) as $entity) {
            $options[$entity] = self::LABEL[$entity] ?? $entity;
        }

        return $options;
    }
}
