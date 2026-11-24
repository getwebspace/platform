<?php declare(strict_types=1);

namespace tests\Application;

use App\Domain\Service\Catalog\CategoryService;
use App\Domain\Service\Catalog\ProductService;
use App\Domain\Service\GuestBook\GuestBookService;
use App\Domain\Service\Page\PageService;
use App\Domain\Service\Parameter\ParameterService;
use App\Domain\Service\Publication\CategoryService as PublicationCategoryService;
use App\Domain\Service\Publication\PublicationService;
use App\Domain\Service\Reference\ReferenceService;
use App\Domain\Service\User\GroupService;
use PHPUnit\Framework\Attributes\DataProvider;
use tests\Concerns\MakesAuthRequests;
use tests\TestCase;

/**
 * What a theme author can call from a template, rendered the way the site renders it
 *
 * @internal
 *
 * #[CoversNothing]
 */
class TwigExtensionTest extends TestCase
{
    use MakesAuthRequests;

    private array $server = [];

    public function setUp(): void
    {
        parent::setUp();

        $this->server = $_SERVER;
        $_SERVER['REQUEST_URI'] = '/blog/news/3?sort=date&q=a+b';
        $_SERVER['HTTP_HOST'] = 'shop.example';
        $_SERVER['REQUEST_SCHEME'] = 'https';
        unset($_SERVER['HTTP_X_FORWARDED_PROTO'], $_SERVER['HTTP_X_FORWARDED_HOST']);
    }

    public function tearDown(): void
    {
        $_SERVER = $this->server;

        parent::tearDown();
    }

    private function render(string $template, array $data = []): string
    {
        return trim($this->getService('view')->fetchFromString($template, $data));
    }

    private function setParameter(string $name, string $value): void
    {
        $this->getService(ParameterService::class)->create(['name' => $name, 'value' => $value]);

        // parameters are read once per request, and here a test is one request
        $this->getService(\Illuminate\Cache\ArrayStore::class)->flush();
    }

    // ------------------------------------------------------------ small helpers

    public function testDatesFollowTheConfiguredFormat(): void
    {
        $this->assertSame('02.01.2026', $this->render("{{ df('2026-01-02 03:04:05', 'd.m.Y') }}"));
        $this->assertSame('2-01-2026, 03:04', $this->render("{{ '2026-01-02 03:04:05'|df }}"), 'the default format');

        $this->setParameter('common_date_format', 'Y/m/d');
        $this->assertSame('2026/01/02', $this->render("{{ '2026-01-02 03:04:05'|df }}"));
        $this->assertSame('YYYY/MM/DD', $this->render('{{ dfm() }}'), 'the same format for the javascript date picker');
    }

    public function testCountWorksOnAnythingCountable(): void
    {
        $this->assertSame('3', $this->render('{{ count([1, 2, 3]) }}'));
        $this->assertSame('2', $this->render('{{ collect({a: 1, b: 2})|count }}'));
        $this->assertSame('', $this->render("{{ count('text') }}"), 'false for what cannot be counted');
    }

    public function testRegexReplace(): void
    {
        $this->assertSame('a-b-c', $this->render("{{ 'a  b   c'|preg_replace('/\\\\s+/', '-') }}"));
    }

    public function testBase64AndJson(): void
    {
        $this->assertSame('aGVsbG8=', $this->render("{{ base64_encode('hello') }}"));
        $this->assertSame('hello', $this->render("{{ base64_decode('aGVsbG8=') }}"));
        $this->assertSame('{"name":"Привіт","n":1}', html_entity_decode($this->render("{{ json_encode({name: 'Привіт', n: 1}) }}")));
        $this->assertSame('x', $this->render("{{ json_decode('{\"a\": {\"b\": \"x\"}}').a.b }}"));
    }

    #[DataProvider('sizeProvider')]
    public function testSizesAreReadable(int $bytes, string $expected): void
    {
        $this->assertSame($expected, $this->render('{{ convert_size(' . $bytes . ') }}'));
    }

    public static function sizeProvider(): array
    {
        return [
            'bytes' => [500, '500 b'],
            'kilobytes' => [2048, '2 kb'],
            'megabytes' => [5 * 1024 * 1024, '5 mb'],
            'with a fraction' => [1536, '1.5 kb'],
            'one byte' => [1, '1 b'],
        ];
    }

    public function testZeroBytesDoNotBreakThePage(): void
    {
        $this->assertSame('0 b', $this->render('{{ convert_size(0) }}'));
    }

    public function testInstanceofTest(): void
    {
        $this->assertSame('yes', $this->render("{% if value is instanceof('ArrayObject') %}yes{% else %}no{% endif %}", ['value' => new \ArrayObject()]));
        $this->assertSame('no', $this->render("{% if value is instanceof('ArrayObject') %}yes{% else %}no{% endif %}", ['value' => 'text']));
    }

    public function testConstantsOfTheApplicationCanBeRead(): void
    {
        $this->assertSame('work', $this->render("{{ constant('App/Domain/Casts/User/Status::WORK') }}"));
        $this->assertStringContainsString('work', $this->render("{{ constant('App/Domain/Casts/User/Status::LIST')|join(',') }}"), 'a list is keyed by its own values');
        $this->assertSame('fallback', $this->render("{{ constant('No/Such/Thing::X', 'fallback') }}"), 'an unknown one gives the fallback');
    }

    public function testTranslationsPickTheCurrentLanguage(): void
    {
        $this->assertSame('', $this->render("{{ {'ru-RU': 'Привет'}|trans }}"), 'no text for the language');
        $this->assertSame('plain', $this->render("{{ 'plain'|trans }}"));
        $this->assertSame('SOME_UNKNOWN_KEY', $this->render("{{ _('SOME_UNKNOWN_KEY') }}"), 'a missing translation shows its key');
    }

    public function testQrCodeIsAnInlineImage(): void
    {
        $html = $this->render("{{ qr_code('https://shop.example', 128) }}");

        $this->assertStringStartsWith('<img src="data:image/svg+xml;base64,', $html);
        $this->assertStringContainsString('height="128" width="128"', $html);
        $this->assertStringContainsString('<svg', base64_decode(explode('"', $html)[1] ? substr(explode(',', explode('"', $html)[1])[1], 0) : ''));
    }

    public function testCacheKeepsAValueForTheRequest(): void
    {
        $this->assertSame('miss', $this->render("{{ cache_get('k', 'miss') }}"));

        $this->render("{% do cache_put('k', 'stored', 60) %}");
        $this->assertSame('stored', $this->render("{{ cache_get('k', 'miss') }}"));
        $this->assertSame('miss', $this->render("{{ cache_get('other', 'miss') }}"));
    }

    public function testFileCacheSurvivesTheRequest(): void
    {
        $key = 'twig-test-' . uniqid();

        $this->render("{% do cache_put('{$key}', 'on disk', 60, 'file') %}");

        $this->assertSame('on disk', $this->render("{{ cache_get('{$key}', 'miss', 'file') }}"));
        $this->assertSame('miss', $this->render("{{ cache_get('{$key}', 'miss') }}"), 'the memory cache is a different one');
    }

    public function testHtmlIsMinifiedOutsideDebug(): void
    {
        $html = $this->render('{% htmlcompress %}<div>   <p>  a  </p>   </div>{% endhtmlcompress %}');

        $this->assertStringContainsString('<p>', $html);
        $this->assertLessThanOrEqual(strlen('<div>   <p>  a  </p>   </div>'), strlen($html));
    }

    // ------------------------------------------------------------- the address

    public function testCurrentAddressPieces(): void
    {
        $this->assertSame('https://shop.example/blog/news/3?sort=date&q=a+b', $this->render('{{ current_url()|raw }}'));
        $this->assertSame('/blog/news/3', $this->render("{{ parse_url().path }}"));
        $this->assertSame('3', $this->render('{{ current_page_number() }}'));
        $this->assertSame('/blog/news', $this->render('{{ non_page_path() }}'));
        $this->assertSame('yes', $this->render("{% if is_current_page_number(3) %}yes{% endif %}"));
        $this->assertSame('', $this->render("{% if is_current_page_number(2) %}yes{% endif %}"));
    }

    public function testPageNumberIsZeroWithoutOne(): void
    {
        $_SERVER['REQUEST_URI'] = '/blog/news';

        $this->assertSame('0', $this->render('{{ current_page_number() }}'));
        $this->assertSame('/blog/news', $this->render('{{ non_page_path() }}'));
    }

    public function testQueryCanBeExtended(): void
    {
        $this->assertSame('?sort=date&q=a+b', html_entity_decode($this->render('{{ current_query() }}')));
        $this->assertSame('?sort=price&q=a+b', html_entity_decode($this->render("{{ current_query('sort', 'price') }}")));
        $this->assertSame('?sort=date&q=a+b&page=2', html_entity_decode($this->render("{{ current_query('page', 2) }}")));

        $_SERVER['REQUEST_URI'] = '/plain';
        $this->assertSame('', $this->render('{{ current_query() }}'));
    }

    public function testQueryStringsAreBuilt(): void
    {
        $this->assertSame('/search?q=a b&page=2', html_entity_decode($this->render("{{ build_query('/search', {q: 'a b', page: 2}) }}")));
        $this->assertSame('?x=1', html_entity_decode($this->render('{{ build_query({x: 1}) }}')), 'the address can be left out');
    }

    public function testTheSiteAddressComesFromTheSettingsWhenSet(): void
    {
        $this->setParameter('common_homepage', 'https://www.mysite.example/');

        $this->assertSame('https://www.mysite.example', $this->render('{{ base_url() }}'), 'no slash at the end');
        $this->assertSame('https://www.mysite.example/blog/news/3?sort=date&q=a+b', $this->render('{{ current_url()|raw }}'));
    }

    public function testAProxyDecidesTheAddressWhenItSendsHeaders(): void
    {
        $_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';
        $_SERVER['HTTP_X_FORWARDED_HOST'] = 'public.example';

        $this->assertSame('https://public.example/blog/news/3?sort=date&q=a+b', $this->render('{{ current_url()|raw }}'));
    }

    // ---------------------------------------------------------------- the data

    public function testParametersAreReadWithADefault(): void
    {
        $this->setParameter('shop_name', 'Corner shop');

        $this->assertSame('Corner shop', $this->render("{{ parameter('shop_name') }}"));
        $this->assertSame('none', $this->render("{{ parameter('missing', 'none') }}"));
    }

    public function testPagesPublicationsAndTheGuestbookCanBeListed(): void
    {
        $pages = $this->getService(PageService::class);
        $pages->create(['title' => 'About', 'address' => 'about', 'content' => 'x']);
        $pages->create(['title' => 'Delivery', 'address' => 'delivery', 'content' => 'x']);

        $this->assertSame('About,Delivery', $this->render("{{ page({}, {title: 'asc'})|map(p => p.title)|join(',') }}"));
        $this->assertSame('Delivery', $this->render("{{ page({}, {title: 'asc'}, 1, 1)|map(p => p.title)|join(',') }}"), 'limit and offset');

        $category = $this->getService(PublicationCategoryService::class)->create(['title' => 'Blog', 'address' => 'blog']);
        $this->getService(PublicationService::class)->create(['title' => 'First post', 'address' => 'first', 'category_uuid' => $category->uuid, 'content' => ['short' => 'a', 'full' => 'b']]);

        $this->assertSame('Blog', $this->render("{{ publication_category()|map(c => c.title)|join(',') }}"));
        $this->assertSame('First post', $this->render("{{ publication({}, {})|map(p => p.title)|join(',') }}"));

        $entry = $this->getService(GuestBookService::class)->create(['name' => 'Ann', 'email' => 'ann.smith@example.com', 'message' => 'Nice']);
        $this->assertSame('', $this->render("{{ guestbook()|map(m => m.name)|join(',') }}"), 'not moderated yet');
        $this->getService(GuestBookService::class)->update($entry, ['status' => \App\Domain\Casts\GuestBook\Status::WORK]);
        $shown = $this->render("{{ guestbook()|map(m => m.email)|join(',') }}");
        $this->assertNotSame('', $shown);
        $this->assertStringNotContainsString('ann.smith@example.com', $shown, 'the address is masked');
    }

    public function testReferencesAreListedByTypeOrAsAMapOfTypes(): void
    {
        $this->getService(ReferenceService::class)->create(['title' => 'USD', 'type' => 'currency', 'value' => ['code' => 'USD'], 'order' => 1]);
        $this->getService(ReferenceService::class)->create(['title' => 'EUR', 'type' => 'currency', 'value' => ['code' => 'EUR'], 'order' => 2]);

        $this->assertSame('USD,EUR', $this->render("{{ reference('currency')|map(r => r.title)|join(',') }}"));
        $this->assertSame('USD,EUR', $this->render("{{ reference('currency', true)|keys|join(',') }}"));
        $this->assertStringContainsString('currency', $this->render("{{ reference()|join(',') }}"), 'no type: the list of types');
        $this->assertStringContainsString('currency', $this->render("{{ reference('not-a-type')|join(',') }}"));
    }

    public function testTheCategoryTreeCountsProductsAndSkipsHiddenBranches(): void
    {
        $categories = $this->getService(CategoryService::class);
        $products = $this->getService(ProductService::class);
        $root = $categories->create(['title' => 'Root', 'address' => 'root', 'is_allow_nested' => true, 'order' => 1]);
        $leaf = $categories->create(['title' => 'Leaf', 'address' => 'root/leaf', 'parent_uuid' => $root->uuid]);
        $closed = $categories->create(['title' => 'Closed', 'address' => 'closed', 'is_allow_nested' => false, 'order' => 2]);
        $inside = $categories->create(['title' => 'Inside', 'address' => 'closed/inside', 'parent_uuid' => $closed->uuid]);
        $secret = $categories->create(['title' => 'Secret', 'address' => 'secret', 'is_hidden' => true, 'order' => 3]);
        $under = $categories->create(['title' => 'Under', 'address' => 'secret/under', 'parent_uuid' => $secret->uuid]);

        foreach ([[$root, 'a'], [$leaf, 'b'], [$leaf, 'c'], [$inside, 'd'], [$under, 'e']] as [$category, $name]) {
            $products->create(['title' => $name, 'address' => $name, 'category_uuid' => $category->uuid]);
        }
        $products->create(['title' => 'gone', 'address' => 'gone', 'category_uuid' => $leaf->uuid, 'status' => \App\Domain\Casts\Catalog\Status::DELETE]);

        $this->assertSame(
            'Root:3(1)[Leaf:2(2)],Closed:0(0)[Inside:1(1)]',
            $this->render("{% macro n(list) %}{% for c in list %}{{ c.title }}:{{ c.count }}({{ c.own_count }}){% if c.children %}[{{ _self.n(c.children) }}]{% endif %}{% if not loop.last %},{% endif %}{% endfor %}{% endmacro %}{{ _self.n(catalog_category_tree()) }}"),
            'the count is what the page lists: nested products only where the category allows them'
        );
        $this->assertSame('Leaf', $this->render("{{ catalog_category_tree('root')|map(c => c.title)|join(',') }}"));
        $this->assertSame('Leaf', $this->render("{{ catalog_category_tree('" . $root->uuid . "')|map(c => c.title)|join(',') }}"));
        $this->assertSame('', $this->render("{{ catalog_category_tree('secret')|map(c => c.title)|join(',') }}"));
        $this->assertSame('', $this->render("{{ catalog_category_tree('nope')|map(c => c.title)|join(',') }}"));
    }

    public function testBoughtTogetherIsSafeToCallFromATemplate(): void
    {
        $this->assertSame('0', $this->render('{{ catalog_product_bought_together(null)|length }}'), 'no product on the page: nothing to show');
        $this->assertSame('0', $this->render("{{ catalog_product_bought_together('00000000-0000-0000-0000-000000000000')|length }}"));
    }

    public function testTheCatalogCanBeListedAndCounted(): void
    {
        $categories = $this->getService(CategoryService::class);
        $shown = $categories->create(['title' => 'Phones', 'address' => 'phones']);
        $categories->create(['title' => 'Secret', 'address' => 'secret', 'is_hidden' => true]);

        $this->assertSame('Phones', $this->render("{{ catalog_category()|map(c => c.title)|join(',') }}"), 'hidden categories stay out');

        $products = $this->getService(ProductService::class);
        foreach ([['One', 10], ['Two', 20], ['Three', 30]] as [$title, $price]) {
            $products->create([
                'title' => $title,
                'address' => strtolower($title),
                'category_uuid' => $shown->uuid,
                'type' => \App\Domain\Casts\Catalog\Product\Type::PRODUCT,
                'price' => $price,
                'order' => $price,
            ]);
        }

        $this->assertSame('3', $this->render('{{ catalog_product_count() }}'));
        $this->assertSame('One,Two,Three', $this->render('{{ catalog_product()|map(p => p.title)|join(",") }}'));
        $this->assertSame('Two', $this->render('{{ catalog_product({}, {order: "asc"}, 1, 1)|map(p => p.title)|join(",") }}'));
    }

    public function testUsersAndGroupsCanBeListed(): void
    {
        $this->getService(GroupService::class)->create(['title' => 'Staff', 'access' => []]);
        $this->makeUser(['username' => 'zed', 'email' => 'zed@example.com']);
        $this->makeUser(['username' => 'amy', 'email' => 'amy@example.com']);
        $this->makeUser(['username' => 'gone', 'email' => 'gone@example.com', 'status' => \App\Domain\Casts\User\Status::BLOCK]);

        $this->assertSame('amy,zed', $this->render("{{ user()|map(u => u.username)|join(',') }}"), 'only working accounts, by e-mail');
        $this->assertSame('Staff', $this->render("{{ user_group()|map(g => g.title)|join(',') }}"));
    }

    public function testThePriceTypeDependsOnWhoIsLookingAtTheShop(): void
    {
        $this->setParameter('catalog_price_type', 'price_wholesale');

        $this->assertSame('price', $this->render('{{ catalog_product_price_type() }}'), 'a guest sees the retail price');
        $this->assertSame('price_wholesale', $this->render('{{ catalog_product_price_type() }}', ['user' => $this->makeUser()]), 'a logged in user the configured one');
    }

    public function testPopularProductsAreThoseOrderedMost(): void
    {
        $category = $this->getService(CategoryService::class)->create(['title' => 'Phones', 'address' => 'phones']);
        $products = $this->getService(ProductService::class);
        $make = fn (string $title) => $products->create([
            'title' => $title, 'address' => strtolower($title), 'category_uuid' => $category->uuid,
            'type' => \App\Domain\Casts\Catalog\Product\Type::PRODUCT, 'price' => 10,
        ]);
        $popular = $make('Popular');
        $rare = $make('Rare');
        $make('Never');

        $orders = $this->getService(\App\Domain\Service\Catalog\OrderService::class);
        foreach ([[$popular, $rare], [$popular], [$popular]] as $items) {
            $orders->create(['email' => 'buyer@example.com', 'phone' => '+380501234567', 'delivery' => ['address' => 'x'], 'products' => array_combine(
                array_map(fn ($p) => $p->uuid, $items),
                array_map(fn () => ['count' => 1, 'price_type' => 'price'], $items)
            )]);
        }

        $this->assertSame('Popular,Rare', $this->render('{{ catalog_product_popular()|map(p => p.title)|join(",") }}'));
        $this->assertSame('Popular', $this->render('{{ catalog_product_popular(1)|map(p => p.title)|join(",") }}'));
    }

    public function testRecentlyViewedProductsRememberAtMostTheLimit(): void
    {
        $_SESSION = [];
        $ids = array_map(fn () => \Ramsey\Uuid\Uuid::uuid4()->toString(), range(1, 4));

        $this->assertSame('', $this->render('{{ catalog_product_view()|join(",") }}'));
        foreach ($ids as $id) {
            $this->render("{% do catalog_product_view('{$id}', 3) %}");
        }

        $this->assertSame(implode(',', array_slice($ids, 1)), $this->render('{{ catalog_product_view()|join(",") }}'), 'the oldest drops out');

        $this->render("{% do catalog_product_view('{$ids[3]}', 3) %}");
        $this->assertCount(3, $_SESSION['catalog_product_view'], 'seeing it again does not add it twice');
        $this->render("{% do catalog_product_view('not-a-uuid', 3) %}");
        $this->assertCount(3, $_SESSION['catalog_product_view']);
    }

    public function testDimensionalWeight(): void
    {
        $product = new \App\Domain\Models\CatalogProduct();
        $product->dimension = ['length' => 50, 'width' => 40, 'height' => 25, 'length_class' => 'cm', 'weight' => 0, 'weight_class' => 'kg'];

        $this->assertEqualsWithDelta(10.0, (float) $this->render('{{ catalog_product_dimensional_weight(product) }}', ['product' => $product]), 0.0001, '50*40*25 / 5000');

        $this->setParameter('catalog_dimensional_weight', '6000');
        $this->assertEqualsWithDelta(8.3333, (float) $this->render('{{ catalog_product_dimensional_weight(product) }}', ['product' => $product]), 0.0001);

        $empty = new \App\Domain\Models\CatalogProduct();
        $this->assertSame('0', $this->render('{{ catalog_product_dimensional_weight(product) }}', ['product' => $empty]), 'no size, no weight');
    }

    public function testOrdersCanBeListed(): void
    {
        $orders = $this->getService(\App\Domain\Service\Catalog\OrderService::class);
        $orders->create(['email' => 'one@example.com', 'phone' => '+380501234567', 'delivery' => ['address' => 'x']]);
        $orders->create(['email' => 'two@example.com', 'phone' => '+380501234567', 'delivery' => ['address' => 'x']]);

        $this->assertSame('2', $this->render('{{ catalog_order()|length }}'));
        $this->assertSame('1', $this->render('{{ catalog_order({}, {date: "desc"}, 1)|length }}'));
    }

    public function testTheHostFallsBackToLocalhostWithoutARequest(): void
    {
        unset($_SERVER['HTTP_HOST'], $_SERVER['REQUEST_SCHEME']);

        $_SERVER['REQUEST_URI'] = '/page';

        $this->assertSame('http://localhost/page', $this->render('{{ current_url() }}'));
    }

    public function testTheExtensionHasAName(): void
    {
        $this->assertSame('wse', (new \App\Application\TwigExtension($this->getService(\Psr\Container\ContainerInterface::class)))->getName());
    }

    public function testTheCurrentPathIsComparedAndCutWithoutItsQuery(): void
    {
        $_SERVER['REQUEST_URI'] = '/blog/news?sort=date';

        $this->assertSame('blog/news', $this->render('{{ current_path() }}'));
        $this->assertSame('blog/news?sort=date', $this->render('{{ current_path(true) }}'));
    }

    public function testAValueOfAConstantIsReadByItsKeyOrWhole(): void
    {
        $this->assertSame('fallback', $this->render("{{ constant('No/Such/Thing', 'fallback') }}"));
    }

    public function testFilesAndAttributesCanBeListedFromATemplate(): void
    {
        $this->getService(\App\Domain\Service\Catalog\AttributeService::class)->create(['title' => 'Color', 'address' => 'color', 'type' => 'string']);
        $path = CACHE_DIR . '/' . md5(uniqid('', true)) . '.txt';
        file_put_contents($path, 'words');
        $file = $this->getService(\App\Domain\Service\File\FileService::class)->createFromPath($path, 'notes.txt');

        try {
            $this->assertSame('1', $this->render('{{ file()|length }}'));
            $this->assertSame('notes', $this->render('{{ file({}, {date: "asc"}, 1).first().name }}'));
            $this->assertSame('1', $this->render('{{ catalog_attribute()|length }}'));
            $this->assertSame('Color', $this->render('{{ catalog_attribute({}, {title: "asc"}).first().title }}'));
        } finally {
            @unlink($file->internal_path());
            @unlink($path);
        }
    }

    public function testTheWeightOfAProductInTheContextNeedsNoArgument(): void
    {
        $product = new \App\Domain\Models\CatalogProduct();
        $product->dimension = ['length' => 50, 'width' => 40, 'height' => 25, 'length_class' => 'cm', 'weight' => 0, 'weight_class' => 'kg'];

        $this->assertEqualsWithDelta(10.0, (float) $this->render('{{ catalog_product_dimensional_weight() }}', ['product' => $product]), 0.0001);
    }
}
