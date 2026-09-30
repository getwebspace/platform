<?php declare(strict_types=1);

namespace tests\API;

use App\Domain\Casts\Reference\Type as ReferenceType;
use App\Domain\Service\Catalog\CategoryService as CatalogCategoryService;
use App\Domain\Service\Catalog\OrderService as CatalogOrderService;
use App\Domain\Service\Catalog\ProductService as CatalogProductService;
use App\Domain\Service\Form\DataService as FormDataService;
use App\Domain\Service\Form\FormService;
use App\Domain\Service\Parameter\ParameterService;
use App\Domain\Service\Reference\ReferenceService;
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

        $this->assertEquals(-32601, $this->call($token, 'resources/list')['error']['code']);
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
