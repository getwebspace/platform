<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\Catalog;

use App\Domain\AbstractAction;
use App\Domain\Service\Catalog\CategoryService as CatalogCatalogService;
use App\Domain\Service\Catalog\OrderService as CatalogOrderService;
use App\Domain\Service\Catalog\ProductService as CatalogProductService;
use App\Domain\Service\User\UserService;
use Psr\Container\ContainerInterface;
use Tightenco\Collect\Support\Collection;

abstract class CatalogAction extends AbstractAction
{
    /**
     * @var UserService
     */
    protected UserService $userService;

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

        $this->userService = UserService::getWithContainer($container);
        $this->catalogCategoryService = CatalogCatalogService::getWithContainer($container);
        $this->catalogProductService = CatalogProductService::getWithContainer($container);
        $this->catalogOrderService = CatalogOrderService::getWithContainer($container);
    }

    /**
     * @param bool $list
     * if false return key:value
     * if true return key:list
     *
     * @return Collection
     */
    protected function getMeasure($list = false)
    {
        $measure = $this->getParameter('catalog_measure');
        $result = [];

        if ($measure) {
            preg_match_all('/([\w\d]+)\:\s?([\w\d]+)\;\s?([\w\d]+)\;\s?([\w\d]+)(?>\s|$)/u', $measure, $matches);

            foreach ($matches[1] as $index => $key) {
                $result[$key] = $list ? [$matches[2][$index], $matches[3][$index], $matches[4][$index]] : $matches[2][$index];
            }
        }

        return collect($result);
    }
}
