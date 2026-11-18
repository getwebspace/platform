<?php declare(strict_types=1);

namespace tests\API;

use App\Domain\Casts\Catalog\Product\Type;
use App\Domain\Service\Catalog\AttributeService;
use App\Domain\Service\Catalog\CategoryService;
use App\Domain\Service\Catalog\ProductService;
use App\Domain\Service\Parameter\ParameterService;
use tests\Concerns\MakesAuthRequests;
use tests\TestCase;

/**
 * The public catalog: the front page of the shop, categories, filters, paging and a product
 *
 * @internal
 *
 * #[CoversNothing]
 */
class CatalogPagesTest extends TestCase
{
    use MakesAuthRequests;

    private function setParameter(string $name, string $value): void
    {
        $this->getService(ParameterService::class)->create(['name' => $name, 'value' => $value]);
    }

    private function category(string $title, array $extra = []): \App\Domain\Models\CatalogCategory
    {
        return $this->getService(CategoryService::class)->create($extra + ['title' => $title, 'address' => strtolower(str_replace(' ', '-', $title))]);
    }

    private function product(string $title, \App\Domain\Models\CatalogCategory $category, array $extra = []): \App\Domain\Models\CatalogProduct
    {
        return $this->getService(ProductService::class)->create($extra + [
            'title' => $title,
            'address' => $category->address . '/' . strtolower(str_replace(' ', '-', $title)),
            'category_uuid' => $category->uuid,
            'type' => Type::PRODUCT,
            'price' => 10,
        ]);
    }

    /**
     * @return string[] titles of the products a page shows
     */
    private function shown(string $path): array
    {
        $response = $this->http('GET', $path . (str_contains($path, '?') ? '&' : '?') . 'format=json');
        $this->assertEquals(200, $response->getStatusCode(), $path . ' ' . $this->why($response));
        return array_column($this->json($response)['data']['products']['filtered'] ?? [], 'title');
    }

    // ------------------------------------------------------------------ the shop

    public function testTheCatalogListsTheProductsOfVisibleCategoriesOnly(): void
    {
        $phones = $this->category('Phones');
        $secret = $this->category('Secret', ['is_hidden' => true]);
        $this->product('Visible phone', $phones);
        $this->product('Hidden one', $secret);
        $this->product('Archived phone', $phones, ['status' => \App\Domain\Casts\Catalog\Status::DELETE]);

        $this->assertSame(['Visible phone'], $this->shown('/catalog'));
    }

    public function testTheCountIsOfAllProductsNotJustThePage(): void
    {
        $this->setParameter('catalog_category_pagination', '2');
        $phones = $this->category('Phones');
        foreach (['A', 'B', 'C', 'D', 'E'] as $title) {
            $this->product($title, $phones);
        }

        $data = $this->json($this->http('GET', '/catalog?format=json'))['data'];

        $this->assertSame(5, $data['products']['count']);
        $this->assertSame(5, $data['pagination']['count']);
        $this->assertEquals(2, $data['pagination']['page']);
        $this->assertCount(2, $data['products']['filtered']);
    }

    public function testPagesAreReachedByTheNumberAtTheEndOfTheAddress(): void
    {
        $this->setParameter('catalog_category_pagination', '2');
        $this->setParameter('catalog_sort_by', 'title');
        $phones = $this->category('Phones');
        foreach (['A', 'B', 'C', 'D', 'E'] as $title) {
            $this->product($title, $phones);
        }

        $this->assertSame(['A', 'B'], $this->shown('/catalog'));
        $this->assertSame(['A', 'B'], $this->shown('/catalog/1'));
        $this->assertSame(['C', 'D'], $this->shown('/catalog/2'));
        $this->assertSame(['E'], $this->shown('/catalog/3'));
        $this->assertSame([], $this->shown('/catalog/4'));
    }

    public function testProductsCanBeOrderedByPriceOrTitle(): void
    {
        $phones = $this->category('Phones');
        $this->product('Beta', $phones, ['price' => 30]);
        $this->product('Alpha', $phones, ['price' => 20]);
        $this->product('Gamma', $phones, ['price' => 10]);

        $this->assertSame(['Alpha', 'Beta', 'Gamma'], $this->shown('/catalog?order=title'));
        $this->assertSame(['Gamma', 'Beta', 'Alpha'], $this->shown('/catalog?order=title&direction=desc'));
        $this->assertSame(['Gamma', 'Alpha', 'Beta'], $this->shown('/catalog?order=price'));
        $this->assertSame(['Beta', 'Alpha', 'Gamma'], $this->shown('/catalog?order=price&direction=DESC'));
    }

    public function testAnUnknownSortColumnOrDirectionIsNotUsed(): void
    {
        $phones = $this->category('Phones');
        $this->product('Alpha', $phones);

        // neither may reach the query
        $this->assertSame(['Alpha'], $this->shown('/catalog?order=' . urlencode('title; DROP TABLE catalog_product')));
        $this->assertSame(['Alpha'], $this->shown('/catalog?order=title&direction=' . urlencode('asc; DROP TABLE x')));
        $this->assertSame(['Alpha'], $this->shown('/catalog'));
    }

    public function testThePriceRangeNarrowsTheList(): void
    {
        $phones = $this->category('Phones');
        foreach ([5, 15, 25, 35] as $price) {
            $this->product("P{$price}", $phones, ['price' => $price]);
        }

        $this->assertSame(['P15', 'P25'], $this->shown('/catalog?order=price&price[min]=10&price[max]=30'));
        $this->assertSame(['P25', 'P35'], $this->shown('/catalog?order=price&price[min]=20'));
        $this->assertSame(['P5', 'P15'], $this->shown('/catalog?order=price&price[max]=20'));
    }

    public function testCountryAndManufacturerFilters(): void
    {
        $phones = $this->category('Phones');
        $this->product('Ukrainian', $phones, ['country' => 'UA', 'manufacturer' => 'Acme']);
        $this->product('Polish', $phones, ['country' => 'PL', 'manufacturer' => 'Acme']);
        $this->product('Other maker', $phones, ['country' => 'UA', 'manufacturer' => 'Zed']);

        $this->assertSame(['Ukrainian', 'Other maker'], $this->shown('/catalog?order=price&country=UA'));
        $this->assertSame(['Polish'], $this->shown('/catalog?country=PL&manufacturer=Acme'));
        $this->assertSame([], $this->shown('/catalog?country=PL&manufacturer=Zed'));
    }

    public function testFiltersAcceptHostileValuesWithoutHarm(): void
    {
        $phones = $this->category('Phones');
        $this->product('Alpha', $phones, ['country' => 'UA']);

        $this->assertSame([], $this->shown('/catalog?country=' . urlencode("' OR '1'='1")));
        $this->assertSame([], $this->shown('/catalog?manufacturer=' . urlencode('<script>alert(1)</script>')));
    }

    public function testProductsCanBeFilteredByAnAttribute(): void
    {
        $attribute = $this->getService(AttributeService::class)->create(['title' => 'Color', 'address' => 'color', 'type' => 'string', 'is_filter' => true]);
        $phones = $this->category('Phones');
        $red = $this->product('Red phone', $phones, ['attributes' => [$attribute->uuid => 'red']]);
        $this->product('Blue phone', $phones, ['attributes' => [$attribute->uuid => 'blue']]);
        $this->product('Plain phone', $phones);

        $this->assertSame(['Red phone'], $this->shown('/catalog?color=red'));
        $this->assertSame(['Red phone'], $this->shown('/catalog/phones?color=red'));
        $this->assertNotNull($red);
    }

    public function testAnAttributeThatIsNotAFilterIsIgnored(): void
    {
        $attribute = $this->getService(AttributeService::class)->create(['title' => 'Secret code', 'address' => 'code', 'type' => 'string', 'is_filter' => false]);
        $phones = $this->category('Phones');
        $this->product('One', $phones, ['attributes' => [$attribute->uuid => 'x']]);
        $this->product('Two', $phones);

        $this->assertCount(2, $this->shown('/catalog?code=x'));
    }

    // ---------------------------------------------------------------- categories

    public function testACategoryShowsItsOwnProductsAndTheNestedOnes(): void
    {
        $phones = $this->category('Phones', ['is_allow_nested' => true]);
        $smart = $this->category('Smart', ['parent_uuid' => $phones->uuid, 'address' => 'phones/smart']);
        $cables = $this->category('Cables');
        $this->product('Plain', $phones);
        $this->product('Clever', $smart);
        $this->product('Wire', $cables);

        $this->assertEqualsCanonicalizing(['Plain', 'Clever'], $this->shown('/catalog/phones'));
        $this->assertSame(['Clever'], $this->shown('/catalog/phones/smart'));
        $this->assertSame(['Wire'], $this->shown('/catalog/cables'));
    }

    public function testACategoryPagesByItsOwnPaginationSetting(): void
    {
        $phones = $this->category('Phones', ['pagination' => 2, 'sort' => ['by' => 'title', 'direction' => 'asc']]);
        foreach (['A', 'B', 'C'] as $title) {
            $this->product($title, $phones);
        }

        $this->assertSame(['A', 'B'], $this->shown('/catalog/phones'));
        $this->assertSame(['C'], $this->shown('/catalog/phones/2'));
        $this->assertSame(2, $this->json($this->http('GET', '/catalog/phones?format=json'))['data']['pagination']['page']);
    }

    public function testACategoryIsSortedBySettingUnlessTheVisitorChoosesAnOrder(): void
    {
        $phones = $this->category('Phones', ['sort' => ['by' => 'price', 'direction' => 'desc']]);
        $this->product('Cheap', $phones, ['price' => 1]);
        $this->product('Dear', $phones, ['price' => 100]);

        $this->assertSame(['Dear', 'Cheap'], $this->shown('/catalog/phones'));
        $this->assertSame(['Cheap', 'Dear'], $this->shown('/catalog/phones?order=price&direction=asc'));
    }

    public function testAHiddenOrUnknownCategoryIsNotFound(): void
    {
        $this->category('Secret', ['is_hidden' => true]);

        $this->assertEquals(404, $this->http('GET', '/catalog/secret?format=json')->getStatusCode());
        $this->assertEquals(404, $this->http('GET', '/catalog/no-such-category?format=json')->getStatusCode());
    }

    public function testACategoryThatWasRemovedIsNotFound(): void
    {
        $this->category('Old', ['status' => \App\Domain\Casts\Catalog\Status::DELETE]);

        $this->assertEquals(404, $this->http('GET', '/catalog/old?format=json')->getStatusCode());
    }

    // ------------------------------------------------------------------- product

    public function testAProductPageShowsTheProductAndItsCategory(): void
    {
        $phones = $this->category('Phones');
        $this->product('Nice phone', $phones, ['price' => 99]);

        $response = $this->http('GET', '/catalog/phones/nice-phone?format=json');

        $this->assertEquals(200, $response->getStatusCode(), $this->why($response));
        $data = $this->json($response)['data'];
        $this->assertSame('Nice phone', $data['product']['title']);
        $this->assertSame('Phones', $data['category']['title']);
        $this->assertSame('phones/nice-phone', $data['params']['address']);
    }

    public function testARemovedOrUnknownProductIsNotFound(): void
    {
        $phones = $this->category('Phones');
        $this->product('Gone', $phones, ['status' => \App\Domain\Casts\Catalog\Status::DELETE]);

        $this->assertEquals(404, $this->http('GET', '/catalog/phones/gone?format=json')->getStatusCode());
        $this->assertEquals(404, $this->http('GET', '/catalog/phones/never-existed?format=json')->getStatusCode());
    }

    public function testAProductOfAHiddenCategoryIsNotReachableByItsAddress(): void
    {
        $secret = $this->category('Secret', ['is_hidden' => true]);
        $this->product('Hidden product', $secret);

        $this->assertEquals(404, $this->http('GET', '/catalog/secret/hidden-product?format=json')->getStatusCode());
    }

    public function testTheCatalogCanBeSwitchedOff(): void
    {
        $this->setParameter('catalog_is_enabled', 'no');

        $this->assertEquals(307, $this->http('GET', '/catalog?format=json')->getStatusCode());
    }

    public function testTheCatalogIsCachedForGuestsOnly(): void
    {
        $this->category('Phones');

        $guest = $this->http('GET', '/catalog?format=json');

        $this->assertStringContainsString('max-age', $guest->getHeaderLine('Cache-Control'));
        $this->assertSame('', $guest->getHeaderLine('Set-Cookie'), 'a cached page never sets a cookie');
    }
}
