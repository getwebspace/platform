<?php declare(strict_types=1);

namespace App\Application\Actions\Common\Catalog;

use App\Domain\AbstractAction;
use App\Domain\Service\Catalog\CategoryService as CatalogCatalogService;
use App\Domain\Service\Catalog\OrderService as CatalogOrderService;
use App\Domain\Service\Catalog\ProductService as CatalogProductService;
use Psr\Container\ContainerInterface;

abstract class CatalogAction extends AbstractAction
{
    /**
     * @var CatalogCatalogService
     */
    protected CatalogCatalogService $catalogCategoryService;

    /**
     * @var CatalogProductService
     */
    protected CatalogProductService $catalogProductService;

    /**
     * @var CatalogOrderService
     */
    protected CatalogOrderService $catalogOrderService;

    /**
     * {@inheritdoc}
     */
    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);

        $this->catalogCategoryService = CatalogCatalogService::getWithContainer($this->container);
        $this->catalogProductService = CatalogProductService::getWithContainer($this->container);
        $this->catalogOrderService = CatalogOrderService::getWithContainer($this->container);
    }
}
