<?php declare(strict_types=1);

namespace tests\Domain\Service\Search;

use App\Domain\Service\Catalog\CategoryService as CatalogCategoryService;
use App\Domain\Service\Catalog\ProductService;
use App\Domain\Service\Page\PageService;
use App\Domain\Service\Publication\CategoryService as PublicationCategoryService;
use App\Domain\Service\Publication\PublicationService;
use App\Domain\Service\Search\SearchService;
use tests\TestCase;

/**
 * @internal
 *
 * #[CoversNothing]
 */
class SearchServiceTest extends TestCase
{
    private SearchService $service;

    /**
     * @var string[]
     */
    private array $aside = [];

    public function setUp(): void
    {
        parent::setUp();

        // the indexes of a developer's machine are put aside for the time of a test
        foreach (glob(CACHE_DIR . '/*.index*') ?: [] as $file) {
            rename($file, $file . '.test-aside');
            $this->aside[] = $file;
        }

        $this->service = $this->getService(SearchService::class);
    }

    public function tearDown(): void
    {
        foreach (glob(CACHE_DIR . '/*.index') ?: [] as $file) {
            @unlink($file);
        }
        foreach ($this->aside as $file) {
            @rename($file . '.test-aside', $file);
        }
        $this->aside = [];

        parent::tearDown();
    }

    private function fill(): void
    {
        $goods = $this->getService(CatalogCategoryService::class)->create(['title' => 'Outerwear', 'address' => 'outerwear']);
        $this->getService(CatalogCategoryService::class)->create(['title' => 'Jackets and coats', 'address' => 'jackets']);
        $this->getService(CatalogCategoryService::class)->create(['title' => 'Secret jackets', 'address' => 'secret', 'is_hidden' => true]);
        $hidden = $this->getService(CatalogCategoryService::class)->create(['title' => 'Closed shelf', 'address' => 'closed', 'is_hidden' => true]);

        $products = $this->getService(ProductService::class);
        $products->create(['title' => 'Waterproof jacket', 'address' => 'waterproof-jacket', 'category_uuid' => $goods->uuid, 'price' => 100, 'stock' => 1, 'description' => '<p>Warm and <b>dry</b></p>']);
        $products->create(['title' => 'Deleted jacket', 'address' => 'deleted-jacket', 'category_uuid' => $goods->uuid, 'price' => 100, 'stock' => 1, 'status' => 'delete']);
        $products->create(['title' => 'Jacket in hidden category', 'address' => 'hidden-cat-jacket', 'category_uuid' => $hidden->uuid, 'price' => 100, 'stock' => 1]);

        $this->getService(PageService::class)->create(['title' => 'About jackets', 'address' => 'about-jackets', 'content' => 'We sell jackets']);

        $news = $this->getService(PublicationCategoryService::class)->create(['title' => 'News of jackets', 'address' => 'news']);
        $this->getService(PublicationCategoryService::class)->create(['title' => 'Closed jackets news', 'address' => 'closed-news', 'is_public' => false]);
        $publications = $this->getService(PublicationService::class);
        $publications->create(['title' => 'Jacket sale', 'address' => 'news/sale', 'category_uuid' => $news->uuid, 'content' => ['short' => 'jackets', 'full' => 'jackets'], 'date' => '2026-01-01 10:00:00']);
        $publications->create(['title' => 'Jacket draft', 'address' => 'news/draft', 'category_uuid' => $news->uuid, 'content' => ['short' => 'jackets', 'full' => 'jackets'], 'date' => '2099-12-31 00:00:00']);

        $this->service->reindex();
    }

    private function titles(array $found): array
    {
        return array_column($found['items'], 'title');
    }

    public function testSearchesEverySectionAndSkipsWhatTheSiteDoesNotShow(): void
    {
        $this->fill();

        $found = $this->service->search('jacket', [], 50);
        $titles = $this->titles($found);

        foreach (['Waterproof jacket', 'About jackets', 'Jacket sale', 'Jackets and coats', 'News of jackets'] as $title) {
            $this->assertContains($title, $titles);
        }
        foreach (['Deleted jacket', 'Jacket in hidden category', 'Secret jackets', 'Closed jackets news', 'Jacket draft'] as $title) {
            $this->assertNotContains($title, $titles, $title . ' is not on the site');
        }
        $this->assertSame([], $found['missing']);
        $this->assertSame($found['total'], array_sum($found['counts']));
        $this->assertEqualsCanonicalizing(SearchService::types(), array_keys($found['counts']));
    }

    public function testFilterByTypesAndLinks(): void
    {
        $this->fill();

        $found = $this->service->search('jacket', ['page'], 10);
        $this->assertSame(['page'], array_unique(array_column($found['items'], 'type')));
        $this->assertSame('/about-jackets', $found['items'][0]['url']);

        $found = $this->service->search('jacket', ['catalog_product', 'catalog_category'], 10);
        $urls = array_column($found['items'], 'url', 'type');
        $this->assertSame('/catalog/waterproof-jacket', $urls['catalog_product']);
        $this->assertSame('/catalog/jackets', $urls['catalog_category']);

        $this->assertSame('/news/sale', array_column($this->service->search('sale', ['publication'])['items'], 'url')[0]);
    }

    public function testProductIsFoundByItsCategoryAndExcerptHasNoTags(): void
    {
        $this->fill();

        $found = $this->service->search('outerwear', ['catalog_product']);
        $this->assertSame(['Waterproof jacket'], $this->titles($found));

        $found = $this->service->search('warm', ['catalog_product']);
        $this->assertSame('Warm and dry', $found['items'][0]['excerpt']);
    }

    public function testPaginationAndShortQuery(): void
    {
        $this->fill();

        $all = $this->service->search('jacket', [], 50);
        $first = $this->service->search('jacket', [], 2, 0);
        $second = $this->service->search('jacket', [], 2, 2);

        $this->assertSame($all['total'], $first['total']);
        $this->assertCount(2, $first['items']);
        $this->assertSame(array_slice(array_column($all['items'], 'uuid'), 0, 4), array_merge(array_column($first['items'], 'uuid'), array_column($second['items'], 'uuid')));

        $this->assertSame(0, $this->service->search('j')['total']);
        $this->assertSame(0, $this->service->search('   ')['total']);
    }

    public function testStaleIndexDoesNotShowWhatBecameHidden(): void
    {
        $this->fill();

        $product = $this->getService(ProductService::class)->read(['address' => 'waterproof-jacket']);
        $this->getService(ProductService::class)->update($product, ['status' => 'delete']);

        // no reindex
        $this->assertNotContains('Waterproof jacket', $this->titles($this->service->search('waterproof', ['catalog_product'])));
    }

    public function testMissingIndexIsReportedAndReindexKeepsTheOldOneUntilReady(): void
    {
        $found = $this->service->search('jacket', ['page', 'catalog_product']);
        $this->assertSame(0, $found['total']);
        $this->assertEqualsCanonicalizing(['page', 'catalog_product'], $found['missing']);

        $this->fill();
        $this->assertSame([], glob(CACHE_DIR . '/*.tmp'), 'no temporary files are left');

        $counts = $this->service->reindex('page');
        $this->assertSame(['page' => 1], $counts);
        $this->assertSame(['About jackets'], $this->titles($this->service->search('jacket', ['page'])));
    }
}
