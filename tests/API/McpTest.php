<?php declare(strict_types=1);

namespace tests\API;

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
 * @internal
 *
 * #[CoversNothing]
 */
class McpTest extends TestCase
{
    private const URL = '/api/v1/mcp';

    public function setUp(): void
    {
        parent::setUp();

        $this->getService(ParameterService::class)->create(['name' => 'entity_access', 'value' => 'key']);
    }

    private function rpc(?string $token, mixed $body): \Psr\Http\Message\ResponseInterface
    {
        return $this->createRequest()->post(self::URL, [
            'headers' => array_filter([
                'Content-Type' => 'application/json',
                'Authorization' => $token ? 'Bearer ' . $token : null,
            ]),
            'body' => is_string($body) ? $body : json_encode($body),
        ]);
    }

    private function call(?string $token, string $method, array $params = []): array
    {
        $response = $this->rpc($token, ['jsonrpc' => '2.0', 'id' => 1, 'method' => $method, 'params' => $params ?: new \stdClass()]);
        $this->assertEquals(200, $response->getStatusCode());

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * @return array{0: bool, 1: mixed} [isError, decoded result or error text]
     */
    private function tool(?string $token, string $name, array $args = []): array
    {
        $result = $this->call($token, 'tools/call', ['name' => $name, 'arguments' => $args ?: new \stdClass()]);
        $this->assertArrayHasKey('result', $result, json_encode($result));

        $text = $result['result']['content'][0]['text'];

        return [$result['result']['isError'], json_decode($text, true) ?? $text];
    }

    private function tools(?string $token): array
    {
        return array_column($this->call($token, 'tools/list')['result']['tools'], 'name');
    }

    // protocol

    public function testRequiresKey(): void
    {
        $response = $this->rpc(null, ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'ping']);
        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testInitialize(): void
    {
        $result = $this->call($this->createApiKeyToken(), 'initialize', ['protocolVersion' => '2025-03-26']);

        $this->assertEquals('2025-03-26', $result['result']['protocolVersion']);
        $this->assertArrayHasKey('tools', $result['result']['capabilities']);
        $this->assertEquals('webspace-platform', $result['result']['serverInfo']['name']);

        // unknown version: the server answers with its preferred one
        $result = $this->call($this->createApiKeyToken(), 'initialize', ['protocolVersion' => '1999-01-01']);
        $this->assertEquals('2025-06-18', $result['result']['protocolVersion']);
    }

    public function testTransportEdgeCases(): void
    {
        $token = $this->createApiKeyToken();

        // notification: accepted without a body
        $this->assertEquals(202, $this->rpc($token, ['jsonrpc' => '2.0', 'method' => 'notifications/initialized'])->getStatusCode());

        // no SSE stream
        $response = $this->createRequest()->get(self::URL, ['headers' => ['Authorization' => 'Bearer ' . $token]]);
        $this->assertEquals(405, $response->getStatusCode());

        $response = $this->rpc($token, 'not json');
        $this->assertEquals(400, $response->getStatusCode());
        $this->assertEquals(-32700, json_decode((string) $response->getBody(), true)['error']['code']);

        $this->assertEquals(-32601, $this->call($token, 'completion/complete')['error']['code']);
        $this->assertEquals(-32602, $this->call($token, 'tools/call', ['name' => 'no_such_tool'])['error']['code']);
    }

    // access

    public function testToolsFollowKeyScopes(): void
    {
        $full = $this->tools($this->createApiKeyToken());
        $products = $this->tools($this->createApiKeyToken(['read' => ['catalog/product']], false));

        foreach (['catalog_product_search', 'catalog_order_list', 'site_overview', 'reference_list', 'form_data_list', 'form_data_get'] as $name) {
            $this->assertContains($name, $full);
        }

        $this->assertContains('catalog_product_search', $products);
        $this->assertContains('site_overview', $products);
        $this->assertNotContains('catalog_order_list', $products);
        $this->assertNotContains('catalog_order_get', $products);
        $this->assertNotContains('reference_list', $products);
        // forms are not an API entity: full access keys only
        $this->assertNotContains('form_data_list', $products);

        $result = $this->call($this->createApiKeyToken(['read' => ['catalog/product']], false), 'tools/call', ['name' => 'catalog_order_list']);
        $this->assertEquals(-32602, $result['error']['code']);
    }

    // tools

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

    public function testWave2ToolsFollowKeyScopes(): void
    {
        $orders = $this->tools($this->createApiKeyToken(['read' => ['catalog/order'], 'write' => []], false));
        $this->assertContains('catalog_order_list', $orders);
        $this->assertNotContains('catalog_order_update_status', $orders);

        $writer = $this->tools($this->createApiKeyToken(['read' => ['review'], 'write' => ['review', 'catalog/order']], false));
        $this->assertContains('review_list', $writer);
        $this->assertContains('review_moderate', $writer);
        $this->assertContains('catalog_order_update_status', $writer);
        $this->assertNotContains('guestbook_moderate', $writer);
        $this->assertNotContains('task_retry', $writer);

        $full = $this->tools($this->createApiKeyToken());
        foreach (['catalog_order_update_status', 'review_list', 'review_moderate', 'guestbook_list', 'guestbook_moderate', 'task_list', 'task_retry'] as $name) {
            $this->assertContains($name, $full);
        }
    }

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

    public function testReviewModeration(): void
    {
        $category = $this->getService(CatalogCategoryService::class)->create(['title' => 'Телефоны', 'address' => 'phones']);
        $product = $this->getService(CatalogProductService::class)->create(['title' => 'Смартфон', 'address' => 'phone', 'category_uuid' => $category->uuid]);
        $reviews = $this->getService(ReviewService::class);
        $entry = fn (string $message, string $status) => $reviews->create([
            'type' => 'review', 'entity_type' => 'catalog_product', 'entity_uuid' => $product->uuid, 'rating' => 5, 'message' => $message, 'status' => $status,
        ]);
        $waiting = $entry('Отличный товар', ReviewStatus::MODERATE);
        $entry('Уже на сайте', ReviewStatus::WORK);

        $token = $this->createApiKeyToken(['read' => ['review'], 'write' => ['review']], false);

        [, $result] = $this->tool($token, 'review_list');
        $this->assertCount(1, $result['items']);
        $this->assertEquals('Отличный товар', $result['items'][0]['message']);
        $this->assertEquals('Смартфон', $result['items'][0]['about']['title']);
        $this->assertNull($result['items'][0]['reply']);

        [, $result] = $this->tool($token, 'review_list', ['status' => 'all']);
        $this->assertCount(2, $result['items']);

        [, $result] = $this->tool($token, 'review_moderate', ['uuid' => (string) $waiting->uuid, 'decision' => 'publish', 'response' => 'Спасибо за отзыв!']);
        $this->assertEquals('work', $result['status']);
        $this->assertEquals('Спасибо за отзыв!', $result['reply']);

        // the answer is a child row, not a separate top-level entry
        [, $result] = $this->tool($token, 'review_list', ['status' => 'work']);
        $this->assertCount(2, $result['items']);
        $this->assertContains('Спасибо за отзыв!', array_column($result['items'], 'reply'));

        // change the answer, then remove it
        [, $result] = $this->tool($token, 'review_moderate', ['uuid' => (string) $waiting->uuid, 'response' => 'Благодарим!']);
        $this->assertEquals('Благодарим!', $result['reply']);
        [, $result] = $this->tool($token, 'review_moderate', ['uuid' => (string) $waiting->uuid, 'decision' => 'hide', 'response' => '']);
        $this->assertEquals('moderate', $result['status']);
        $this->assertNull($result['reply']);

        [$error] = $this->tool($token, 'review_moderate', ['uuid' => (string) $waiting->uuid]);
        $this->assertTrue($error);
        [$error] = $this->tool($token, 'review_moderate', ['uuid' => (string) $waiting->uuid, 'decision' => 'delete']);
        $this->assertTrue($error);
        [$error] = $this->tool($token, 'review_list', ['status' => 'bad']);
        $this->assertTrue($error);
    }

    public function testGuestBookModeration(): void
    {
        $guestBook = $this->getService(GuestBookService::class);
        $entry = $guestBook->create(['name' => 'Иван', 'email' => 'ivan@example.com', 'message' => 'Хороший сайт', 'status' => 'moderate']);

        $token = $this->createApiKeyToken(['read' => ['guestbook'], 'write' => ['guestbook']], false);

        [, $result] = $this->tool($token, 'guestbook_list');
        $this->assertEquals(['Хороший сайт'], array_column($result['items'], 'message'));

        [, $result] = $this->tool($token, 'guestbook_moderate', ['uuid' => (string) $entry->uuid, 'decision' => 'publish', 'response' => 'Спасибо!']);
        $this->assertEquals('work', $result['status']);
        $this->assertEquals('Спасибо!', $result['response']);

        [, $result] = $this->tool($token, 'guestbook_list');
        $this->assertCount(0, $result['items']);
        [, $result] = $this->tool($token, 'guestbook_list', ['status' => 'work', 'search' => 'ИВАН']);
        $this->assertCount(1, $result['items']);

        [$error] = $this->tool($token, 'guestbook_moderate', ['uuid' => (string) $entry->uuid]);
        $this->assertTrue($error);
    }

    public function testTaskListAndRetry(): void
    {
        $tasks = $this->getService(TaskService::class);
        $failed = $tasks->create(['title' => 'Упавшая', 'action' => \App\Domain\Tasks\ConvertImageTask::class, 'params' => ['uuid' => []], 'status' => TaskStatus::FAIL, 'output' => 'Файл не найден']);
        $mail = $tasks->create(['title' => 'Письмо', 'action' => \App\Domain\Tasks\SendMailTask::class, 'status' => TaskStatus::FAIL]);
        $done = $tasks->create(['title' => 'Готово', 'action' => \App\Domain\Tasks\ConvertImageTask::class, 'status' => TaskStatus::DONE]);

        $token = $this->createApiKeyToken(['read' => ['task'], 'write' => ['task']], false);

        [, $result] = $this->tool($token, 'task_list');
        $this->assertEqualsCanonicalizing(['Упавшая', 'Письмо'], array_column($result['items'], 'title'));
        $this->assertContains('Файл не найден', array_column($result['items'], 'output'));

        [, $result] = $this->tool($token, 'task_list', ['status' => 'all']);
        $this->assertCount(3, $result['items']);

        [$error, $text] = $this->tool($token, 'task_retry', ['uuid' => (string) $mail->uuid]);
        $this->assertTrue($error);
        $this->assertStringContainsString('e-mails', $text);

        [$error] = $this->tool($token, 'task_retry', ['uuid' => (string) $done->uuid]);
        $this->assertTrue($error);

        [$error, $result] = $this->tool($token, 'task_retry', ['uuid' => (string) $failed->uuid]);
        $this->assertFalse($error);
        $this->assertEquals('queue', $result['status']);

        // the background worker may already be done with it, but it is not failed with the old output
        $task = $tasks->read(['uuid' => (string) $failed->uuid]);
        $this->assertNotEquals('Файл не найден', $task->output);
    }

    // wave 3: content

    public function testWave3ToolsFollowKeyScopes(): void
    {
        $reader = $this->tools($this->createApiKeyToken(['read' => ['page', 'publication', 'catalog/product']], false));
        $this->assertContains('page_list', $reader);
        $this->assertContains('page_get', $reader);
        $this->assertContains('publication_list', $reader);
        foreach (['page_update', 'publication_create', 'publication_update', 'catalog_product_update', 'catalog_price_bulk_update', 'catalog_category_update'] as $name) {
            $this->assertNotContains($name, $reader);
        }

        $writer = $this->tools($this->createApiKeyToken(['read' => [], 'write' => ['page', 'catalog/product']], false));
        $this->assertContains('page_update', $writer);
        $this->assertContains('catalog_product_update', $writer);
        $this->assertContains('catalog_price_bulk_update', $writer);
        $this->assertNotContains('publication_update', $writer);
        $this->assertNotContains('catalog_category_update', $writer);
    }

    private function makeProduct(string $title, string $categoryUuid, array $extra = []): object
    {
        return $this->getService(CatalogProductService::class)->create(array_merge([
            'title' => $title,
            'address' => 'p-' . uniqid(),
            'category_uuid' => $categoryUuid,
            'price' => 100,
            'stock' => 5,
        ], $extra));
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

    public function testPublications(): void
    {
        $category = $this->getService(PublicationCategoryService::class)->create(['title' => 'Новости', 'address' => 'news']);
        $token = $this->createApiKeyToken(['read' => ['publication'], 'write' => ['publication']], false);

        // a draft by default
        [$error, $created] = $this->tool($token, 'publication_create', [
            'title' => 'Открытие магазина',
            'category_uuid' => (string) $category->uuid,
            'short' => '<p>Коротко</p>',
            'full' => '<p>Полный <i>текст</i></p><script>alert(1)</script><a href="javascript:x()">link</a>',
            'meta_title' => 'SEO',
        ]);
        $this->assertFalse($error, json_encode($created));
        $this->assertEquals('draft', $created['state']);
        $this->assertEquals('<p>Полный <i>текст</i></p><a>link</a>', $created['full']);
        $this->assertEquals('SEO', $created['meta']['title']);
        $this->assertMatchesRegularExpression('#^news/[a-z0-9-]+$#', $created['address']);
        $uuid = $created['uuid'];

        // not shown on the public site, visible to the tools
        $this->assertCount(0, $this->getService(\App\Domain\Service\Publication\PublicationService::class)->read(['date_to' => datetime()->format('Y-m-d H:i:s')]));
        [, $list] = $this->tool($token, 'publication_list', ['state' => 'draft']);
        $this->assertEquals(['Открытие магазина'], array_column($list['items'], 'title'));
        [, $list] = $this->tool($token, 'publication_list', ['state' => 'published']);
        $this->assertCount(0, $list['items']);

        // edit the text, the address stays
        [, $result] = $this->tool($token, 'publication_update', ['uuid' => $uuid, 'title' => 'Открытие нового магазина', 'full' => '<p>Новый текст</p>']);
        $this->assertEquals($created['address'], $result['address']);
        $this->assertEquals('<p>Новый текст</p>', $result['full']);
        $this->assertEquals('<p>Коротко</p>', $result['short']);
        $this->assertEquals('draft', $result['state']);

        // publish now, then back to a draft
        [, $result] = $this->tool($token, 'publication_update', ['uuid' => $uuid, 'publish' => true]);
        $this->assertEquals('published', $result['state']);
        [, $list] = $this->tool($token, 'publication_list', ['state' => 'published', 'search' => 'НОВОГО']);
        $this->assertCount(1, $list['items']);
        $this->assertCount(1, $this->getService(\App\Domain\Service\Publication\PublicationService::class)->read(['date_to' => datetime()->addMinute()->format('Y-m-d H:i:s')]));

        [, $result] = $this->tool($token, 'publication_update', ['uuid' => $uuid, 'unpublish' => true]);
        $this->assertEquals('draft', $result['state']);

        // scheduled
        [, $result] = $this->tool($token, 'publication_update', ['uuid' => $uuid, 'publish_at' => '2031-05-01 10:00']);
        $this->assertEquals('scheduled', $result['state']);
        $this->assertEquals('2031-05-01 10:00', $result['date']);

        [, $result] = $this->tool($token, 'publication_get', ['address' => $created['address']]);
        $this->assertEquals('Открытие нового магазина', $result['title']);

        foreach ([
            ['tool' => 'publication_create', 'args' => ['title' => 'Без категории']],
            ['tool' => 'publication_create', 'args' => ['title' => 'Плохая категория', 'category_uuid' => \Ramsey\Uuid\Uuid::uuid4()->toString()]],
            ['tool' => 'publication_create', 'args' => ['title' => 'Дата', 'category_uuid' => (string) $category->uuid, 'publish_at' => '01.05.2031']],
            ['tool' => 'publication_create', 'args' => ['title' => 'Открытие нового магазина', 'category_uuid' => (string) $category->uuid]],
            ['tool' => 'publication_update', 'args' => ['uuid' => $uuid]],
            ['tool' => 'publication_update', 'args' => ['uuid' => $uuid, 'publish' => true, 'unpublish' => true]],
            ['tool' => 'publication_update', 'args' => ['uuid' => $uuid, 'title' => '']],
            ['tool' => 'publication_get', 'args' => []],
            ['tool' => 'publication_list', 'args' => ['state' => 'later']],
        ] as $case) {
            [$error] = $this->tool($token, $case['tool'], $case['args']);
            $this->assertTrue($error, json_encode($case));
        }
    }

    public function testPages(): void
    {
        $pages = $this->getService(PageService::class);
        $page = $pages->create(['title' => 'О компании', 'address' => 'about', 'content' => '<p>Старый текст</p>']);
        $pages->create(['title' => 'Контакты', 'address' => 'contacts', 'content' => '<p>Адрес</p>']);

        $token = $this->createApiKeyToken(['read' => ['page'], 'write' => ['page']], false);

        [, $list] = $this->tool($token, 'page_list');
        $this->assertEquals(['Контакты', 'О компании'], array_column($list['items'], 'title'));
        [, $list] = $this->tool($token, 'page_list', ['search' => 'КОМПАНИИ']);
        $this->assertEquals(['about'], array_column($list['items'], 'address'));

        [, $result] = $this->tool($token, 'page_get', ['address' => 'about']);
        $this->assertEquals('<p>Старый текст</p>', $result['content']);

        [$error, $result] = $this->tool($token, 'page_update', [
            'uuid' => (string) $page->uuid,
            'title' => 'О нас',
            'content' => '<h2>О нас</h2><p onmouseover="x()">Новый текст</p><script>alert(1)</script>',
            'meta_title' => 'SEO',
        ]);
        $this->assertFalse($error, json_encode($result));
        $this->assertEquals('about', $result['address']);
        $this->assertEqualsCanonicalizing(['title', 'content', 'meta'], $result['changed_fields']);

        [, $result] = $this->tool($token, 'page_get', ['uuid' => (string) $page->uuid]);
        $this->assertEquals('О нас', $result['title']);
        $this->assertEquals('<h2>О нас</h2><p>Новый текст</p>', $result['content']);
        $this->assertEquals('SEO', $result['meta']['title']);

        foreach ([
            ['tool' => 'page_update', 'args' => ['uuid' => (string) $page->uuid]],
            ['tool' => 'page_update', 'args' => ['uuid' => (string) $page->uuid, 'title' => 'Контакты']],
            ['tool' => 'page_update', 'args' => ['uuid' => 'nope', 'title' => 'x']],
            ['tool' => 'page_get', 'args' => ['address' => 'missing']],
            ['tool' => 'page_get', 'args' => []],
        ] as $case) {
            [$error] = $this->tool($token, $case['tool'], $case['args']);
            $this->assertTrue($error, json_encode($case));
        }
    }

    // wave 4: users and the rest

    public function testWave4ToolsFollowKeyScopes(): void
    {
        $catalog = $this->tools($this->createApiKeyToken(['read' => ['catalog/attributes', 'file']], false));
        $this->assertContains('catalog_attribute_list', $catalog);
        $this->assertContains('file_list', $catalog);
        foreach (['user_list', 'user_get', 'user_update', 'user_group_list', 'subscriber_list'] as $name) {
            $this->assertNotContains($name, $catalog);
        }

        $reader = $this->tools($this->createApiKeyToken(['read' => ['user', 'user/group']], false));
        $this->assertContains('user_list', $reader);
        $this->assertContains('user_get', $reader);
        $this->assertContains('user_group_list', $reader);
        $this->assertNotContains('user_update', $reader);
        // not an API entity: full access keys only
        $this->assertNotContains('subscriber_list', $reader);

        $full = $this->tools($this->createApiKeyToken());
        foreach (['user_update', 'subscriber_list', 'file_list', 'catalog_attribute_list'] as $name) {
            $this->assertContains($name, $full);
        }
    }

    public function testUsers(): void
    {
        $groups = $this->getService(UserGroupService::class);
        $customers = $groups->create(['title' => 'Покупатели']);
        $vips = $groups->create(['title' => 'VIP']);
        $staff = $groups->create(['title' => 'Администраторы', 'access' => ['cup:main']]);

        $users = $this->getService(UserService::class);
        $ivan = $users->create(['username' => 'ivan', 'email' => 'ivan@example.com', 'phone' => '+79000000001', 'firstname' => 'Иван', 'lastname' => 'Петров', 'group_uuid' => $customers->uuid, 'password' => 'secret-pass']);
        $admin = $users->create(['username' => 'boss', 'email' => 'boss@example.com', 'firstname' => 'Босс', 'group_uuid' => $staff->uuid, 'password' => 'secret-pass']);
        $users->create(['username' => 'lonely', 'email' => 'lonely@example.com', 'firstname' => 'Без группы']);
        $blocked = $users->create(['username' => 'blocked', 'email' => 'blocked@example.com', 'status' => 'block']);

        $token = $this->createApiKeyToken(['read' => ['user', 'user/group'], 'write' => ['user']], false);

        // list: active by default, search ignores case, filters
        [, $result] = $this->tool($token, 'user_list');
        $this->assertCount(3, $result['items']);
        $this->assertStringNotContainsString('secret-pass', json_encode($result));
        [, $result] = $this->tool($token, 'user_list', ['search' => 'ПЕТРОВ']);
        $this->assertEquals(['ivan'], array_column($result['items'], 'username'));
        [, $result] = $this->tool($token, 'user_list', ['group' => 'покупатели']);
        $this->assertEquals(['ivan'], array_column($result['items'], 'username'));
        [, $result] = $this->tool($token, 'user_list', ['without_group' => true]);
        $this->assertEquals(['lonely'], array_column($result['items'], 'username'));
        [, $result] = $this->tool($token, 'user_list', ['status' => 'block']);
        $this->assertEquals(['blocked'], array_column($result['items'], 'username'));
        [, $result] = $this->tool($token, 'user_list', ['status' => 'all']);
        $this->assertCount(4, $result['items']);
        [$error, $text] = $this->tool($token, 'user_list', ['group' => 'нет такой']);
        $this->assertTrue($error);
        $this->assertStringContainsString('Покупатели', $text);
        [$error] = $this->tool($token, 'user_list', ['status' => 'gone']);
        $this->assertTrue($error);

        // get: by any identifier, no secrets
        [, $result] = $this->tool($token, 'user_get', ['email' => 'IVAN@example.com']);
        $this->assertEquals('Иван', $result['firstname']);
        $this->assertEquals('Покупатели', $result['group']);
        $this->assertFalse($result['staff']);
        $this->assertArrayNotHasKey('password', $result);
        $this->assertArrayNotHasKey('tokens', $result);
        [, $result] = $this->tool($token, 'user_get', ['uuid' => (string) $blocked->uuid]);
        $this->assertEquals('block', $result['status']);
        [, $result] = $this->tool($token, 'user_get', ['uuid' => (string) $admin->uuid]);
        $this->assertTrue($result['staff']);
        [$error] = $this->tool($token, 'user_get');
        $this->assertTrue($error);
        [$error] = $this->tool($token, 'user_get', ['email' => 'nobody@example.com']);
        $this->assertTrue($error);

        // groups
        [, $result] = $this->tool($token, 'user_group_list');
        $byTitle = array_column($result, null, 'title');
        $this->assertEquals(1, $byTitle['Покупатели']['members']);
        $this->assertFalse($byTitle['Покупатели']['staff']);
        $this->assertTrue($byTitle['Администраторы']['staff']);
        $this->assertArrayNotHasKey('access', $byTitle['Администраторы']);

        // update: partial json merge, group, block
        [$error, $result] = $this->tool($token, 'user_update', [
            'uuid' => (string) $ivan->uuid,
            'city' => 'Уфа',
            'birthdate' => '1990-05-17',
            'about' => 'Постоянный клиент',
            'company' => ['position' => 'Директор'],
            'messenger' => ['telegram' => '@ivan'],
            'allow_mail' => false,
            'group' => 'vip',
        ]);
        $this->assertFalse($error, json_encode($result));
        $this->assertEquals('Уфа', $result['city']);
        $this->assertEquals('1990-05-17', $result['birthdate']);
        $this->assertEquals('VIP', $result['group']);
        $this->assertEquals('Директор', $result['company']['position']);
        $this->assertEquals('@ivan', $result['messenger']['telegram']);
        $this->assertFalse($result['allow_mail']);
        $this->assertEqualsCanonicalizing(['city', 'birthdate', 'additional', 'company', 'messenger', 'is_allow_mail', 'group_uuid'], $result['changed_fields']);

        [, $result] = $this->tool($token, 'user_update', ['uuid' => (string) $ivan->uuid, 'company' => ['title' => 'ООО Иван']]);
        $this->assertEquals(['title' => 'ООО Иван', 'position' => 'Директор'], $result['company']);

        [, $result] = $this->tool($token, 'user_update', ['uuid' => (string) $ivan->uuid, 'status' => 'block']);
        $this->assertEquals('block', $result['status']);
        [, $result] = $this->tool($token, 'user_update', ['uuid' => (string) $ivan->uuid, 'status' => 'work', 'birthdate' => '']);
        $this->assertEquals('work', $result['status']);
        $this->assertNull($result['birthdate']);

        // email taken is reported, nothing else changes
        [$error] = $this->tool($token, 'user_update', ['uuid' => (string) $ivan->uuid, 'email' => 'boss@example.com']);
        $this->assertTrue($error);

        // staff is out of reach in both directions, nothing is deleted or escalated
        [$error, $text] = $this->tool($token, 'user_update', ['uuid' => (string) $admin->uuid, 'city' => 'Москва']);
        $this->assertTrue($error);
        $this->assertStringContainsString('staff', $text);
        [$error] = $this->tool($token, 'user_update', ['uuid' => (string) $ivan->uuid, 'group' => 'Администраторы']);
        $this->assertTrue($error);
        $this->assertEquals('VIP', $this->tool($token, 'user_get', ['uuid' => (string) $ivan->uuid])[1]['group']);

        foreach ([
            ['uuid' => (string) $ivan->uuid],
            ['uuid' => (string) $ivan->uuid, 'status' => 'delete'],
            ['uuid' => (string) $ivan->uuid, 'status' => 'confirmation'],
            ['uuid' => (string) $ivan->uuid, 'birthdate' => '17.05.1990'],
            ['uuid' => (string) $ivan->uuid, 'group' => 'нет такой'],
            ['uuid' => 'nope', 'city' => 'x'],
            ['uuid' => \Ramsey\Uuid\Uuid::uuid4()->toString(), 'city' => 'x'],
        ] as $args) {
            [$error] = $this->tool($token, 'user_update', $args);
            $this->assertTrue($error, json_encode($args));
        }

        // password and username are not tools' business
        $before = $this->getService(UserService::class)->read(['uuid' => (string) $ivan->uuid]);
        $this->tool($token, 'user_update', ['uuid' => (string) $ivan->uuid, 'password' => 'hacked', 'username' => 'hacked', 'city' => 'Казань']);
        $after = $this->getService(UserService::class)->read(['uuid' => (string) $ivan->uuid]);
        $this->assertEquals($before->password, $after->password);
        $this->assertEquals('ivan', $after->username);
        $this->assertEquals('Казань', $after->city);
    }

    public function testAttributesFilesAndSubscribers(): void
    {
        $attributes = $this->getService(AttributeService::class);
        $attributes->create(['title' => 'Цвет', 'address' => 'color', 'type' => 'string', 'group' => 'Основные', 'is_filter' => true]);
        $attributes->create(['title' => 'Вес', 'address' => 'weight', 'type' => 'float', 'is_filter' => false]);

        $token = $this->createApiKeyToken();

        [, $result] = $this->tool($token, 'catalog_attribute_list');
        $this->assertCount(2, $result['items']);
        [, $result] = $this->tool($token, 'catalog_attribute_list', ['search' => 'ЦВЕТ']);
        $this->assertEquals(['color'], array_column($result['items'], 'address'));
        [, $result] = $this->tool($token, 'catalog_attribute_list', ['is_filter' => true]);
        $this->assertEquals(['color'], array_column($result['items'], 'address'));
        [, $result] = $this->tool($token, 'catalog_attribute_list', ['group' => 'Основные']);
        $this->assertEquals(['color'], array_column($result['items'], 'address'));

        // files
        $files = $this->getService(FileService::class);
        foreach ([['photo-one.jpg', 'image/jpeg'], ['photo-two.png', 'image/png'], ['price.pdf', 'application/pdf']] as [$name, $type]) {
            $path = CACHE_DIR . '/' . $name;
            $image = imagecreatetruecolor(20, 20);
            imagefill($image, 0, 0, imagecolorallocate($image, random_int(0, 255), random_int(0, 255), random_int(0, 255)));
            imagestring($image, 2, 1, 1, uniqid(), imagecolorallocate($image, 255, 255, 255));
            imagejpeg($image, $path);
            $file = $files->createFromPath($path, $name);
            $this->assertNotNull($file);
            $files->update($file, ['type' => $type]);
        }

        [, $result] = $this->tool($token, 'file_list');
        $this->assertCount(3, $result['items']);
        $this->assertStringStartsWith('/uploads/', parse_url($result['items'][0]['url'], PHP_URL_PATH));
        [, $result] = $this->tool($token, 'file_list', ['kind' => 'image']);
        $this->assertCount(2, $result['items']);
        [, $result] = $this->tool($token, 'file_list', ['kind' => 'document']);
        $this->assertEquals(['price.pdf'], array_column($result['items'], 'name'));
        [, $result] = $this->tool($token, 'file_list', ['kind' => 'image', 'limit' => 1]);
        $this->assertCount(1, $result['items']);
        $this->assertTrue($result['has_more']);
        [, $result] = $this->tool($token, 'file_list', ['search' => 'PHOTO']);
        $this->assertCount(2, $result['items']);
        [$error] = $this->tool($token, 'file_list', ['kind' => 'archive']);
        $this->assertTrue($error);

        // subscribers
        $subscribers = $this->getService(SubscriberService::class);
        $subscribers->create(['email' => 'one@example.com']);
        $subscribers->create(['email' => 'two@example.org']);

        [, $result] = $this->tool($token, 'subscriber_list');
        $this->assertCount(2, $result['items']);
        [, $result] = $this->tool($token, 'subscriber_list', ['search' => 'EXAMPLE.ORG']);
        $this->assertEquals(['two@example.org'], array_column($result['items'], 'email'));
    }

    // prompts and resources

    private function prompts(?string $token): array
    {
        return array_column($this->call($token, 'prompts/list')['result']['prompts'], null, 'name');
    }

    private function resources(?string $token): array
    {
        return array_column($this->call($token, 'resources/list')['result']['resources'], null, 'uri');
    }

    public function testCapabilitiesAnnouncePromptsAndResources(): void
    {
        $result = $this->call($this->createApiKeyToken(), 'initialize', ['protocolVersion' => '2025-06-18']);

        $this->assertArrayHasKey('prompts', $result['result']['capabilities']);
        $this->assertArrayHasKey('resources', $result['result']['capabilities']);
    }

    public function testPromptsFollowAvailableTools(): void
    {
        $full = $this->prompts($this->createApiKeyToken());
        $this->assertEqualsCanonicalizing(
            ['handle_new_orders', 'moderate_content', 'site_report', 'change_prices', 'write_publication', 'check_failed_tasks'],
            array_keys($full)
        );

        // a prompt is offered only if the caller may use every tool it needs
        $catalog = $this->prompts($this->createApiKeyToken(['read' => ['catalog/product', 'catalog/category'], 'write' => ['catalog/product']], false));
        $this->assertArrayHasKey('change_prices', $catalog);
        $this->assertArrayNotHasKey('handle_new_orders', $catalog);
        $this->assertArrayNotHasKey('moderate_content', $catalog);

        $reviews = $this->prompts($this->createApiKeyToken(['read' => ['review'], 'write' => ['review']], false));
        $this->assertArrayNotHasKey('moderate_content', $reviews, 'guestbook tools are missing');

        $this->assertTrue($full['change_prices']['arguments'][0]['required']);
        $this->assertArrayHasKey('title', $full['site_report']);
    }

    public function testPromptGet(): void
    {
        $token = $this->createApiKeyToken();

        $result = $this->call($token, 'prompts/get', ['name' => 'handle_new_orders', 'arguments' => ['status' => 'В работе']]);
        $message = $result['result']['messages'][0];
        $this->assertEquals('user', $message['role']);
        $this->assertEquals('text', $message['content']['type']);
        $this->assertStringContainsString('catalog_order_list', $message['content']['text']);
        $this->assertStringContainsString('"В работе"', $message['content']['text']);
        $this->assertStringContainsString('Never change or publish anything without', $message['content']['text']);

        $text = $this->call($token, 'prompts/get', ['name' => 'site_report', 'arguments' => ['days' => '30']])['result']['messages'][0]['content']['text'];
        $this->assertStringContainsString('last 30 days', $text);
        $this->assertStringContainsString('date_from=' . datetime()->subDays(30)->format('Y-m-d'), $text);
        // a silly period is clamped
        $this->assertStringContainsString('last 90 days', $this->call($token, 'prompts/get', ['name' => 'site_report', 'arguments' => ['days' => '5000']])['result']['messages'][0]['content']['text']);

        $text = $this->call($token, 'prompts/get', ['name' => 'change_prices', 'arguments' => ['category' => 'Куртки', 'change' => '-7,5%']])['result']['messages'][0]['content']['text'];
        $this->assertStringContainsString('percent=-7.5', $text);
        $this->assertStringContainsString('WITHOUT apply', $text);
        $text = $this->call($token, 'prompts/get', ['name' => 'change_prices', 'arguments' => ['category' => 'Куртки', 'change' => '+300']])['result']['messages'][0]['content']['text'];
        $this->assertStringContainsString('amount=300', $text);

        foreach ([
            ['name' => 'change_prices'],
            ['name' => 'change_prices', 'arguments' => ['category' => 'Куртки']],
            ['name' => 'change_prices', 'arguments' => ['category' => 'Куртки', 'change' => 'вдвое']],
            ['name' => 'write_publication', 'arguments' => ['topic' => ' ']],
            ['name' => 'no_such_prompt'],
            [],
        ] as $params) {
            $this->assertEquals(-32602, $this->call($token, 'prompts/get', $params)['error']['code'], json_encode($params));
        }

        // not offered = not available by name either
        $limited = $this->createApiKeyToken(['read' => ['catalog/product']], false);
        $this->assertEquals(-32602, $this->call($limited, 'prompts/get', ['name' => 'handle_new_orders'])['error']['code']);
    }

    public function testResources(): void
    {
        $this->getService(ParameterService::class)->create(['name' => 'common_title', 'value' => 'Мой магазин']);
        $categories = $this->getService(CatalogCategoryService::class);
        $parent = $categories->create(['title' => 'Одежда', 'address' => 'wear']);
        $categories->create(['title' => 'Куртки', 'address' => 'jackets', 'parent_uuid' => $parent->uuid]);
        $references = $this->getService(ReferenceService::class);
        $references->create(['type' => ReferenceType::ORDER_STATUS, 'title' => 'Новый', 'order' => 1]);
        $references->create(['type' => ReferenceType::ORDER_STATUS, 'title' => 'Выполнен', 'order' => 2]);

        $token = $this->createApiKeyToken();
        $list = $this->resources($token);

        $this->assertEqualsCanonicalizing(
            ['platform://site', 'platform://guide/rules', 'platform://catalog/categories', 'platform://reference/order_statuses'],
            array_keys($list)
        );
        $this->assertEquals('text/markdown', $list['platform://guide/rules']['mimeType']);

        $read = fn (string $uri) => $this->call($token, 'resources/read', ['uri' => $uri])['result']['contents'][0];

        $site = json_decode($read('platform://site')['text'], true);
        $this->assertEquals('Мой магазин', $site['title']);
        $this->assertContains('catalog', $site['sections_enabled']);

        $tree = json_decode($read('platform://catalog/categories')['text'], true);
        $this->assertEquals('Одежда', $tree[0]['title']);
        $this->assertEquals('Куртки', $tree[0]['children'][0]['title']);
        $this->assertArrayNotHasKey('children', $tree[0]['children'][0]);

        $statuses = json_decode($read('platform://reference/order_statuses')['text'], true);
        $this->assertEquals(['Новый', 'Выполнен'], array_column($statuses, 'title'));

        $rules = $read('platform://guide/rules');
        $this->assertEquals('platform://guide/rules', $rules['uri']);
        $this->assertStringContainsString('Nothing is deleted', $rules['text']);

        // a key sees only the resources its scopes allow
        $limited = $this->resources($this->createApiKeyToken(['read' => ['catalog/category']], false));
        $this->assertArrayHasKey('platform://catalog/categories', $limited);
        $this->assertArrayHasKey('platform://site', $limited);
        $this->assertArrayNotHasKey('platform://reference/order_statuses', $limited);

        $other = $this->createApiKeyToken(['read' => ['catalog/category']], false);
        $this->assertEquals(-32002, $this->call($other, 'resources/read', ['uri' => 'platform://reference/order_statuses'])['error']['code']);
        $this->assertEquals(-32002, $this->call($token, 'resources/read', ['uri' => 'platform://nope'])['error']['code']);
        $this->assertEquals(-32002, $this->call($token, 'resources/read')['error']['code']);

        $this->assertEquals([], $this->call($token, 'resources/templates/list')['result']['resourceTemplates']);
    }

    public function testPluginsCanRegisterPromptsAndResources(): void
    {
        $registry = $this->getService('mcp');

        $prompt = new class($this->getService(\Psr\Container\ContainerInterface::class)) extends \App\Domain\AbstractMcpPrompt {
            public const NAME = 'plugin_prompt';
            public const TOOLS = ['no_such_tool'];

            public function render(array $args = []): string
            {
                return 'x';
            }
        };
        $resource = new class($this->getService(\Psr\Container\ContainerInterface::class)) extends \App\Domain\AbstractMcpResource {
            public const URI = 'plugin://data';
            public const NAME = 'plugin_data';

            public function read(): string
            {
                return '{}';
            }
        };

        $this->assertTrue($registry->registerPrompt($prompt));
        $this->assertFalse($registry->registerPrompt($prompt), 'the second registration of a name is ignored');
        $this->assertTrue($registry->registerResource($resource));
        $this->assertSame($prompt, $registry->findPrompt('plugin_prompt'));
        $this->assertSame($resource, $registry->findResource('plugin://data'));
        $this->assertNull($registry->findPrompt('missing'));

        // the prompt needs a tool nobody has: it is never offered
        $this->assertFalse($prompt->isAvailable(['catalog_order_list']));
        $this->assertTrue($prompt->isAvailable(['no_such_tool']));
    }

    public function testSiteOverviewFollowsKeyScopes(): void
    {
        $this->getService(CatalogOrderService::class)->create(['phone' => '+79000000001']);

        [$error, $full] = $this->tool($this->createApiKeyToken(), 'site_overview');
        $this->assertFalse($error);
        $this->assertEquals(1, $full['attention']['orders']);
        $this->assertArrayHasKey('users', $full['attention']);
        $this->assertArrayHasKey('revenue_30_days', $full);

        [, $limited] = $this->tool($this->createApiKeyToken(['read' => ['catalog/product']], false), 'site_overview');
        $this->assertArrayNotHasKey('orders', $limited['attention'] ?? []);
        $this->assertArrayNotHasKey('users', $limited['attention'] ?? []);
        $this->assertArrayNotHasKey('revenue_30_days', $limited);

        // without a key there is no access at all
        $this->assertEquals(401, $this->rpc(null, ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'])->getStatusCode());
    }

    public function testFormData(): void
    {
        $form = $this->getService(FormService::class)->create(['title' => 'Обратная связь', 'address' => 'feedback']);
        $this->getService(FormService::class)->create(['title' => 'Другая', 'address' => 'other']);
        $data = $this->getService(FormDataService::class)->create([
            'form_uuid' => $form->uuid,
            'data' => ['name' => 'Иван', 'phone' => '+79000000001', 'recaptcha' => 'secret'],
            'message' => 'Перезвоните',
        ]);

        $token = $this->createApiKeyToken();

        [, $result] = $this->tool($token, 'form_data_list');
        $this->assertCount(1, $result['items']);
        $this->assertEquals('Обратная связь', $result['items'][0]['form']);
        $this->assertArrayNotHasKey('recaptcha', $result['items'][0]['preview']);
        $this->assertEqualsCanonicalizing(['feedback', 'other'], array_column($result['forms'], 'address'));

        [, $result] = $this->tool($token, 'form_data_list', ['form' => 'other']);
        $this->assertCount(0, $result['items']);

        [$error] = $this->tool($token, 'form_data_list', ['form' => 'missing']);
        $this->assertTrue($error);

        [, $result] = $this->tool($token, 'form_data_get', ['uuid' => (string) $data->uuid]);
        $this->assertEquals('Иван', $result['fields']['name']);
        $this->assertEquals('Перезвоните', $result['message']);
        $this->assertArrayNotHasKey('recaptcha', $result['fields']);
    }
}
