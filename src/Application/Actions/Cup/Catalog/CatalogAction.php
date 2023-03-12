<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\Catalog;

use App\Domain\AbstractAction;
use App\Domain\Service\Catalog\AttributeService as CatalogAttributeService;
use App\Domain\Service\Catalog\CategoryService as CatalogCategoryService;
use App\Domain\Service\Catalog\MeasureService as CatalogMeasureService;
use App\Domain\Service\Catalog\OrderService as CatalogOrderService;
use App\Domain\Service\Catalog\OrderStatusService as CatalogOrderStatusService;
use App\Domain\Service\Catalog\ProductService as CatalogProductService;
use App\Domain\Service\Notification\NotificationService;
use App\Domain\Service\User\UserService;
use Psr\Container\ContainerInterface;

abstract class CatalogAction extends AbstractAction
{
    protected UserService $userService;

    protected CatalogCategoryService $catalogCategoryService;

    protected CatalogProductService $catalogProductService;

    protected CatalogAttributeService $catalogAttributeService;

    protected CatalogOrderService $catalogOrderService;

    protected CatalogOrderStatusService $catalogOrderStatusService;

    protected CatalogMeasureService $catalogMeasureService;

    protected NotificationService $notificationService;

    /**
     * {@inheritdoc}
     */
    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);

        $this->userService = $container->get(UserService::class);
        $this->catalogAttributeService = $container->get(CatalogAttributeService::class);
        $this->catalogCategoryService = $container->get(CatalogCategoryService::class);
        $this->catalogProductService = $container->get(CatalogProductService::class);
        $this->catalogOrderService = $container->get(CatalogOrderService::class);
        $this->catalogOrderStatusService = $container->get(CatalogOrderStatusService::class);
        $this->catalogMeasureService = $container->get(CatalogMeasureService::class);
        $this->notificationService = $container->get(NotificationService::class);
    }
}
