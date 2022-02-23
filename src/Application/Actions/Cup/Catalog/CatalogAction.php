<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\Catalog;

use App\Domain\AbstractAction;
use App\Domain\Service\Catalog\AttributeService as CatalogAttributeService;
use App\Domain\Service\Catalog\CategoryService as CatalogCategoryService;
use App\Domain\Service\Catalog\MeasureService as CatalogMeasureService;
use App\Domain\Service\Catalog\OrderProductService as CatalogOrderProductService;
use App\Domain\Service\Catalog\OrderService as CatalogOrderService;
use App\Domain\Service\Catalog\ProductAttributeService as CatalogProductAttributeService;
use App\Domain\Service\Catalog\ProductRelationService as CatalogProductRelationService;
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

    protected CatalogProductAttributeService $catalogProductAttributeService;

    protected CatalogProductRelationService $catalogProductRelationService;

    protected CatalogOrderService $catalogOrderService;

    protected CatalogOrderProductService $catalogOrderProductService;

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
        $this->catalogProductAttributeService = $container->get(CatalogProductAttributeService::class);
        $this->catalogProductRelationService = $container->get(CatalogProductRelationService::class);
        $this->catalogOrderService = $container->get(CatalogOrderService::class);
        $this->catalogOrderProductService = $container->get(CatalogOrderProductService::class);
        $this->catalogMeasureService = $container->get(CatalogMeasureService::class);
        $this->notificationService = $container->get(NotificationService::class);
    }
}

// todo переместить в более удобное место
const INVOICE_TEMPLATE = <<<'EOD'
    <div class="m-5">
        <div class="row">
            <div class="col-12 text-center">
                <h3 class="font-weight-bold">Инвойс</h3>
                {#<img src="/images/logo.png" style="width: 100%; max-width: 300px" />#}
            </div>
            <div class="col-6">
                {{ parameter('common_title') }}<br />
                Заказ: <b>{{ order.external_id ?: order.serial }}</b><br />
                Дата: <b>{{ order.date|df('d.m.Y H:i') }}</b><br />
                Доставка: <b>{{ order.shipping|df('d.m.Y H:i') }}</b>
            </div>
            <div class="col-6 text-right">
                {{ qr_code(base_url() ~ '/cart/done/' ~ order.uuid, 100) }}
            </div>
        </div>

        <div class="row mt-3">
            <div class="col-6">
                {{ order.user and order.user.getName() == order.delivery.client ? order.user.getName() : order.delivery.client }}<br />
                {{ order.user and order.user.phone == order.phone ? order.user.phone : (order.phone ? order.phone : '&mdash;') }}<br />
                {{ order.user and order.user.email == order.email ? order.user.email : (order.email ? order.email : '&mdash;') }}
            </div>
            <div class="col-6 text-right">
                {{ order.user and order.user.address == order.delivery.address ? order.user.address : order.delivery.address }}<br />
                {{ order.comment }}
            </div>
        </div>

        <div class="row py-1 mt-3 bg-grey2">
            <div class="col-8 col-md-6 text-nowrap font-weight-bold">Позиция</div>
            <div class="d-none d-md-block col-md-2 text-right text-nowrap font-weight-bold">Цена</div>
            <div class="d-none d-md-block col-md-2 text-right text-nowrap font-weight-bold">Количество</div>
            <div class="col-4 col-md-2 text-right text-nowrap font-weight-bold">Сумма</div>
        </div>

        {% set total = 0 %}
        {% for item in order.products %}
            <div class="row py-1 {{ loop.last ?: 'border-bottom' }} {{ loop.index0 % 2 ? 'bg-grey1' }}">
                <div class="col-8 col-md-6 overflow-hidden text-nowrap">{{ item.title }}</div>
                <div class="d-none d-md-block col-md-2 text-right text-nowrap">{{ item.price|number_format(2, '.', ' ') }}</div>
                <div class="d-none d-md-block col-md-2 text-right text-nowrap">{{ item.count }}</div>
                <div class="col-4 col-md-2 text-right text-nowrap">{{ (item.price * item.count)|number_format(2, '.', ' ') }}</div>
            </div>
        {% endfor %}

        <div class="row py-1">
            <div class="col-12 text-right text-nowrap font-weight-bold border-top">Общая сумма: {{ order.getTotalPrice()|number_format(2, '.', ' ') }}</div>
        </div>
    </div>
    EOD;
