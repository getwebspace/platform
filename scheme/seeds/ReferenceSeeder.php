<?php declare(strict_types=1);

use Phinx\Seed\AbstractSeed;

class ReferenceSeeder extends AbstractSeed
{
    public function run(): void
    {
        $data = [];

        $order_status = [
            ['title' => 'New'],
            ['title' => 'In processing'],
            ['title' => 'Payed'],
            ['title' => 'Sent'],
            ['title' => 'Delivered'],
            ['title' => 'Canceled'],
        ];

        foreach ($order_status as $i => $item) {
            $data[] = [
                'uuid' => Ramsey\Uuid\Uuid::uuid4()->toString(),
                'type' => \App\Domain\Casts\Reference\Type::ORDER_STATUS,
                'title' => $item['title'],
                'order' => $i + 1,
                'status' => true,
            ];
        }

        $payment_methods = [
            ['title' => 'Cash'],
            ['title' => 'Card'],
            ['title' => 'To the courier'],
        ];

        foreach ($payment_methods as $i => $item) {
            $data[] = [
                'uuid' => Ramsey\Uuid\Uuid::uuid4()->toString(),
                'type' => \App\Domain\Casts\Reference\Type::PAYMENT,
                'title' => $item['title'],
                'order' => $i + 1,
                'status' => true,
            ];
        }

        $stock_status = [
            ['title' => 'Pre-Order'],
            ['title' => 'Out Of Stock'],
            ['title' => 'In Stock'],
            ['title' => '2-3 Days'],
        ];

        foreach ($stock_status as $i => $item) {
            $data[] = [
                'uuid' => Ramsey\Uuid\Uuid::uuid4()->toString(),
                'type' => \App\Domain\Casts\Reference\Type::STOCK_STATUS,
                'title' => $item['title'],
                'order' => $i + 1,
                'status' => true,
            ];
        }

        $weight_class = [
            ['title' => 'Kilogram', 'value' => ['unit' => 'kg', 'value' => 1000]],
            ['title' => 'Gram', 'value' => ['unit' => 'g', 'value' => 1]],
            ['title' => 'Ounce', 'value' => ['unit' => 'oz', 'value' => 35.2739]],
            ['title' => 'Pound', 'value' => ['unit' => 'lb', 'value' => 2.2046]],
        ];

        foreach ($weight_class as $i => $item) {
            $data[] = [
                'uuid' => Ramsey\Uuid\Uuid::uuid4()->toString(),
                'type' => \App\Domain\Casts\Reference\Type::WEIGHT_CLASS,
                'title' => $item['title'],
                'value' => json_encode($item['value'], JSON_UNESCAPED_UNICODE),
                'order' => $i + 1,
                'status' => true,
            ];
        }

        $length_class = [
            ['title' => 'Meter', 'value' => ['unit' => 'm', 'value' => 100.0000]],
            ['title' => 'Centimeter', 'value' => ['unit' => 'cm', 'value' => 1.0000]],
            ['title' => 'Millimeter', 'value' => ['unit' => 'mm', 'value' => 0.1000]],
            ['title' => 'Inch', 'value' => ['unit' => 'inch', 'value' => 2.5400]],
        ];

        foreach ($length_class as $i => $item) {
            $data[] = [
                'uuid' => Ramsey\Uuid\Uuid::uuid4()->toString(),
                'type' => \App\Domain\Casts\Reference\Type::LENGTH_CLASS,
                'title' => $item['title'],
                'value' => json_encode($item['value'], JSON_UNESCAPED_UNICODE),
                'order' => $i + 1,
                'status' => true,
            ];
        }

        $tax_rates = [
            ['title' => 'VAT 25', 'value' => ['rate' => 25.0000]],
            ['title' => 'VAT 22', 'value' => ['rate' => 22.0000]],
            ['title' => 'VAT 21', 'value' => ['rate' => 21.0000]],
            ['title' => 'VAT 20', 'value' => ['rate' => 20.0000]],
            ['title' => 'VAT 19', 'value' => ['rate' => 19.0000]],
            ['title' => 'VAT 15', 'value' => ['rate' => 15.0000]],
            ['title' => 'VAT 10', 'value' => ['rate' => 10.0000]],
        ];

        foreach ($tax_rates as $i => $item) {
            $data[] = [
                'uuid' => Ramsey\Uuid\Uuid::uuid4()->toString(),
                'type' => \App\Domain\Casts\Reference\Type::TAX_RATE,
                'title' => $item['title'],
                'value' => json_encode($item['value'], JSON_UNESCAPED_UNICODE),
                'order' => $i + 1,
                'status' => true,
            ];
        }

        $social_networks = [
            ['title' => 'Facebook', 'value' => ['url' => '#']],
            ['title' => 'Instagram', 'value' => ['url' => '#']],
            ['title' => 'Telegram', 'value' => ['url' => '#']],
            ['title' => 'WhatsApp', 'value' => ['url' => '#']],
            ['title' => 'Twitter', 'value' => ['url' => '#']],
            ['title' => 'LinkedIn', 'value' => ['url' => '#']],
            ['title' => 'Pinterest', 'value' => ['url' => '#']],
            ['title' => 'Snapchat', 'value' => ['url' => '#']],
            ['title' => 'TikTok', 'value' => ['url' => '#']],
            ['title' => 'YouTube', 'value' => ['url' => '#']],
            ['title' => 'Reddit', 'value' => ['url' => '#']],
            ['title' => 'Tumblr', 'value' => ['url' => '#']],
            ['title' => 'VK', 'value' => ['url' => '#']],
            ['title' => 'WeChat', 'value' => ['url' => '#']],
            ['title' => 'Discord', 'value' => ['url' => '#']],
        ];

        foreach ($social_networks as $i => $item) {
            $data[] = [
                'uuid' => Ramsey\Uuid\Uuid::uuid4()->toString(),
                'type' => \App\Domain\Casts\Reference\Type::SOCIAL_NETWORK,
                'title' => $item['title'],
                'value' => json_encode($item['value'], JSON_UNESCAPED_UNICODE),
                'order' => $i + 1,
                'status' => true,
            ];
        }

        $address_formats = [
            ['title' => 'Default', 'value' => ['format' => '{address}']],
            ['title' => 'International address', 'value' => ['format' => "{company.title}\n{address}\n{city} {postcode}\n{country}"]],
        ];

        foreach ($address_formats as $i => $item) {
            $data[] = [
                'uuid' => Ramsey\Uuid\Uuid::uuid4()->toString(),
                'type' => \App\Domain\Casts\Reference\Type::ADDRESS_FORMAT,
                'title' => $item['title'],
                'value' => json_encode($item['value'], JSON_UNESCAPED_UNICODE),
                'order' => $i + 1,
                'status' => true,
            ];
        }

        $documents = [
            ['title' => 'Order Invoice', 'value' => ['type' => \App\Domain\References\Documents::INVOICE_TYPE, 'template' => INVOICE_TEMPLATE]],
            ['title' => 'Dispatch note', 'value' => ['type' => \App\Domain\References\Documents::DISPATCH_TYPE, 'template' => DISPATCH_TEMPLATE]],
            ['title' => 'Act', 'value' => ['type' => \App\Domain\References\Documents::STATEMENT_TYPE, 'template' => STATEMENT_TEMPLATE]],
        ];

        foreach ($documents as $i => $item) {
            $data[] = [
                'uuid' => Ramsey\Uuid\Uuid::uuid4()->toString(),
                'type' => \App\Domain\Casts\Reference\Type::DOCUMENT,
                'title' => $item['title'],
                'value' => json_encode($item['value'], JSON_UNESCAPED_UNICODE),
                'order' => $i + 1,
                'status' => true,
            ];
        }

        // Check the number of records in the table
        $count = $this->fetchRow('SELECT COUNT(*) as count FROM reference');

        if ($count['count'] === 0) {
            // Insert the data if the table is empty
            $this->table('reference')->insert($data)->saveData();
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

        {% for item in order.products %}
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

const STATEMENT_TEMPLATE = <<<'EOD'
    <div class="m-5">
        <div class="row">
            <div class="col-12 text-center">
                <h3 class="font-weight-bold">{{ 'Act'|locale }}</h3>
                {#<img src="/images/logo.png" style="width: 100%; max-width: 300px" />#}
            </div>
            <div class="col-6">
                {{ parameter('common_title') }}<br />
                {{ 'Order'|locale }}: <b>{{ order.external_id ?: order.serial }}</b><br />
                {{ 'Date'|locale }}: <b>{{ order.date|df('d.m.Y H:i') }}</b><br />
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
            <div class="col-6 text-nowrap font-weight-bold">{{ 'Service'|locale }}</div>
            <div class="col-2 text-right text-nowrap font-weight-bold">{{ 'Quantity'|locale }}</div>
            <div class="col-2 text-right text-nowrap font-weight-bold">{{ 'Cost'|locale }}</div>
            <div class="col-2 text-right text-nowrap font-weight-bold">{{ 'Amount'|locale }}</div>
        </div>
    
        {% for item in order.products %}
            <div class="row py-1 {{ loop.last ?: 'border-bottom' }} {{ loop.index0 % 2 ? 'bg-grey1' }}">
                <div class="col-6 text-nowrap font-weight-bold">{{ item.title }}</div>
                <div class="col-2 text-right text-nowrap font-weight-bold">{{ item.totalCount() }}</div>
                <div class="col-2 text-right text-nowrap font-weight-bold">{{ item.totalPrice() }}</div>
                <div class="col-2 text-right text-nowrap font-weight-bold">{{ item.totalSum() }}</div>
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
