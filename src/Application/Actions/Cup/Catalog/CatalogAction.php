<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\Catalog;

use App\Domain\AbstractAction;
use App\Domain\Service\Catalog\AttributeService as CatalogAttributeService;
use App\Domain\Service\Catalog\CategoryService as CatalogCategoryService;
use App\Domain\Service\Catalog\OrderService as CatalogOrderService;
use App\Domain\Service\Catalog\ProductAttributeService as CatalogProductAttributeService;
use App\Domain\Service\Catalog\ProductRelationService as CatalogProductRelationService;
use App\Domain\Service\Catalog\ProductService as CatalogProductService;
use App\Domain\Service\Notification\NotificationService;
use App\Domain\Service\User\UserService;
use Illuminate\Support\Collection;
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

    protected NotificationService $notificationService;

    /**
     * {@inheritdoc}
     */
    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);

        $this->userService = UserService::getWithContainer($container);
        $this->catalogAttributeService = CatalogAttributeService::getWithContainer($container);
        $this->catalogCategoryService = CatalogCategoryService::getWithContainer($container);
        $this->catalogProductService = CatalogProductService::getWithContainer($container);
        $this->catalogProductAttributeService = CatalogProductAttributeService::getWithContainer($container);
        $this->catalogProductRelationService = CatalogProductRelationService::getWithContainer($container);
        $this->catalogOrderService = CatalogOrderService::getWithContainer($container);
        $this->notificationService = NotificationService::getWithContainer($container);
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
        $measure = $this->parameter('catalog_measure');
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

// todo переместить в более удобное место
const INVOICE_TEMPLATE = <<<EOD
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
            {{ qr_code(base_url() ~ '/cart/done/' ~ order.uuid, 100, 100) }}
        </div>
    </div>

    <div class="row mt-3">
        <div class="col-6">
            {{ order.user ? order.user.getName() : order.delivery.client }}<br />
            {{ order.user and order.user.phone ? order.user.phone : (order.phone ? order.phone : '&mdash;') }}<br />
            {{ order.user and order.user.email ? order.user.email : (order.email ? order.email : '&mdash;') }}
        </div>
        <div class="col-6 text-right">
            {{ order.user ? order.user.getAddress() : order.delivery.address }}<br />
            {{ order.comment }}
        </div>
    </div>

    <div class="row p-1 mt-3 bg-grey2">
        <div class="col-6 font-weight-bold">Позиция</div>
        <div class="col-2 text-right font-weight-bold">Цена</div>
        <div class="col-2 text-right font-weight-bold">Количество</div>
        <div class="col-2 text-right font-weight-bold">Всего</div>
    </div>

    {% set total = 0 %}
    {% for product in products %}
        {% set count = order.list[product.uuid.toString()] %}
        {% set total = total + (product.price * count) %}
        <div class="row p-1 {{ loop.last ?: 'border-bottom' }} {{ loop.index0 % 2 ? 'bg-grey1' }}">
            <div class="col-6">{{ product.title }}</div>
            <div class="col-2 text-right">{{ product.price|number_format(2, '.', ' ') }}</div>
            <div class="col-2 text-right">{{ count }}</div>
            <div class="col-2 text-right">{{ (product.price * count)|number_format(2, '.', ' ') }}</div>
        </div>
    {% endfor %}

    <div class="row p-1">
        <div class="col-6 offset-6 text-right font-weight-bold border-top">Общая сумма: {{ total|number_format(2, '.', ' ') }}</div>
    </div>
</div>
EOD;
