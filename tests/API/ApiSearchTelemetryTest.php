<?php declare(strict_types=1);

namespace tests\API;

use App\Domain\Service\Catalog\CategoryService;
use App\Domain\Service\Page\PageService;
use App\Domain\Service\Parameter\ParameterService;
use tests\API\Mcp\McpTestCase;
use tests\Concerns\MakesAuthRequests;

/**
 * /api/v1/search and /api/v1/telemetry
 *
 * @internal
 *
 * #[CoversNothing]
 */
class ApiSearchTelemetryTest extends McpTestCase
{
    use MakesAuthRequests;

    private function get(string $path, ?string $key = null): \Psr\Http\Message\ResponseInterface
    {
        return $this->http('GET', '/api/v1/' . $path, ['headers' => array_filter(['key' => $key])]);
    }

    private function buildIndex(): void
    {
        $container = $this->getService(\Psr\Container\ContainerInterface::class);
        $model = (new \App\Domain\Tasks\SearchIndexTask($container))->execute();
        (new \App\Domain\Tasks\SearchIndexTask($container, $model))->run();
        foreach (glob(CACHE_DIR . '/*.index') as $file) {
            chmod($file, 0o666); // the web server is another user
        }
    }

    // ---------------------------------------------------------------- telemetry

    public function testTelemetryTellsWhatTheSiteIsMadeOfAndCountsWhatItHolds(): void
    {
        $this->getService(PageService::class)->create(['title' => 'About', 'address' => 'about', 'content' => 'x']);
        $this->getService(CategoryService::class)->create(['title' => 'Goods', 'address' => 'goods']);

        $response = $this->get('telemetry');
        $body = $this->json($response);

        $this->assertSame(200, $response->getStatusCode(), $this->why($response));
        $this->assertSame(PHP_VERSION, $body['environment']['php']);
        $this->assertArrayHasKey('branch', $body['version']);
        $this->assertSame(1, $body['stats']['pages']);
        $this->assertSame(1, $body['stats']['catalog']['category']);
        $this->assertSame(0, $body['stats']['catalog']['product']);
        $this->assertIsArray($body['plugins']);
        foreach (['users', 'publications', 'catalog'] as $group) {
            $this->assertIsArray($body['stats'][$group], $group);
        }
    }

    public function testTelemetryCanBeSwitchedOff(): void
    {
        $this->getService(ParameterService::class)->create(['name' => 'telemetry_is_enabled', 'value' => 'no']);

        $response = $this->get('telemetry');

        $this->assertSame(423, $response->getStatusCode());
        $this->assertStringNotContainsString(PHP_VERSION, (string) $response->getBody());
    }

    // ------------------------------------------------------------------- search

    public function testSearchNeedsAKey(): void
    {
        $this->assertSame(401, $this->get('search?query=jacket')->getStatusCode());
    }

    public function testSearchCanBeSwitchedOff(): void
    {
        $this->getService(ParameterService::class)->create(['name' => 'search_is_enabled', 'value' => 'no']);

        $this->assertSame(423, $this->get('search?query=jacket', $this->createApiKeyToken())->getStatusCode());
    }

    public function testASearchQueryThatIsTooShortOrOfAnUnknownKindIsRefused(): void
    {
        $key = $this->createApiKeyToken();

        $short = $this->get('search?query=a', $key);
        $this->assertSame(400, $short->getStatusCode());
        $this->assertSame('Wrong query string', $this->json($short)['data']);

        $this->assertSame(400, $this->get('search', $key)->getStatusCode(), 'no query at all');

        $unknown = $this->get('search?query=jacket&type=users,page', $key);
        $this->assertSame(400, $unknown->getStatusCode());
        $this->assertStringContainsString('Unknown type: users', $this->json($unknown)['data']);
    }

    public function testWithoutAnIndexNothingIsFoundAndNothingBreaks(): void
    {
        $this->withSearchIndexAside(function (): void {
            $response = $this->get('search?query=jacket', $this->createApiKeyToken());

            $this->assertContains($response->getStatusCode(), [200, 503], $this->why($response));
            if ($response->getStatusCode() === 200) {
                $this->assertSame(0, $this->json($response)['data']['count']);
            }
        });
    }

    public function testSearchFindsWhatWasIndexedAndPagesThroughIt(): void
    {
        $this->withSearchIndexAside(function (): void {
            $pages = $this->getService(PageService::class);
            foreach (range(1, 3) as $i) {
                $pages->create(['title' => 'Jacket guide ' . $i, 'address' => 'jacket-' . $i, 'content' => 'About jackets ' . $i]);
            }
            $this->buildIndex();
            $key = $this->createApiKeyToken();

            $response = $this->get('search?q=jacket&type=page&limit=2', $key);
            $data = $this->json($response)['data'];

            $this->assertSame(200, $response->getStatusCode(), $this->why($response));
            $this->assertSame('jacket', $data['query']);
            $this->assertSame('page', $data['index_type']);
            $this->assertSame(3, $data['count']);
            $this->assertSame(2, $data['limit']);
            $this->assertSame(1, $data['page']);
            $this->assertCount(2, $data['result']);
            $this->assertArrayNotHasKey('model', $data['result'][0], 'the record itself is not sent, only what describes it');

            $second = $this->json($this->get('search?q=jacket&type=page&limit=2&page=2', $key))['data'];
            $this->assertCount(1, $second['result']);
            $this->assertSame(2, $second['page']);
            $this->assertEmpty(array_intersect(array_column($data['result'], 'title'), array_column($second['result'], 'title')), 'the second page is other records');

            $this->assertSame(100, $this->json($this->get('search?q=jacket&limit=5000', $key))['data']['limit'], 'the page size is capped');
            $this->assertSame(1, $this->json($this->get('search?q=jacket&limit=-4&page=0', $key))['data']['limit'], 'and never below one');
        });
    }

    public function testASearchQueryIsNotPassedOnAsMarkup(): void
    {
        $this->withSearchIndexAside(function (): void {
            $this->buildIndex();

            $data = $this->json($this->get('search?query=' . urlencode('<script>alert(1)</script>jacket'), $this->createApiKeyToken()))['data'];

            $this->assertStringNotContainsString('<script', json_encode($data));
        });
    }
}
