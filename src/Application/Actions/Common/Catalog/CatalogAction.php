<?php declare(strict_types=1);

namespace App\Application\Actions\Common\Catalog;

use App\Domain\AbstractAction;
use App\Domain\Service\Catalog\CategoryService as CatalogCategoryService;
use App\Domain\Service\Catalog\OrderProductService as CatalogOrderProductService;
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

    protected CatalogOrderProductService $catalogOrderProductService;

    protected NotificationService $notificationService;

    /**
     * {@inheritdoc}
     */
    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);

        $this->userService = UserService::getWithContainer($container);
        $this->catalogCategoryService = CatalogCategoryService::getWithContainer($container);
        $this->catalogProductService = CatalogProductService::getWithContainer($container);
        $this->catalogOrderService = CatalogOrderService::getWithContainer($container);
        $this->catalogOrderProductService = CatalogOrderProductService::getWithContainer($container);
        $this->notificationService = NotificationService::getWithContainer($container);
    }
}
