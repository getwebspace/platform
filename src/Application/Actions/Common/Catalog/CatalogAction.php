<?php declare(strict_types=1);

namespace App\Application\Actions\Common\Catalog;

use App\Domain\AbstractAction;
use App\Domain\Service\Catalog\CategoryService as CatalogCategoryService;
use App\Domain\Service\Catalog\OrderService as CatalogOrderService;
use App\Domain\Service\Catalog\ProductService as CatalogProductService;
use App\Domain\Service\Notification\NotificationService;
use App\Domain\Service\User\UserService;
use Psr\Container\ContainerInterface;

abstract class CatalogAction extends AbstractAction
{
    protected UserService $userService;

    protected CatalogCategoryService $catalogCategoryService;

    protected CatalogProductService $catalogProductService;

    protected CatalogOrderService $catalogOrderService;

    protected NotificationService $notificationService;

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);

        $this->userService = $container->get(UserService::class);
        $this->catalogCategoryService = $container->get(CatalogCategoryService::class);
        $this->catalogProductService = $container->get(CatalogProductService::class);
        $this->catalogOrderService = $container->get(CatalogOrderService::class);
        $this->notificationService = $container->get(NotificationService::class);
    }
}
