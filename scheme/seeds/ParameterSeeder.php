<?php declare(strict_types=1);

use Phinx\Seed\AbstractSeed;

class ParameterSeeder extends AbstractSeed
{
    public function run(): void
    {
        $data = [
            [
                'name' => 'catalog_invoice',
                'value' => INVOICE_TEMPLATE,
            ],
            [
                'name' => 'catalog_dispatch',
                'value' => DISPATCH_TEMPLATE,
            ]
        ];

        // Check the number of records in the table
        $count = $this->fetchRow('SELECT COUNT(*) as count FROM params');

        if ($count['count'] == 0) {
            // Insert the data if the table is empty
            $this->table('params')->insert($data)->saveData();
        }
    }
}

const INVOICE_TEMPLATE = <<<'EOD'
    <div class="m-5">
        <div class="row">
            <div class="col-12 text-center">
                <h3 class="font-weight-bold">{{ 'Invoice'|locale }}</h3>
                {#<img src="/images/logo.png" style="width: 100%; max-width: 300px" />#}
            </div>
            <div class="col-6">
                {{ parameter('common_title') }}<br />
                {{ 'Order'|locale }}: <b>{{ order.external_id ?: order.serial }}</b><br />
                {{ 'Date'|locale }}: <b>{{ order.date|df('d.m.Y H:i') }}</b><br />
                {{ 'Shipping'|locale }}: <b>{{ order.shipping|df('d.m.Y H:i') }}</b>
            </div>
            <div class="col-6 text-right">
                {{ qr_code(base_url() ~ '/cart/done/' ~ order.uuid, 100) }}
            </div>
        </div>

        <div class="row mt-3">
            <div class="col-6">
                {{ order.delivery.client }}<br />
                {{ order.phone ? order.phone : '-' }}<br />
                {{ order.email ? order.email : '-' }}
            </div>
            <div class="col-6 text-right">
                {{ order.delivery.address }}<br />
                {{ order.comment }}
            </div>
        </div>

        <div class="row py-1 mt-3 bg-grey2">
            <div class="col-8 col-md-6 text-nowrap font-weight-bold">{{ 'Item'|locale }}</div>
            <div class="d-none d-md-block col-md-2 text-right text-nowrap font-weight-bold">{{ 'Price'|locale }}</div>
            <div class="d-none d-md-block col-md-2 text-right text-nowrap font-weight-bold">{{ 'Quantity'|locale }}</div>
            <div class="col-4 col-md-2 text-right text-nowrap font-weight-bold">{{ 'Sum'|locale }}</div>
        </div>

        {% for item in order.products().where('type', 'product').get() %}
            <div class="row py-1 {{ loop.last ?: 'border-bottom' }} {{ loop.index0 % 2 ? 'bg-grey1' }}">
                <div class="col-8 col-md-6 overflow-hidden text-nowrap">{{ item.title }}</div>
                <div class="d-none d-md-block col-md-2 text-right text-nowrap">{{ item.totalPrice()|number_format(2, '.', ' ') }}</div>
                <div class="d-none d-md-block col-md-2 text-right text-nowrap">{{ item.totalCount() }}</div>
                <div class="col-4 col-md-2 text-right text-nowrap">{{ item.totalSum()|number_format(2, '.', ' ') }}</div>
            </div>
        {% endfor %}

        <div class="row border-top">
            <div class="col-6 text-nowrap font-weight-bold border-top">{{ 'Discount'|locale }}:</div>
            <div class="col-6 text-right text-nowrap font-weight-bold border-top">{{ order.totalDiscount()|number_format(2, '.', ' ') }}</div>

            <div class="col-6 text-nowrap font-weight-bold border-top">{{ 'Tax'|locale }}:</div>
            <div class="col-6 text-right text-nowrap font-weight-bold border-top">{{ order.totalTax()|number_format(2, '.', ' ') }}</div>

            <div class="col-6 text-nowrap font-weight-bold border-top">{{ 'Total'|locale }}:</div>
            <div class="col-6 text-right text-nowrap font-weight-bold border-top">{{ order.totalSum()|number_format(2, '.', ' ') }}</div>
        </div>
    </div>
    EOD;

const DISPATCH_TEMPLATE = <<<'EOD'
    <div class="m-5">
        <div class="row">
            <div class="col-12 text-center">
                <h3 class="font-weight-bold">{{ 'Dispatch Note'|locale }}</h3>
                {#<img src="/images/logo.png" style="width: 100%; max-width: 300px" />#}
            </div>
            <div class="col-6">
                {{ parameter('common_title') }}<br />
                {{ 'Order'|locale }}: <b>{{ order.external_id ?: order.serial }}</b><br />
                {{ 'Date'|locale }}: <b>{{ order.date|df('d.m.Y H:i') }}</b><br />
                {{ 'Shipping'|locale }}: <b>{{ order.shipping|df('d.m.Y H:i') }}</b><br />
                {{ 'Total price'|locale }}: <b>{{ order.totalSum()|number_format(2, '.', ' ') }}</b>
            </div>
            <div class="col-6 text-right">
                {{ order.delivery.client }}<br />
                {{ order.phone ? order.phone : '-' }}<br />
                {{ order.email ? order.email : '-' }}<br />
                {{ order.delivery.address }}<br />
                {{ order.comment }}
            </div>
        </div>
    
        <div class="row py-1 mt-3 bg-grey2">
            <div class="col-6 text-nowrap font-weight-bold">{{ 'Item'|locale }}</div>
            <div class="col-2 text-right text-nowrap font-weight-bold">{{ 'Volumetric weight'|locale }}</div>
            <div class="col-2 text-right text-nowrap font-weight-bold">{{ 'Weight'|locale }}</div>
            <div class="col-2 text-right text-nowrap font-weight-bold">{{ 'Quantity'|locale }}</div>
        </div>
    
        {% for item in order.products().where('type', 'product').get() %}
            <div class="row py-1 {{ loop.last ?: 'border-bottom' }} {{ loop.index0 % 2 ? 'bg-grey1' }}">
                <div class="col-6 text-nowrap font-weight-bold">{{ item.title }}</div>
                <div class="col-2 text-right text-nowrap font-weight-bold">{{ catalog_product_dimensional_weight(item) }}</div>
                <div class="col-2 text-right text-nowrap font-weight-bold">{{ item.weightWithClass() }}</div>
                <div class="col-2 text-right text-nowrap font-weight-bold">{{ item.totalCount() }}</div>
            </div>
        {% endfor %}
    </div>
    EOD;
