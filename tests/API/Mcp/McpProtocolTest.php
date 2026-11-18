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
 * The protocol itself: keys, scopes, prompts, resources, limits, dry runs and the journal
 *
 * @internal
 *
 * #[CoversNothing]
 */
class McpProtocolTest extends McpTestCase
{
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
            ['handle_new_orders', 'moderate_content', 'site_report', 'change_prices', 'write_publication', 'check_failed_tasks', 'seo_check', 'sales_analysis', 'catalog_check', 'edit_template'],
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

    // review follow-up: anonymous access, strict limits, dry run, journal

    public function testAnonymousAccessSeesOnlyPublicData(): void
    {
        $this->getService(ParameterService::class)->update($this->getService(ParameterService::class)->read(['name' => 'entity_access']), ['value' => 'all']);

        $categories = $this->getService(CatalogCategoryService::class);
        $categories->create(['title' => 'Открытая', 'address' => 'open']);
        $categories->create(['title' => 'Скрытая', 'address' => 'secret', 'is_hidden' => true]);

        $anonymous = $this->tools(null);

        foreach (['catalog_product_search', 'catalog_product_get', 'catalog_category_list', 'catalog_attribute_list', 'page_list', 'page_get'] as $name) {
            $this->assertContains($name, $anonymous);
        }
        // things that are not public: reviews waiting for moderation, files, reference books, everything that writes
        foreach (['review_list', 'file_list', 'reference_list', 'publication_list', 'user_list', 'catalog_order_list', 'site_overview', 'page_update', 'catalog_product_update'] as $name) {
            $this->assertNotContains($name, $anonymous, $name);
        }
        $this->assertEquals(-32602, $this->call(null, 'tools/call', ['name' => 'review_list'])['error']['code']);
        $this->assertCount(6, $anonymous);

        // hidden products: not found, not read, not asked for by a caller without a key
        $this->makeProduct('Hidden thing', (string) \App\Domain\Models\CatalogCategory::query()->where('address', 'open')->value('uuid'), ['status' => 'delete', 'vendorcode' => 'HID-1']);
        [, $found] = $this->tool(null, 'catalog_product_search', ['query' => 'Hidden thing']);
        $this->assertEquals(0, $found['total']);
        [$error] = $this->tool(null, 'catalog_product_search', ['query' => 'Hidden thing', 'status' => 'all']);
        $this->assertTrue($error);
        [$error] = $this->tool(null, 'catalog_product_get', ['vendorcode' => 'HID-1']);
        $this->assertTrue($error);
        [$error] = $this->tool($this->createApiKeyToken(), 'catalog_product_get', ['vendorcode' => 'HID-1']);
        $this->assertFalse($error);

        // a hidden category is not shown without a key, with a key it is
        [, $result] = $this->tool(null, 'catalog_category_list');
        $this->assertEquals(['Открытая'], array_column($result, 'title'));
        [, $result] = $this->tool($this->createApiKeyToken(), 'catalog_category_list');
        $this->assertEqualsCanonicalizing(['Открытая', 'Скрытая'], array_column($result, 'title'));

        $resources = array_keys($this->resources(null));
        $this->assertContains('platform://site', $resources);
        $this->assertNotContains('platform://catalog/categories', $resources);
        $this->assertNotContains('platform://reference/order_statuses', $resources);
    }

    public function testWrongKeyIsNotTreatedAsAnonymous(): void
    {
        $this->getService(ParameterService::class)->update($this->getService(ParameterService::class)->read(['name' => 'entity_access']), ['value' => 'all']);

        $ping = ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'ping'];

        // no credentials: anonymous access is allowed by the site settings
        $this->assertEquals(200, $this->rpc(null, $ping)->getStatusCode());
        // credentials that do not work: refused, the client must not silently get less than it asked for
        $this->assertEquals(401, $this->rpc('garbage.token.value', $ping)->getStatusCode());
        $this->assertEquals(401, $this->createRequest()->post(self::URL, ['headers' => ['apikey' => 'garbage'], 'body' => json_encode($ping)])->getStatusCode());
        $this->assertEquals(401, $this->createRequest()->post(self::URL . '?apikey=garbage', ['body' => json_encode($ping)])->getStatusCode());
        $this->assertEquals(200, $this->rpc($this->createApiKeyToken(), $ping)->getStatusCode());

        // not our credentials (a proxy login) are no reason to refuse
        $response = $this->createRequest()->post(self::URL, ['headers' => ['Authorization' => 'Basic dXNlcjpwYXNz'], 'body' => json_encode($ping)]);
        $this->assertEquals(200, $response->getStatusCode());

        // a revoked key is refused too
        $apiKey = $this->getService(\App\Domain\Service\ApiKey\ApiKeyService::class)->create(['title' => 'old', 'is_full_access' => true]);
        $token = $this->getService(\App\Domain\Service\ApiKey\ApiKeyService::class)->issueToken($apiKey);
        $this->getService(\App\Domain\Service\ApiKey\ApiKeyService::class)->update($apiKey, ['status' => \App\Domain\Casts\ApiKey\Status::REVOKE]);
        $this->assertEquals(401, $this->rpc($token, $ping)->getStatusCode());
    }

    public function testLimitsAreValidatedNotCut(): void
    {
        $token = $this->createApiKeyToken();

        foreach ([['limit' => 101], ['limit' => 0], ['limit' => -5], ['limit' => 'many'], ['limit' => 2.5], ['offset' => -1], ['offset' => 'x']] as $args) {
            [$error, $text] = $this->tool($token, 'page_list', $args);
            $this->assertTrue($error, json_encode($args));
            $this->assertStringContainsString(array_key_first($args), $text);
        }

        [$error, $result] = $this->tool($token, 'page_list', ['limit' => 100, 'offset' => 0]);
        $this->assertFalse($error);
        $this->assertEquals(100, $result['limit']);
        // numbers as strings are fine, empty means the default
        [, $result] = $this->tool($token, 'page_list', ['limit' => '5', 'offset' => null]);
        $this->assertEquals(5, $result['limit']);
        [, $result] = $this->tool($token, 'page_list', ['limit' => '']);
        $this->assertEquals(20, $result['limit']);
    }

    public function testDryRunShowsTheResultAndSavesNothing(): void
    {
        $category = $this->getService(CatalogCategoryService::class)->create(['title' => 'Телефоны', 'address' => 'phones']);
        $product = $this->makeProduct('Смартфон', (string) $category->uuid, ['price' => 100]);
        $products = $this->getService(CatalogProductService::class);
        $price = fn () => (float) $products->read(['uuid' => (string) $product->uuid])->price;

        $token = $this->createApiKeyToken();

        // write tools announce dry_run, read ones and the worker based one do not
        $definitions = array_column($this->call($token, 'tools/list')['result']['tools'], null, 'name');
        $this->assertArrayHasKey('dry_run', $definitions['catalog_product_update']['inputSchema']['properties']);
        $this->assertArrayHasKey('dry_run', $definitions['page_update']['inputSchema']['properties']);
        $this->assertArrayNotHasKey('dry_run', $definitions['catalog_product_search']['inputSchema']['properties']);
        $this->assertArrayNotHasKey('dry_run', $definitions['task_retry']['inputSchema']['properties']);

        [$error, $result] = $this->tool($token, 'catalog_product_update', ['uuid' => (string) $product->uuid, 'price' => 999, 'title' => 'Другое', 'dry_run' => true]);
        $this->assertFalse($error, json_encode($result));
        $this->assertTrue($result['dry_run']);
        $this->assertEquals(999, $result['price']);
        $this->assertEquals('Другое', $result['title']);
        $this->assertEquals(100.0, $price());
        $this->assertEquals('Смартфон', $products->read(['uuid' => (string) $product->uuid])->title);

        // the same call for real
        [, $result] = $this->tool($token, 'catalog_product_update', ['uuid' => (string) $product->uuid, 'price' => 999]);
        $this->assertArrayNotHasKey('dry_run', $result);
        $this->assertEquals(999.0, $price());

        // a failure inside is still a failure and still saves nothing
        [$error] = $this->tool($token, 'catalog_product_update', ['uuid' => (string) $product->uuid, 'price' => -1, 'dry_run' => true]);
        $this->assertTrue($error);

        // creating: the new record does not stay
        $pubCategory = $this->getService(PublicationCategoryService::class)->create(['title' => 'Новости', 'address' => 'news']);
        [, $result] = $this->tool($token, 'publication_create', ['title' => 'Пробная', 'address' => 'news/probnaya', 'category_uuid' => (string) $pubCategory->uuid, 'dry_run' => true]);
        $this->assertEquals('draft', $result['state']);
        [, $list] = $this->tool($token, 'publication_list');
        $this->assertEquals(0, $list['total']);

        // bulk prices applied "for show"
        $this->makeProduct('Чехол', (string) $category->uuid, ['price' => 50]);
        [, $result] = $this->tool($token, 'catalog_price_bulk_update', ['category_uuid' => (string) $category->uuid, 'percent' => 10, 'apply' => true, 'expected_count' => 2, 'dry_run' => true]);
        $this->assertTrue($result['dry_run']);
        $this->assertEquals(999.0, $price());

        // not for tools that only read, not for the ones with effects outside of the database
        [$error, $text] = $this->tool($token, 'catalog_product_search', ['dry_run' => true]);
        $this->assertTrue($error);
        $this->assertStringContainsString('only reads', $text);
        [$error, $text] = $this->tool($token, 'task_retry', ['uuid' => \Ramsey\Uuid\Uuid::uuid4()->toString(), 'dry_run' => true]);
        $this->assertTrue($error);
        $this->assertStringContainsString('does not support', $text);
    }

    public function testDryRunDoesNotNotifySubscribers(): void
    {
        $pubSub = $this->getService(\App\Application\PubSub::class);
        $calls = 0;
        $pubSub->subscribe('mcp:test:event', function () use (&$calls): void {
            ++$calls;
        });

        $pubSub->publish('mcp:test:event');
        $this->assertEquals(1, $calls);

        $result = $pubSub->silently(function () use ($pubSub, &$calls) {
            $pubSub->publish('mcp:test:event');

            return 'done';
        });
        $this->assertEquals('done', $result);
        $this->assertEquals(1, $calls, 'muted');

        $pubSub->publish('mcp:test:event');
        $this->assertEquals(2, $calls, 'the events are back after the callback, also when it throws');

        try {
            $pubSub->silently(fn () => throw new \RuntimeException('x'));
        } catch (\RuntimeException) {
        }
        $pubSub->publish('mcp:test:event');
        $this->assertEquals(3, $calls);
    }

    public function testWritesAreJournaled(): void
    {
        $category = $this->getService(CatalogCategoryService::class)->create(['title' => 'Телефоны', 'address' => 'phones']);
        $product = $this->makeProduct('Смартфон', (string) $category->uuid);

        $apiKey = $this->getService(\App\Domain\Service\ApiKey\ApiKeyService::class)->create(['title' => 'журнал-тест', 'is_full_access' => true]);
        $token = $this->getService(\App\Domain\Service\ApiKey\ApiKeyService::class)->issueToken($apiKey);

        // the log lives between the test runs: every call carries a mark of this one
        $marker = 'Журнал ' . uniqid();
        $this->tool($token, 'catalog_product_update', ['uuid' => (string) $product->uuid, 'title' => $marker . ' ok']);
        $this->tool($token, 'catalog_product_update', ['uuid' => (string) $product->uuid, 'title' => $marker . ' bad', 'price' => -1]);
        $this->tool($token, 'catalog_product_update', ['uuid' => (string) $product->uuid, 'title' => $marker . ' dry', 'description' => str_repeat('я', 5000), 'dry_run' => true]);
        $this->tool($token, 'catalog_product_search', ['query' => $marker]);

        $log = '';
        foreach (glob(LOG_DIR . '/app*.log') as $file) {
            $log .= (string) file_get_contents($file);
        }

        $lines = array_values(array_filter(explode("\n", $log), fn ($line) => str_contains($line, 'MCP write') && str_contains($line, 'журнал-тест')));
        $this->assertGreaterThanOrEqual(3, count($lines));

        $mine = array_values(array_filter($lines, fn ($line) => str_contains($line, $marker)));
        $this->assertCount(3, $mine, 'a success, a refusal and a dry run');
        $this->assertStringContainsString('"tool":"catalog_product_update"', $mine[0]);
        $this->assertStringContainsString((string) $apiKey->uuid, $mine[0]);
        $this->assertStringContainsString('"ok":true', implode("\n", $mine));
        $this->assertStringContainsString('"ok":false', implode("\n", $mine));
        $this->assertStringContainsString('"dry_run":true', implode("\n", $mine));
        // a long text does not flood the log
        $this->assertStringNotContainsString(str_repeat('я', 300), $log);
        // reads are not journaled
        $this->assertEmpty(array_filter(explode("\n", $log), fn ($line) => str_contains($line, 'MCP write') && str_contains($line, '"tool":"catalog_product_search"')));
    }

    // the last batch: reports, creating, search, logs, health

    public function testNewToolsFollowKeyScopes(): void
    {
        $orders = $this->tools($this->createApiKeyToken(['read' => ['catalog/order']], false));
        $this->assertContains('catalog_sales_report', $orders);
        $this->assertContains('catalog_top_products', $orders);
        $this->assertNotContains('catalog_order_update', $orders);
        $this->assertNotContains('catalog_stock_report', $orders);

        $writer = $this->tools($this->createApiKeyToken(['write' => ['catalog/order', 'catalog/product', 'catalog/category']], false));
        foreach (['catalog_order_update', 'catalog_product_create', 'catalog_category_create'] as $name) {
            $this->assertContains($name, $writer);
        }

        $this->assertContains('publication_category_list', $this->tools($this->createApiKeyToken(['read' => ['publication/category']], false)));
        $this->assertNotContains('publication_category_list', $orders);

        // none of the new ones is open without a key
        $this->getService(ParameterService::class)->update($this->getService(ParameterService::class)->read(['name' => 'entity_access']), ['value' => 'all']);
        $this->assertCount(6, $this->tools(null));
    }

    // settings and the files of the design

    public function testFieldToolsFollowKeyScopes(): void
    {
        $reader = $this->tools($this->createApiKeyToken(['read' => ['catalog/category', 'catalog/attributes']], false));
        $this->assertContains('catalog_category_get', $reader);
        $this->assertNotContains('catalog_attribute_create', $reader);
        $this->assertNotContains('catalog_attribute_update', $reader);

        $writer = $this->tools($this->createApiKeyToken(['write' => ['catalog/attributes']], false));
        $this->assertContains('catalog_attribute_create', $writer);
        $this->assertContains('catalog_attribute_update', $writer);
        $this->assertNotContains('catalog_category_get', $writer);

        $definitions = array_column($this->call($this->createApiKeyToken(), 'tools/list')['result']['tools'], null, 'name');
        foreach (['type', 'tax_included', 'priceFirst', 'tags', 'dimension', 'attributes', 'relations', 'external_id', 'export', 'order', 'date'] as $field) {
            $this->assertArrayHasKey($field, $definitions['catalog_product_update']['inputSchema']['properties'], "product update: {$field}");
            $this->assertArrayHasKey($field, $definitions['catalog_product_create']['inputSchema']['properties'], "product create: {$field}");
        }
        foreach (['pagination', 'is_allow_nested', 'sort', 'template', 'order', 'specifics', 'external_id', 'export', 'system', 'attributes_add', 'attributes_remove', 'parent_uuid'] as $field) {
            $this->assertArrayHasKey($field, $definitions['catalog_category_update']['inputSchema']['properties'], "category update: {$field}");
        }
    }

    // files of the records

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
}
