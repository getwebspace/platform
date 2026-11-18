<?php declare(strict_types=1);

namespace tests\API\Mcp;

use App\Domain\Casts\Review\Status as ReviewStatus;
use App\Domain\Casts\Reference\Type as ReferenceType;
use App\Domain\Service\Catalog\CategoryService as CatalogCategoryService;
use App\Domain\Service\Catalog\OrderService as CatalogOrderService;
use App\Domain\Service\Catalog\ProductService as CatalogProductService;
use App\Domain\Casts\Task\Status as TaskStatus;
use App\Domain\Service\Form\DataService as FormDataService;
use App\Domain\Service\Form\FormService;
use App\Domain\Service\GuestBook\GuestBookService;
use App\Domain\Service\Catalog\AttributeService;
use App\Domain\Service\File\FileService;
use App\Domain\Service\Page\PageService;
use App\Domain\Service\Publication\CategoryService as PublicationCategoryService;
use App\Domain\Service\Parameter\ParameterService;
use App\Domain\Service\Reference\ReferenceService;
use App\Domain\Service\Review\ReviewService;
use App\Domain\Service\Task\TaskService;
use App\Domain\Service\User\GroupService as UserGroupService;
use App\Domain\Service\User\SubscriberService;
use App\Domain\Service\User\UserService;
use tests\TestCase;

/**
 * Products, categories, prices, orders and the catalog reports
 *
 * @internal
 *
 * #[CoversNothing]
 */
class McpCatalogTest extends McpTestCase
{
    public function testProductSearchIsCaseInsensitive(): void
    {
        $category = $this->getService(CatalogCategoryService::class)->create(['title' => 'Телефоны', 'address' => 'phones']);
        $products = $this->getService(CatalogProductService::class);
        $products->create(['title' => 'Смартфон Альфа', 'address' => 'alpha', 'category_uuid' => $category->uuid, 'vendorcode' => 'VC-1']);
        $products->create(['title' => 'Чехол', 'address' => 'case', 'category_uuid' => $category->uuid, 'vendorcode' => 'VC-2']);

        $token = $this->createApiKeyToken(['read' => ['catalog/product']], false);

        [$error, $result] = $this->tool($token, 'catalog_product_search', ['query' => 'смартфон']);
        $this->assertFalse($error);
        $this->assertEquals(1, $result['total']);
        $this->assertEquals('Смартфон Альфа', $result['items'][0]['title']);

        [, $result] = $this->tool($token, 'catalog_product_search', ['vendorcode' => 'VC-2']);
        $this->assertEquals(['Чехол'], array_column($result['items'], 'title'));

        [, $result] = $this->tool($token, 'catalog_product_search', ['limit' => 1]);
        $this->assertEquals(2, $result['total']);
        $this->assertCount(1, $result['items']);
        $this->assertTrue($result['has_more']);
    }

    public function testOrderList(): void
    {
        $references = $this->getService(ReferenceService::class);
        $new = $references->create(['type' => ReferenceType::ORDER_STATUS, 'title' => 'Новый', 'order' => 1]);
        $done = $references->create(['type' => ReferenceType::ORDER_STATUS, 'title' => 'Выполнен', 'order' => 2]);

        $orders = $this->getService(CatalogOrderService::class);
        $orders->create(['phone' => '+79000000001', 'email' => 'a@example.com']);
        $orders->create(['phone' => '+79000000002', 'email' => 'b@example.com', 'status_uuid' => $new->uuid]);
        $orders->create(['phone' => '+79000000003', 'email' => 'c@example.com', 'status_uuid' => $done->uuid]);

        $token = $this->createApiKeyToken(['read' => ['catalog/order', 'reference']], false);

        [, $result] = $this->tool($token, 'catalog_order_list');
        $this->assertCount(3, $result['items']);

        // no status or the first one
        [, $result] = $this->tool($token, 'catalog_order_list', ['new_only' => true]);
        $this->assertEqualsCanonicalizing(['a@example.com', 'b@example.com'], array_column($result['items'], 'email'));

        [, $result] = $this->tool($token, 'catalog_order_list', ['status' => 'выполнен']);
        $this->assertEquals(['c@example.com'], array_column($result['items'], 'email'));

        [, $result] = $this->tool($token, 'catalog_order_list', ['search' => 'B@EXAMPLE']);
        $this->assertEquals(['b@example.com'], array_column($result['items'], 'email'));

        [$error, $text] = $this->tool($token, 'catalog_order_list', ['status' => 'нет такого']);
        $this->assertTrue($error);
        $this->assertStringContainsString('Выполнен', $text);

        [, $result] = $this->tool($token, 'reference_list', ['type' => ReferenceType::ORDER_STATUS]);
        $this->assertEquals(['Новый', 'Выполнен'], array_column($result['items'], 'title'));

        [$error] = $this->tool($token, 'reference_list');
        $this->assertTrue($error);
    }

    public function testOrderListDateRange(): void
    {
        $orders = $this->getService(CatalogOrderService::class);
        $dates = ['2026-03-10 09:00:00', '2026-03-10 23:30:00', '2026-03-11 00:00:00', '2026-04-01 12:00:00'];
        $created = [];
        foreach ($dates as $i => $date) {
            $created[$i] = $orders->create(['phone' => '+7900000000' . $i, 'email' => "o{$i}@example.com"]);
        }
        // the serial is built from today, so the dates are set after all orders exist
        foreach ($created as $i => $order) {
            \App\Domain\Models\CatalogOrder::query()->where('uuid', $order->uuid)->update(['date' => $dates[$i]]);
        }

        $token = $this->createApiKeyToken(['read' => ['catalog/order']], false);
        $emails = fn (array $args) => array_column($this->tool($token, 'catalog_order_list', $args)[1]['items'] ?? [], 'email');

        // a plain date covers the whole day on both ends
        $this->assertEqualsCanonicalizing(['o0@example.com', 'o1@example.com'], $emails(['date_from' => '2026-03-10', 'date_to' => '2026-03-10']));
        $this->assertEqualsCanonicalizing(['o2@example.com', 'o3@example.com'], $emails(['date_from' => '2026-03-11']));
        $this->assertEqualsCanonicalizing(['o0@example.com', 'o1@example.com', 'o2@example.com'], $emails(['date_to' => '2026-03-11']));
        $this->assertEquals(['o1@example.com'], $emails(['date_from' => '2026-03-10 12:00', 'date_to' => '2026-03-10 23:59']));
        $this->assertCount(4, $emails([]));

        [$error, $text] = $this->tool($token, 'catalog_order_list', ['date_from' => '10.03.2026']);
        $this->assertTrue($error);
        $this->assertStringContainsString('YYYY-MM-DD', $text);

        [$error] = $this->tool($token, 'catalog_order_list', ['date_from' => '2026-04-02', 'date_to' => '2026-04-01']);
        $this->assertTrue($error);
    }

    // wave 2: handling

    public function testOrderUpdateStatus(): void
    {
        $references = $this->getService(ReferenceService::class);
        $references->create(['type' => ReferenceType::ORDER_STATUS, 'title' => 'Новый', 'order' => 1]);
        $references->create(['type' => ReferenceType::ORDER_STATUS, 'title' => 'Выполнен', 'order' => 2]);
        $order = $this->getService(CatalogOrderService::class)->create(['phone' => '+79000000001']);

        $token = $this->createApiKeyToken(['read' => ['catalog/order'], 'write' => ['catalog/order']], false);

        [$error, $result] = $this->tool($token, 'catalog_order_update_status', ['serial' => $order->serial, 'status' => 'выполнен']);
        $this->assertFalse($error);
        $this->assertTrue($result['changed']);
        $this->assertNull($result['status_before']);
        $this->assertEquals('Выполнен', $result['status']);

        [, $result] = $this->tool($token, 'catalog_order_get', ['uuid' => (string) $order->uuid]);
        $this->assertEquals('Выполнен', $result['status']['title']);

        // same status again changes nothing
        [, $result] = $this->tool($token, 'catalog_order_update_status', ['uuid' => (string) $order->uuid, 'status' => 'Выполнен']);
        $this->assertFalse($result['changed']);

        [$error, $text] = $this->tool($token, 'catalog_order_update_status', ['uuid' => (string) $order->uuid, 'status' => 'нет такого']);
        $this->assertTrue($error);
        $this->assertStringContainsString('Новый', $text);

        [$error] = $this->tool($token, 'catalog_order_update_status', ['status' => 'Новый']);
        $this->assertTrue($error);
        [$error] = $this->tool($token, 'catalog_order_update_status', ['serial' => 'nope', 'status' => 'Новый']);
        $this->assertTrue($error);
    }

    public function testProductUpdate(): void
    {
        $category = $this->getService(CatalogCategoryService::class)->create(['title' => 'Телефоны', 'address' => 'phones']);
        $other = $this->getService(CatalogCategoryService::class)->create(['title' => 'Чехлы', 'address' => 'cases']);
        $product = $this->makeProduct('Смартфон', (string) $category->uuid);
        $token = $this->createApiKeyToken(['read' => ['catalog/product'], 'write' => ['catalog/product']], false);

        [$error, $result] = $this->tool($token, 'catalog_product_update', [
            'uuid' => (string) $product->uuid,
            'title' => 'Смартфон Альфа',
            'price' => 150.5,
            'stock' => 0,
            'description' => '<p onclick="x()">Новый <b>текст</b></p><script>alert(1)</script>',
            'meta_title' => 'SEO заголовок',
        ]);
        $this->assertFalse($error, json_encode($result));
        $this->assertEquals('Смартфон Альфа', $result['title']);
        $this->assertEquals(150.5, $result['price']);
        $this->assertEqualsCanonicalizing(['title', 'price', 'stock', 'description', 'meta'], $result['changed_fields']);
        // the address stays even though the title changed
        $this->assertEquals($product->address, $result['address']);

        [, $card] = $this->tool($token, 'catalog_product_get', ['uuid' => (string) $product->uuid]);
        $this->assertEquals('<p>Новый <b>текст</b></p>', $card['description']);
        $this->assertEquals('SEO заголовок', $card['meta']['title']);

        // hide, move
        [, $result] = $this->tool($token, 'catalog_product_update', ['uuid' => (string) $product->uuid, 'status' => 'delete', 'category_uuid' => (string) $other->uuid]);
        $this->assertEquals('delete', $result['status']);
        [, $found] = $this->tool($token, 'catalog_product_search', ['query' => 'альфа']);
        $this->assertEquals(0, $found['total']);

        foreach ([
            ['uuid' => (string) $product->uuid],
            ['uuid' => (string) $product->uuid, 'price' => -1],
            ['uuid' => (string) $product->uuid, 'price' => 'много'],
            ['uuid' => (string) $product->uuid, 'title' => '  '],
            ['uuid' => (string) $product->uuid, 'status' => 'gone'],
            ['uuid' => (string) $product->uuid, 'category_uuid' => \Ramsey\Uuid\Uuid::uuid4()->toString()],
            ['uuid' => 'nope', 'price' => 1],
            ['uuid' => \Ramsey\Uuid\Uuid::uuid4()->toString(), 'price' => 1],
        ] as $args) {
            [$error] = $this->tool($token, 'catalog_product_update', $args);
            $this->assertTrue($error, json_encode($args));
        }
    }

    public function testCategoryUpdate(): void
    {
        $category = $this->getService(CatalogCategoryService::class)->create(['title' => 'Телефоны', 'address' => 'phones']);
        $token = $this->createApiKeyToken(['read' => ['catalog/category'], 'write' => ['catalog/category']], false);

        [$error, $result] = $this->tool($token, 'catalog_category_update', [
            'uuid' => (string) $category->uuid,
            'title' => 'Смартфоны',
            'is_hidden' => true,
            'description' => '<p>Описание</p><iframe src="//evil"></iframe>',
            'meta_description' => 'SEO',
        ]);
        $this->assertFalse($error, json_encode($result));
        $this->assertEquals('Смартфоны', $result['title']);
        $this->assertTrue($result['is_hidden']);
        $this->assertEquals('phones', $result['address']);

        $saved = $this->getService(CatalogCategoryService::class)->read(['uuid' => (string) $category->uuid]);
        $this->assertEquals('<p>Описание</p>', $saved->description);
        $this->assertEquals('SEO', $saved->meta['description']);

        [$error] = $this->tool($token, 'catalog_category_update', ['uuid' => (string) $category->uuid]);
        $this->assertTrue($error);
    }

    public function testPriceBulkUpdate(): void
    {
        $categories = $this->getService(CatalogCategoryService::class);
        $parent = $categories->create(['title' => 'Одежда', 'address' => 'wear']);
        $child = $categories->create(['title' => 'Куртки', 'address' => 'jackets', 'parent_uuid' => $parent->uuid]);
        $outside = $categories->create(['title' => 'Обувь', 'address' => 'shoes']);

        $a = $this->makeProduct('Куртка зимняя', (string) $parent->uuid, ['price' => 1000]);
        $b = $this->makeProduct('Куртка летняя', (string) $child->uuid, ['price' => 500, 'priceWholesale' => 400]);
        $c = $this->makeProduct('Ботинки', (string) $outside->uuid, ['price' => 700]);
        $hidden = $this->makeProduct('Куртка старая', (string) $child->uuid, ['price' => 50, 'status' => 'delete']);

        $token = $this->createApiKeyToken(['read' => ['catalog/product'], 'write' => ['catalog/product']], false);
        $price = fn ($product) => (float) $this->getService(CatalogProductService::class)->read(['uuid' => (string) $product->uuid, 'status' => ['work', 'delete']])->price;

        // preview changes nothing
        [$error, $result] = $this->tool($token, 'catalog_price_bulk_update', ['category_uuid' => (string) $parent->uuid, 'percent' => 10]);
        $this->assertFalse($error, json_encode($result));
        $this->assertFalse($result['applied']);
        $this->assertEquals(2, $result['total']);
        $this->assertEquals(1100.0, collect($result['examples'])->firstWhere('title', 'Куртка зимняя')['new']);
        $this->assertEquals([1000.0, 500.0, 700.0, 50.0], [$price($a), $price($b), $price($c), $price($hidden)]);

        // apply is refused without the confirmed count, or with a wrong one
        foreach ([[], ['expected_count' => 3]] as $extra) {
            [$error] = $this->tool($token, 'catalog_price_bulk_update', ['category_uuid' => (string) $parent->uuid, 'percent' => 10, 'apply' => true] + $extra);
            $this->assertTrue($error);
        }
        $this->assertEquals(1000.0, $price($a));

        // nested category included, hidden and foreign products untouched
        [$error, $result] = $this->tool($token, 'catalog_price_bulk_update', ['category_uuid' => (string) $parent->uuid, 'percent' => 10, 'apply' => true, 'expected_count' => 2]);
        $this->assertFalse($error, json_encode($result));
        $this->assertTrue($result['applied']);
        $this->assertEquals([1100.0, 550.0, 700.0, 50.0], [$price($a), $price($b), $price($c), $price($hidden)]);

        // the address of a changed product stays the same
        $this->assertEquals($a->address, $this->getService(CatalogProductService::class)->read(['uuid' => (string) $a->uuid])->address);

        // fixed amount for a search, only the category without nested ones, never below zero
        [, $result] = $this->tool($token, 'catalog_price_bulk_update', ['search' => 'КУРТКА', 'amount' => -600, 'apply' => true, 'expected_count' => 2]);
        $this->assertEquals([500.0, 0.0], [$price($a), $price($b)]);
        [, $result] = $this->tool($token, 'catalog_price_bulk_update', ['category_uuid' => (string) $parent->uuid, 'include_nested' => false, 'amount' => 1]);
        $this->assertEquals(1, $result['total']);

        // wholesale price
        [, $result] = $this->tool($token, 'catalog_price_bulk_update', ['category_uuid' => (string) $child->uuid, 'field' => 'priceWholesale', 'percent' => -50, 'apply' => true, 'expected_count' => 1]);
        $this->assertEquals(200.0, (float) $this->getService(CatalogProductService::class)->read(['uuid' => (string) $b->uuid])->priceWholesale);

        foreach ([
            ['percent' => 10],
            ['category_uuid' => (string) $parent->uuid],
            ['category_uuid' => (string) $parent->uuid, 'percent' => 10, 'amount' => 5],
            ['category_uuid' => (string) $parent->uuid, 'percent' => -100],
            ['category_uuid' => (string) $parent->uuid, 'percent' => 0],
            ['category_uuid' => (string) $parent->uuid, 'percent' => 10, 'field' => 'cost'],
            ['category_uuid' => \Ramsey\Uuid\Uuid::uuid4()->toString(), 'percent' => 10],
            ['search' => 'такого нет', 'percent' => 10],
        ] as $args) {
            [$error] = $this->tool($token, 'catalog_price_bulk_update', $args);
            $this->assertTrue($error, json_encode($args));
        }
    }

    public function testSalesMatchTheModel(): void
    {
        [$orders] = $this->ordersWithMoney();
        $token = $this->createApiKeyToken(['read' => ['catalog/order', 'reference']], false);

        // what the model says: price minus discount, tax on top, times quantity
        $model = collect($orders)->map(fn ($order) => round($this->getService(CatalogOrderService::class)->read(['uuid' => (string) $order->uuid])->totalSum(), 2));
        $this->assertEquals(2000.0, $model[0]);
        $this->assertEquals(round(1000 + 3 * (500 - 50) * 1.1, 2), $model[1]);

        [$error, $result] = $this->tool($token, 'catalog_sales_report', ['date_from' => '2026-01-01', 'date_to' => '2026-12-31', 'group_by' => 'month']);
        $this->assertFalse($error, json_encode($result));
        $this->assertEquals(3, $result['totals']['orders']);
        $this->assertEquals(round($model->sum(), 2), $result['totals']['revenue'], 'the report is of the same money as the model');
        $this->assertEquals(round($model->sum() / 3, 2), $result['totals']['average_check']);
        $this->assertEquals(['2026-03', '2026-04'], array_column($result['rows'], 'key'));
        $this->assertEquals(2, $result['rows'][0]['orders']);
        $this->assertEquals(round($model[0] + $model[1], 2), $result['rows'][0]['revenue']);

        // the admin panel counts the same
        $dashboard = new \App\Application\Dashboard($this->getService(\Psr\Container\ContainerInterface::class));
        $this->assertStringContainsString('ABS(cop.discount)', \App\Application\Dashboard::lineSql('cop'));
        $this->assertNotEmpty($dashboard->revenue());
    }

    public function testSalesReportGroupings(): void
    {
        $this->ordersWithMoney();
        $token = $this->createApiKeyToken(['read' => ['catalog/order', 'reference']], false);
        $report = fn (array $args) => $this->tool($token, 'catalog_sales_report', array_merge(['date_from' => '2026-01-01', 'date_to' => '2026-12-31'], $args));

        [, $result] = $report(['group_by' => 'day']);
        $this->assertEquals(['2026-03-02', '2026-04-15'], array_column($result['rows'], 'key'), 'days without orders are not listed');

        [, $result] = $report(['group_by' => 'week']);
        $this->assertEquals(['2026-03-02', '2026-04-13'], array_column($result['rows'], 'key'), 'a week starts on Monday');

        [, $result] = $report(['group_by' => 'status']);
        $byLabel = array_column($result['rows'], null, 'label');
        $this->assertEquals(1, $byLabel['Новый']['orders']);
        $this->assertEquals(2, $byLabel['Выполнен']['orders']);

        [, $result] = $report(['group_by' => 'category']);
        $byLabel = array_column($result['rows'], null, 'label');
        $this->assertEquals(2, $byLabel['Одежда']['orders']);
        $this->assertEquals(2, $byLabel['Обувь']['orders']);
        $this->assertEquals(3, $result['totals']['orders'], 'an order with two categories is one order in the total');

        [, $result] = $report(['group_by' => 'month', 'status' => 'выполнен']);
        $this->assertEquals(2, $result['totals']['orders']);

        [, $result] = $report(['date_from' => '2026-03-03', 'date_to' => '2026-03-31']);
        $this->assertEquals(0, $result['totals']['orders']);
        $this->assertSame([], $result['rows']);

        foreach ([['group_by' => 'year'], ['date_from' => '01.03.2026'], ['date_from' => '2026-05-01', 'date_to' => '2026-04-01'], ['date_from' => '2020-01-01', 'date_to' => '2026-12-31'], ['status' => 'нет такого']] as $args) {
            [$error] = $this->tool($token, 'catalog_sales_report', $args);
            $this->assertTrue($error, json_encode($args));
        }
    }

    public function testTopProducts(): void
    {
        [, $jacket, $boots] = $this->ordersWithMoney();
        $token = $this->createApiKeyToken(['read' => ['catalog/order']], false);
        $top = fn (array $args = []) => $this->tool($token, 'catalog_top_products', array_merge(['date_from' => '2026-01-01', 'date_to' => '2026-12-31'], $args))[1]['items'];

        $items = $top();
        $this->assertEquals(['Куртка', 'Ботинки'], array_column($items, 'title'));
        $this->assertEquals(3.0, $items[0]['quantity']);
        $this->assertEquals(2, $items[0]['orders']);
        $this->assertEquals(3000.0, $items[0]['revenue']);

        $this->assertEquals('Ботинки', $top(['sort' => 'quantity'])[0]['title']);
        $this->assertEquals(4.0, $top(['sort' => 'quantity'])[0]['quantity']);
        $this->assertCount(1, $top(['limit' => 1]));

        // a product deleted afterwards is still in the history
        $this->getService(CatalogProductService::class)->delete($boots);
        $this->assertContains('deleted product', array_column($top(), 'title'));

        foreach ([['sort' => 'luck'], ['limit' => 0], ['limit' => 101]] as $args) {
            [$error] = $this->tool($token, 'catalog_top_products', $args);
            $this->assertTrue($error, json_encode($args));
        }
    }

    public function testStockReport(): void
    {
        $category = $this->getService(CatalogCategoryService::class)->create(['title' => 'Товары', 'address' => 'goods']);
        $this->makeProduct('Хороший', (string) $category->uuid, ['price' => 10, 'stock' => 5, 'quantityMin' => 1, 'description' => 'Описание']);
        $this->makeProduct('Нет на складе', (string) $category->uuid, ['price' => 10, 'stock' => 0, 'description' => 'Описание']);
        $this->makeProduct('Мало', (string) $category->uuid, ['price' => 10, 'stock' => 2, 'quantityMin' => 5, 'description' => 'Описание']);
        $this->makeProduct('Без цены', (string) $category->uuid, ['price' => 0, 'stock' => 5, 'description' => 'Описание']);
        $this->makeProduct('Скрытый', (string) $category->uuid, ['price' => 0, 'stock' => 0, 'status' => 'delete']);

        $token = $this->createApiKeyToken(['read' => ['catalog/product']], false);

        [$error, $result] = $this->tool($token, 'catalog_stock_report');
        $this->assertFalse($error, json_encode($result));
        $this->assertEquals(4, $result['visible_products'], 'hidden products are not counted');
        $checks = $result['checks'];
        $this->assertEquals(['Нет на складе'], array_column($checks['out_of_stock']['examples'], 'title'));
        $this->assertEquals(['Мало'], array_column($checks['low_stock']['examples'], 'title'));
        $this->assertEquals(['Без цены'], array_column($checks['no_price']['examples'], 'title'));
        $this->assertEquals(4, $checks['no_image']['count']);
        $this->assertEquals(0, $checks['no_description']['count']);

        [, $result] = $this->tool($token, 'catalog_stock_report', ['checks' => ['no_price'], 'examples' => 0]);
        $this->assertEquals(['no_price'], array_keys($result['checks']));
        $this->assertSame([], $result['checks']['no_price']['examples']);
        $this->assertEquals(1, $result['checks']['no_price']['count']);

        $other = $this->getService(CatalogCategoryService::class)->create(['title' => 'Другое', 'address' => 'other']);
        [, $result] = $this->tool($token, 'catalog_stock_report', ['category_uuid' => (string) $other->uuid]);
        $this->assertEquals(0, $result['visible_products']);

        foreach ([['checks' => ['luck']], ['checks' => 'no_price'], ['examples' => 51]] as $args) {
            [$error] = $this->tool($token, 'catalog_stock_report', $args);
            $this->assertTrue($error, json_encode($args));
        }
    }

    public function testProductCreate(): void
    {
        $category = $this->getService(CatalogCategoryService::class)->create(['title' => 'Phones', 'address' => 'phones']);
        $token = $this->createApiKeyToken(['read' => ['catalog/product'], 'write' => ['catalog/product']], false);

        [$error, $result] = $this->tool($token, 'catalog_product_create', [
            'category_uuid' => (string) $category->uuid,
            'title' => 'Smartphone Alpha',
            'price' => 1500,
            'stock' => 3,
            'description' => '<p onclick="x()">Новый</p><script>alert(1)</script>',
            'meta_title' => 'SEO',
        ]);
        $this->assertFalse($error, json_encode($result));
        $this->assertEquals('phones/smartphone-alpha', $result['address']);
        $this->assertFalse($result['visible'], 'hidden until the operator makes it visible');
        $this->assertEquals('Phones', $result['category']);
        $this->assertEquals(1500, $result['price']);

        // hidden = not in the search of the shop, but there for the tools
        [, $found] = $this->tool($token, 'catalog_product_search', ['query' => 'alpha']);
        $this->assertEquals(0, $found['total']);
        $saved = $this->getService(CatalogProductService::class)->read(['uuid' => $result['uuid'], 'status' => ['work', 'delete']]);
        $this->assertEquals('<p>Новый</p>', $saved->description);
        $this->assertEquals('SEO', $saved->meta['title']);

        // shown at once
        [, $visible] = $this->tool($token, 'catalog_product_create', ['category_uuid' => (string) $category->uuid, 'title' => 'Smartphone Beta', 'visible' => true]);
        $this->assertTrue($visible['visible']);
        [, $found] = $this->tool($token, 'catalog_product_search', ['query' => 'beta']);
        $this->assertEquals(1, $found['total']);

        // own address
        [, $result] = $this->tool($token, 'catalog_product_create', ['category_uuid' => (string) $category->uuid, 'title' => 'Телефон', 'address' => 'phones/telefon-gamma']);
        $this->assertEquals('phones/telefon-gamma', $result['address']);

        foreach ([
            ['title' => 'No category'],
            ['category_uuid' => (string) $category->uuid],
            ['category_uuid' => (string) $category->uuid, 'title' => '  '],
            ['category_uuid' => \Ramsey\Uuid\Uuid::uuid4()->toString(), 'title' => 'Lost'],
            ['category_uuid' => (string) $category->uuid, 'title' => 'Smartphone Alpha'],
            ['category_uuid' => (string) $category->uuid, 'title' => 'Bad price', 'price' => -5],
            // the letters of the title give no address and no transliteration is installed in the tests
            ['category_uuid' => (string) $category->uuid, 'title' => 'Новинка'],
        ] as $args) {
            [$error] = $this->tool($token, 'catalog_product_create', $args);
            $this->assertTrue($error, json_encode($args));
        }

        // dry run saves nothing
        [, $result] = $this->tool($token, 'catalog_product_create', ['category_uuid' => (string) $category->uuid, 'title' => 'Only a rehearsal', 'dry_run' => true]);
        $this->assertTrue($result['dry_run']);
        $this->assertCount(0, $this->getService(CatalogProductService::class)->read(['search' => 'rehearsal', 'status' => ['work', 'delete']]));
    }

    public function testCategoryCreate(): void
    {
        $categories = $this->getService(CatalogCategoryService::class);
        $parent = $categories->create(['title' => 'Wear', 'address' => 'wear']);
        $token = $this->createApiKeyToken(['read' => ['catalog/category'], 'write' => ['catalog/category']], false);

        [$error, $result] = $this->tool($token, 'catalog_category_create', ['title' => 'Jackets', 'parent_uuid' => (string) $parent->uuid, 'description' => '<p>x</p><iframe src="//evil"></iframe>', 'meta_description' => 'SEO']);
        $this->assertFalse($error, json_encode($result));
        $this->assertEquals('wear/jackets', $result['address']);
        $this->assertEquals('Wear', $result['parent']);
        $this->assertTrue($result['is_hidden'], 'out of the menus until the operator decides');

        $saved = $categories->read(['uuid' => $result['uuid']]);
        $this->assertEquals('<p>x</p>', $saved->description);
        $this->assertEquals('SEO', $saved->meta['description']);

        [, $result] = $this->tool($token, 'catalog_category_create', ['title' => 'Top', 'hidden' => false]);
        $this->assertFalse($result['is_hidden']);
        $this->assertEquals('top', $result['address']);

        foreach ([
            [],
            ['title' => ' '],
            ['title' => 'Child', 'parent_uuid' => \Ramsey\Uuid\Uuid::uuid4()->toString()],
            ['title' => 'Child', 'parent_uuid' => 'nope'],
            ['title' => 'Top'],
            ['title' => 'Категория'],
        ] as $args) {
            [$error] = $this->tool($token, 'catalog_category_create', $args);
            $this->assertTrue($error, json_encode($args));
        }

        [, $result] = $this->tool($token, 'catalog_category_create', ['title' => 'Rehearsal', 'dry_run' => true]);
        $this->assertTrue($result['dry_run']);
        $this->assertCount(0, $categories->read(['search' => 'rehearsal']));
    }

    public function testOrderUpdate(): void
    {
        $order = $this->getService(CatalogOrderService::class)->create(['phone' => '+79000000001', 'email' => 'old@example.com', 'comment' => 'Старый', 'delivery' => ['client' => 'Иван', 'address' => 'Уфа']]);
        $token = $this->createApiKeyToken(['read' => ['catalog/order'], 'write' => ['catalog/order']], false);

        [$error, $result] = $this->tool($token, 'catalog_order_update', ['serial' => $order->serial, 'phone' => '+79990000000', 'address' => 'Казань, ул. Мира 1', 'shipping' => '2026-12-24 15:00', 'comment' => 'Позвонить']);
        $this->assertFalse($error, json_encode($result));
        $this->assertEquals('+79990000000', $result['phone']);
        $this->assertEquals('Казань, ул. Мира 1', $result['address']);
        $this->assertEquals('Иван', $result['client'], 'the delivery data is merged, not replaced');
        $this->assertEquals('2026-12-24 15:00', $result['shipping']);
        $this->assertEquals('old@example.com', $result['email']);
        $this->assertEqualsCanonicalizing(['phone', 'delivery', 'shipping', 'comment'], $result['changed_fields']);

        [, $result] = $this->tool($token, 'catalog_order_update', ['uuid' => (string) $order->uuid, 'shipping' => '', 'email' => 'new@example.com']);
        $this->assertNull($result['shipping']);
        $this->assertEquals('new@example.com', $result['email']);

        // dry run
        [, $result] = $this->tool($token, 'catalog_order_update', ['uuid' => (string) $order->uuid, 'comment' => 'Только пример', 'dry_run' => true]);
        $this->assertTrue($result['dry_run']);
        $this->assertEquals('Позвонить', $this->getService(CatalogOrderService::class)->read(['uuid' => (string) $order->uuid])->comment);

        foreach ([
            ['phone' => '1'],
            ['serial' => $order->serial],
            ['serial' => $order->serial, 'email' => 'not an email'],
            ['serial' => $order->serial, 'shipping' => '24.12.2026'],
            ['serial' => 'nope', 'phone' => '1'],
        ] as $args) {
            [$error] = $this->tool($token, 'catalog_order_update', $args);
            $this->assertTrue($error, json_encode($args));
        }
    }

    public function testProductHasAllItsFields(): void
    {
        $category = $this->getService(CatalogCategoryService::class)->create(['title' => 'Goods', 'address' => 'goods']);
        $color = $this->makeAttribute('Color', 'color');
        $weight = $this->makeAttribute('Weight', 'weight', 'float');
        $pieces = $this->makeAttribute('Pieces', 'pieces', 'integer');
        $waterproof = $this->makeAttribute('Waterproof', 'waterproof', 'boolean');
        $other = $this->makeProduct('Related one', (string) $category->uuid);
        $hidden = $this->makeProduct('Related hidden', (string) $category->uuid, ['status' => 'delete']);

        $references = $this->getService(ReferenceService::class);
        $references->create(['type' => ReferenceType::LENGTH_CLASS, 'title' => 'Centimeter', 'value' => ['unit' => 'cm']]);
        $references->create(['type' => ReferenceType::WEIGHT_CLASS, 'title' => 'Kilogram', 'value' => ['unit' => 'kg']]);

        $token = $this->createApiKeyToken(['read' => ['catalog/product', 'catalog/attributes'], 'write' => ['catalog/product']], false);
        $products = $this->getService(CatalogProductService::class);
        $row = fn (string $uuid) => $products->read(['uuid' => $uuid, 'status' => ['work', 'delete']]);

        [$error, $created] = $this->tool($token, 'catalog_product_create', [
            'category_uuid' => (string) $category->uuid,
            'title' => 'Jacket Full',
            'type' => 'service',
            'price' => 100, 'priceFirst' => 60.5, 'priceWholesale' => 80, 'priceWholesaleFrom' => 10,
            'discount' => 5, 'tax' => 20, 'tax_included' => true,
            'quantity' => 2, 'quantityMin' => 3, 'stock' => 7, 'special' => true,
            'vendorcode' => 'VC-1', 'barcode' => '4600000000001', 'country' => 'Russia', 'manufacturer' => 'Factory',
            'tags' => ['Warm', 'winter', 'warm', ' '],
            'order' => 5, 'date' => '2026-02-03 10:20', 'external_id' => 'ERP-77', 'export' => 'import',
            'dimension' => ['length' => 30, 'width' => 20.5, 'height' => 4, 'weight' => 0.8, 'length_class' => 'CM', 'weight_class' => 'kg'],
            'attributes' => ['color' => 'red', 'weight' => '2.5', 'pieces' => 4, 'waterproof' => true],
            'relations' => [(string) $other->uuid => 2, (string) $hidden->uuid => 1],
            'meta_title' => 'SEO',
        ]);
        $this->assertFalse($error, json_encode($created));

        $product = $row($created['uuid']);
        $this->assertEquals('service', $product->type);
        $this->assertEquals([100.0, 60.5, 80.0, 10.0, 5.0, 20.0], [$product->price, (float) $product->getRawOriginal('priceFirst'), $product->priceWholesale, $product->priceWholesaleFrom, $product->discount, $product->tax]);
        $this->assertTrue($product->tax_included);
        $this->assertEquals([2.0, 3.0, 7.0], [$product->quantity, $product->quantityMin, $product->stock]);
        $this->assertEquals(['warm', 'winter'], array_map('mb_strtolower', $product->tags), 'tags are unique, empty ones go');
        $this->assertEquals([5, 'ERP-77', 'import', '2026-02-03 10:20'], [$product->order, $product->external_id, $product->export, $product->date->format('Y-m-d H:i')]);
        $this->assertEquals(['length' => 30.0, 'width' => 20.5, 'height' => 4.0, 'weight' => 0.8, 'length_class' => 'cm', 'weight_class' => 'kg'], $product->dimension, 'the unit is the one of the reference book');
        $this->assertEquals('SEO', $product->meta['title']);

        $values = $this->db()->table('catalog_attribute_product')->where('product_uuid', $created['uuid'])->pluck('value', 'attribute_uuid')->all();
        $this->assertEquals(['red', '2.5', '4', 'yes'], [$values[(string) $color->uuid], $values[(string) $weight->uuid], $values[(string) $pieces->uuid], $values[(string) $waterproof->uuid]]);
        $related = $this->db()->table('catalog_product_related')->where('product_uuid', $created['uuid'])->pluck('count', 'related_uuid')->all();
        $this->assertEquals([2.0, 1.0], [(float) $related[(string) $other->uuid], (float) $related[(string) $hidden->uuid]]);

        // created hidden: a caller with a key finds and reads it, the public one does not
        [, $found] = $this->tool($token, 'catalog_product_search', ['query' => 'Jacket Full']);
        $this->assertEquals(0, $found['total']);
        [, $found] = $this->tool($token, 'catalog_product_search', ['query' => 'Jacket Full', 'status' => 'delete']);
        $this->assertEquals([1, 'delete'], [$found['total'], $found['items'][0]['status']]);
        [, $found] = $this->tool($token, 'catalog_product_search', ['query' => 'related', 'status' => 'all']);
        $this->assertEquals(2, $found['total']);
        [$error] = $this->tool($token, 'catalog_product_search', ['status' => 'maybe']);
        $this->assertTrue($error);

        // the operator sees all of it, the first price too
        [, $card] = $this->tool($token, 'catalog_product_get', ['uuid' => $created['uuid']]);
        $this->assertEquals('delete', $card['status']);
        $this->assertEquals(60.5, (float) $card['priceFirst']);
        $this->assertEquals('service', $card['type']);

        // update: only what is passed changes, the lists are merged
        [$error, $result] = $this->tool($token, 'catalog_product_update', [
            'uuid' => $created['uuid'],
            'tags' => ['summer'],
            'dimension' => ['weight' => 1.2],
            'attributes' => ['color' => 'blue', 'waterproof' => 'no', 'weight' => null],
            'relations' => [(string) $other->uuid => 0],
            'tax_included' => false,
            'external_id' => '',
        ]);
        $this->assertFalse($error, json_encode($result));
        $product = $row($created['uuid']);
        $this->assertEquals(['summer'], $product->tags);
        $this->assertEquals([30.0, 1.2, 'cm'], [$product->dimension['length'], $product->dimension['weight'], $product->dimension['length_class']], 'the other keys of the dimension stay');
        $this->assertFalse($product->tax_included);
        $this->assertEquals('', $product->external_id);
        $values = $this->db()->table('catalog_attribute_product')->where('product_uuid', $created['uuid'])->pluck('value', 'attribute_uuid')->all();
        $this->assertEquals(['blue', 'no', '4'], [$values[(string) $color->uuid], $values[(string) $waterproof->uuid], $values[(string) $pieces->uuid]], 'the attribute that was not passed stays');
        $this->assertArrayNotHasKey((string) $weight->uuid, $values, 'null removes a value');
        $related = $this->db()->table('catalog_product_related')->where('product_uuid', $created['uuid'])->pluck('count', 'related_uuid')->all();
        $this->assertSame([(string) $hidden->uuid], array_keys($related), 'a relation with a hidden product is not lost, 0 removes one');

        [, $result] = $this->tool($token, 'catalog_product_update', ['uuid' => $created['uuid'], 'tags' => 'a, b ,a']);
        $this->assertEquals(['a', 'b'], $row($created['uuid'])->tags, 'tags as a comma separated text');
        [, $result] = $this->tool($token, 'catalog_product_update', ['uuid' => $created['uuid'], 'tags' => []]);
        $this->assertSame([], $row($created['uuid'])->tags);

        foreach ([
            ['type' => 'gadget'],
            ['tax_included' => 'maybe-not-checked-as-bool', 'priceFirst' => -1],
            ['priceWholesaleFrom' => 'many'],
            ['order' => -1], ['order' => 'x'],
            ['date' => '03.02.2026'],
            ['external_id' => str_repeat('a', 256)],
            ['export' => str_repeat('a', 51)],
            ['tags' => 'x', 'dimension' => 'big'],
            ['dimension' => ['length' => -1]],
            ['dimension' => ['depth' => 1]],
            ['dimension' => ['length_class' => 'miles']],
            ['dimension' => ['weight_class' => 'cm']],
            ['attributes' => ['no_such_attribute' => 'x']],
            ['attributes' => ['pieces' => 'four']], ['attributes' => ['pieces' => 4.5]], ['attributes' => ['weight' => 'heavy']],
            ['attributes' => ['waterproof' => 'perhaps']], ['attributes' => ['color' => ['a']]], ['attributes' => ['color' => str_repeat('я', 1001)]],
            ['attributes' => ['a', 'b']],
            ['relations' => [(string) $created['uuid'] => 1]],
            ['relations' => [\Ramsey\Uuid\Uuid::uuid4()->toString() => 1]],
            ['relations' => ['nope' => 1]],
            ['relations' => [(string) $other->uuid => -1]],
        ] as $args) {
            // the last check wants an arithmetic error: the first of each pair is the one that must fail
            [$error] = $this->tool($token, 'catalog_product_update', ['uuid' => $created['uuid']] + $args);
            $this->assertTrue($error, json_encode($args));
        }

        // nothing of the refused calls is saved
        $this->assertEquals(['blue', 'no', '4'], array_values(array_filter($this->db()->table('catalog_attribute_product')->where('product_uuid', $created['uuid'])->pluck('value', 'attribute_uuid')->all(), fn ($v) => $v !== '')) === [] ? [] : [$values[(string) $color->uuid], $values[(string) $waterproof->uuid], $values[(string) $pieces->uuid]]);

        // dry run shows the lists and saves nothing
        [, $dry] = $this->tool($token, 'catalog_product_update', ['uuid' => $created['uuid'], 'attributes' => ['color' => 'green'], 'dry_run' => true]);
        $this->assertTrue($dry['dry_run']);
        $this->assertEquals('blue', $this->db()->table('catalog_attribute_product')->where('product_uuid', $created['uuid'])->where('attribute_uuid', (string) $color->uuid)->value('value'));
    }

    public function testProductWithoutUnitBooksTakesAnyUnit(): void
    {
        $category = $this->getService(CatalogCategoryService::class)->create(['title' => 'Goods', 'address' => 'goods']);
        $token = $this->createApiKeyToken(['write' => ['catalog/product']], false);

        [$error, $result] = $this->tool($token, 'catalog_product_create', ['category_uuid' => (string) $category->uuid, 'title' => 'Box', 'dimension' => ['length_class' => 'mm', 'length' => 1]]);
        $this->assertFalse($error, json_encode($result));
        $this->assertEquals('mm', $this->getService(CatalogProductService::class)->read(['uuid' => $result['uuid'], 'status' => ['work', 'delete']])->dimension['length_class']);
    }

    public function testCategoryHasAllItsFields(): void
    {
        $categories = $this->getService(CatalogCategoryService::class);
        $filter = $this->makeAttribute('Color', 'color');
        $plain = $this->makeAttribute('Note', 'note', 'string', false);
        $size = $this->makeAttribute('Size', 'size', 'integer');

        $token = $this->createApiKeyToken(['read' => ['catalog/category', 'catalog/attributes'], 'write' => ['catalog/category']], false);

        [$error, $created] = $this->tool($token, 'catalog_category_create', [
            'title' => 'Wear', 'description' => '<p>x</p><script>1</script>', 'pagination' => 24, 'is_allow_nested' => true,
            'sort' => ['by' => 'price', 'direction' => 'desc'], 'template' => ['category' => 'main.twig'],
            'order' => 3, 'specifics' => ['banner' => 'sale', 'columns' => 4], 'external_id' => 'ERP-1', 'export' => 'import', 'system' => 'sys',
            'meta_title' => 'SEO', 'attributes_add' => ['color', (string) $plain->uuid],
        ]);
        $this->assertFalse($error, json_encode($created));
        $this->assertEqualsCanonicalizing(['color', 'note'], $created['attributes']);

        [, $card] = $this->tool($token, 'catalog_category_get', ['uuid' => $created['uuid']]);
        $this->assertEquals('<p>x</p>', $card['description']);
        $this->assertEquals([24, true, 3], [$card['pagination'], $card['is_allow_nested'], $card['order']]);
        $this->assertEquals(['by' => 'price', 'direction' => 'DESC'], $card['sort']);
        $this->assertEquals('main.twig', $card['template']['category']);
        $this->assertEquals(['banner' => 'sale', 'columns' => 4], $card['specifics']);
        $this->assertEquals(['ERP-1', 'import', 'sys', 'SEO'], [$card['external_id'], $card['export'], $card['system'], $card['meta']['title']]);
        $this->assertEquals(['color'], $card['filters'], 'a filter is an attribute of the category with the flag');
        $this->assertCount(2, $card['attributes']);
        $this->assertEquals([0, 0], [$card['products'], $card['nested']]);
        $this->assertNull($card['parent']);

        [, $byAddress] = $this->tool($token, 'catalog_category_get', ['address' => $created['address']]);
        $this->assertEquals($created['uuid'], $byAddress['uuid']);

        // update: the json fields are merged, the attributes are added and taken away
        [$error, $result] = $this->tool($token, 'catalog_category_update', [
            'uuid' => $created['uuid'], 'sort' => ['direction' => 'asc'], 'template' => ['product' => 'main.twig'],
            'specifics' => ['columns' => null, 'theme' => 'dark'], 'pagination' => 50,
            'attributes_add' => ['size'], 'attributes_remove' => ['note'], 'is_allow_nested' => false, 'order' => 0, 'external_id' => '',
        ]);
        $this->assertFalse($error, json_encode($result));
        [, $card] = $this->tool($token, 'catalog_category_get', ['uuid' => $created['uuid']]);
        $this->assertEquals(['by' => 'price', 'direction' => 'ASC'], $card['sort'], 'only the direction changed');
        $this->assertEquals('main.twig', $card['template']['category']);
        $this->assertEquals('main.twig', $card['template']['product']);
        $this->assertEquals(['banner' => 'sale', 'theme' => 'dark'], $card['specifics']);
        $this->assertEquals([50, false, 0, ''], [$card['pagination'], $card['is_allow_nested'], $card['order'], $card['external_id']]);
        $this->assertEqualsCanonicalizing(['color', 'size'], array_column($card['attributes'], 'address'));

        // a nested category gets the attributes of the parent
        [, $child] = $this->tool($token, 'catalog_category_create', ['title' => 'Jackets', 'parent_uuid' => $created['uuid'], 'attributes_add' => ['note']]);
        $this->assertEqualsCanonicalizing(['color', 'size', 'note'], $child['attributes']);

        // move: into another category, to the top, never into itself or its own nested one
        [, $other] = $this->tool($token, 'catalog_category_create', ['title' => 'Shoes']);
        [$error, $moved] = $this->tool($token, 'catalog_category_update', ['uuid' => $child['uuid'], 'parent_uuid' => $other['uuid']]);
        $this->assertFalse($error, json_encode($moved));
        $this->assertEquals($other['uuid'], $this->tool($token, 'catalog_category_get', ['uuid' => $child['uuid']])[1]['parent']['uuid']);
        $this->assertEquals(1, $this->tool($token, 'catalog_category_get', ['uuid' => $other['uuid']])[1]['nested']);
        [, $moved] = $this->tool($token, 'catalog_category_update', ['uuid' => $child['uuid'], 'parent_uuid' => '']);
        $this->assertNull($this->tool($token, 'catalog_category_get', ['uuid' => $child['uuid']])[1]['parent']);
        $this->tool($token, 'catalog_category_update', ['uuid' => $child['uuid'], 'parent_uuid' => $created['uuid']]);
        foreach ([$created['uuid'] => $created['uuid'], 'descendant' => $created['uuid']] as $which => $uuid) {
            $target = $which === 'descendant' ? $child['uuid'] : $uuid;
            [$error, $text] = $this->tool($token, 'catalog_category_update', ['uuid' => $uuid, 'parent_uuid' => $target]);
            $this->assertTrue($error, "into {$which}");
            $this->assertStringContainsString('into itself', $text);
        }

        foreach ([
            ['pagination' => 0], ['pagination' => 501], ['pagination' => 'x'],
            ['sort' => ['by' => 'luck']], ['sort' => ['direction' => 'sideways']], ['sort' => ['how' => 'x']], ['sort' => 'price'],
            ['template' => ['category' => 'no-such.twig']], ['template' => ['category' => '../src/x.twig']], ['template' => ['page' => 'main.twig']], ['template' => 'main.twig'],
            ['specifics' => ['a', 'b']], ['specifics' => ['big' => str_repeat('я', 5000)]],
            ['order' => -1], ['external_id' => str_repeat('a', 256)], ['export' => str_repeat('a', 51)], ['system' => str_repeat('a', 256)],
            ['attributes_add' => ['no_such']], ['attributes_add' => 'color'], ['attributes_remove' => [123456]],
            ['attributes_add' => ['color'], 'attributes_remove' => ['color']],
            ['parent_uuid' => \Ramsey\Uuid\Uuid::uuid4()->toString()], ['parent_uuid' => 'nope'],
        ] as $args) {
            [$error] = $this->tool($token, 'catalog_category_update', ['uuid' => $created['uuid']] + $args);
            $this->assertTrue($error, json_encode($args));
        }

        // dry run
        [, $dry] = $this->tool($token, 'catalog_category_update', ['uuid' => $created['uuid'], 'pagination' => 99, 'dry_run' => true]);
        $this->assertTrue($dry['dry_run']);
        $this->assertEquals(50, $this->tool($token, 'catalog_category_get', ['uuid' => $created['uuid']])[1]['pagination']);

        [$error] = $this->tool($token, 'catalog_category_get', []);
        $this->assertTrue($error);
        [$error] = $this->tool($token, 'catalog_category_get', ['uuid' => \Ramsey\Uuid\Uuid::uuid4()->toString()]);
        $this->assertTrue($error);
    }

    public function testAttributeTools(): void
    {
        $category = $this->getService(CatalogCategoryService::class)->create(['title' => 'Goods', 'address' => 'goods']);
        $token = $this->createApiKeyToken(['read' => ['catalog/attributes', 'catalog/category', 'catalog/product'], 'write' => ['catalog/attributes', 'catalog/category', 'catalog/product']], false);

        [$error, $color] = $this->tool($token, 'catalog_attribute_create', ['title' => 'Color', 'group' => 'Look']);
        $this->assertFalse($error, json_encode($color));
        $this->assertEquals(['color', 'string', 'Look', true], [$color['address'], $color['type'], $color['group'], $color['is_filter']]);

        [, $weight] = $this->tool($token, 'catalog_attribute_create', ['title' => 'Item weight', 'type' => 'float', 'is_filter' => false, 'address' => 'wt']);
        $this->assertEquals(['wt', 'float', false], [$weight['address'], $weight['type'], $weight['is_filter']]);

        foreach ([[], ['title' => ' '], ['title' => 'X', 'type' => 'text'], ['title' => 'Color'], ['title' => 'Other', 'address' => 'color'], ['title' => 'Цвет']] as $args) {
            [$error] = $this->tool($token, 'catalog_attribute_create', $args);
            $this->assertTrue($error, json_encode($args));
        }

        [, $list] = $this->tool($token, 'catalog_attribute_list', ['is_filter' => true]);
        $this->assertEquals(['color'], array_column($list['items'], 'address'));

        // a filter of a category: an attribute of the category that has the flag
        [, $cat] = $this->tool($token, 'catalog_category_update', ['uuid' => (string) $category->uuid, 'attributes_add' => ['color', 'wt']]);
        $this->assertEquals(['color'], $this->tool($token, 'catalog_category_get', ['uuid' => (string) $category->uuid])[1]['filters']);
        [, $updated] = $this->tool($token, 'catalog_attribute_update', ['attribute' => 'wt', 'is_filter' => true, 'group' => 'Size', 'title' => 'Weight of an item']);
        $this->assertEquals([true, 'Size', 'Weight of an item', 'wt'], [$updated['is_filter'], $updated['group'], $updated['title'], $updated['address']]);
        $this->assertEqualsCanonicalizing(['color', 'wt'], $this->tool($token, 'catalog_category_get', ['uuid' => (string) $category->uuid])[1]['filters']);

        // the type can be changed while nobody has a value
        [$error, $changed] = $this->tool($token, 'catalog_attribute_update', ['attribute' => (string) $weight['uuid'], 'type' => 'integer']);
        $this->assertFalse($error, json_encode($changed));
        $this->tool($token, 'catalog_product_create', ['category_uuid' => (string) $category->uuid, 'title' => 'Thing', 'attributes' => ['wt' => 3]]);
        [$error, $text] = $this->tool($token, 'catalog_attribute_update', ['attribute' => 'wt', 'type' => 'string']);
        $this->assertTrue($error);
        $this->assertStringContainsString('1 products', $text);

        // the same type is not a change
        [$error] = $this->tool($token, 'catalog_attribute_update', ['attribute' => 'wt', 'type' => 'integer', 'group' => 'Size2']);
        $this->assertFalse($error);

        [, $renamed] = $this->tool($token, 'catalog_attribute_update', ['attribute' => 'wt', 'new_address' => 'weight-kg']);
        $this->assertEquals('weight-kg', $renamed['address']);

        foreach ([['title' => 'x'], ['attribute' => 'no-such', 'title' => 'x'], ['attribute' => 'color'], ['attribute' => 'color', 'title' => ' '], ['attribute' => 'color', 'type' => 'text'], ['attribute' => 'color', 'title' => 'Look']] as $i => $args) {
            [$error] = $this->tool($token, 'catalog_attribute_update', $args);
            // the last one renames "color" to a name that is not taken: it is allowed, the others are errors
            $i === 5 ? $this->assertFalse($error) : $this->assertTrue($error, json_encode($args));
        }

        [, $dry] = $this->tool($token, 'catalog_attribute_update', ['attribute' => 'color', 'title' => 'Dry', 'dry_run' => true]);
        $this->assertTrue($dry['dry_run']);
        $this->assertEquals('Look', $this->getService(AttributeService::class)->read(['address' => 'color'])->title);
    }
}
