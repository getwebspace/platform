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

        // Check the number of records in the table
        $count = $this->fetchRow('SELECT COUNT(*) as count FROM reference');

        if ($count['count'] === 0) {
            // Insert the data if the table is empty
            $this->table('reference')->insert($data)->saveData();
        }
    }
}
