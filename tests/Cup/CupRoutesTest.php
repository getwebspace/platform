<?php declare(strict_types=1);

namespace tests\Cup;

use App\Domain\Casts\User\Status as UserStatus;
use App\Domain\Models\ApiKey;
use App\Domain\Models\CatalogCategory;
use App\Domain\Models\CatalogOrder;
use App\Domain\Models\CatalogProduct;
use App\Domain\Models\File;
use App\Domain\Models\Form;
use App\Domain\Models\GuestBook;
use App\Domain\Models\Page;
use App\Domain\Models\Publication;
use App\Domain\Models\Reference;
use App\Domain\Models\User;
use App\Domain\Models\UserGroup;
use App\Domain\Service\User\GroupService;

/**
 * Every address of the admin panel, as the administrator, as a stranger and as a user
 * with no right to it. This is the net under all the other panel tests
 *
 * @internal
 *
 * #[CoversNothing]
 */
class CupRoutesTest extends CupTestCase
{
    /**
     * Routes the smoke test does not call, and where each is tested instead. A new route has
     * to be listed here or be reachable by a plain GET, otherwise testEveryRouteIsAccountedFor() fails
     */
    private const NOT_BY_SMOKE = [
        // change data or send something, covered with their section
        'cup:api-key:delete' => 'ApiKeys',
        'cup:user:group:delete' => 'Users',
        'cup:user:subscriber:delete' => 'Users',
        'cup:user:delete' => 'Users',
        'cup:page:delete' => 'Content',
        'cup:publication:delete' => 'Content',
        'cup:publication:category:delete' => 'Content',
        'cup:publication:review:delete' => 'Reviews',
        'cup:form:delete' => 'Content',
        'cup:form:view:delete' => 'Forms',
        'cup:form:view:restore' => 'Forms',
        'cup:catalog:category:delete' => 'Catalog',
        'cup:catalog:product:delete' => 'Catalog',
        'cup:catalog:attribute:delete' => 'Catalog',
        'cup:catalog:order:delete' => 'Catalog',
        'cup:catalog:order:dispatch' => 'Catalog',
        'cup:catalog:review:delete' => 'Reviews',
        'cup:catalog:question:delete' => 'Reviews',
        'cup:guestbook:delete' => 'Content',
        'cup:file:delete' => 'Files',
        'cup:file:image:delete' => 'Files',
        'cup:reference:delete' => 'Content',
        'cup:task:run' => 'Tools',
        // POST only
        'cup:catalog:product:import' => 'Catalog',
    ];

    /**
     * Pages that answer something other than a page, with what they answer
     */
    private const ANSWERS = [
        'cup:user:subscriber:add' => [302, '/cup/user/subscriber'],
        'cup:catalog:order:export' => [301, null],
    ];

    /**
     * @return array<int, array{name: string, pattern: string, methods: string[]}>
     */
    private function panelRoutes(): array
    {
        return array_values(array_filter(
            $this->allRoutes(),
            fn (array $route): bool => str_starts_with($route['name'], 'cup:') && !in_array($route['name'], self::CUP_PUBLIC, true)
        ));
    }

    private function bySmoke(array $route): bool
    {
        return !isset(self::NOT_BY_SMOKE[$route['name']]) && in_array('GET', $route['methods'], true);
    }

    private function counts(): array
    {
        return [
            Page::count(), Publication::count(), GuestBook::count(), Form::count(), ApiKey::count(), UserGroup::count(),
            User::count(), CatalogCategory::count(), CatalogProduct::count(), CatalogOrder::count(), Reference::count(), File::count(),
        ];
    }

    // -------------------------------------------------------------- the route table

    public function testEveryRouteIsAccountedFor(): void
    {
        $names = array_column($this->panelRoutes(), 'name');

        foreach ($this->panelRoutes() as $route) {
            $this->assertTrue(
                $this->bySmoke($route) || isset(self::NOT_BY_SMOKE[$route['name']]),
                $route['name'] . ' is not opened by the smoke test and not listed as covered elsewhere'
            );
        }

        foreach (array_keys(self::NOT_BY_SMOKE) as $listed) {
            $this->assertContains($listed, $names, "{$listed} is listed here but there is no such route any more");
        }
    }

    public function testTheAdministratorsGroupHasEveryRoute(): void
    {
        $this->admin();

        $access = $this->getService(GroupService::class)->read(['title' => 'Administrators'])->access;

        $this->assertEqualsCanonicalizing($this->cupRouteNames(), $access);
        $this->assertNotContains('cup:login', $access, 'the public pages are not part of anybody\'s access');
        $this->assertGreaterThan(80, count($access));
    }

    // ----------------------------------------------------------------------- smoke

    public function testEveryPageOpensForTheAdministrator(): void
    {
        $this->fixtures();
        $opened = 0;

        foreach ($this->panelRoutes() as $route) {
            if (!$this->bySmoke($route)) {
                continue;
            }

            $path = $this->addressOf($route);
            $response = $this->cup('GET', $path);

            if (isset(self::ANSWERS[$route['name']])) {
                [$status, $location] = self::ANSWERS[$route['name']];
                $this->assertEquals($status, $response->getStatusCode(), $route['name'] . ' ' . $this->why($response));
                if ($location !== null) {
                    $this->assertSame($location, $response->getHeaderLine('Location'), $route['name']);
                }
            } else {
                $this->assertRenders($response, $route['name'] . ' ' . $path);
            }
            ++$opened;
        }

        $this->assertGreaterThan(55, $opened, 'the smoke test must not quietly shrink');
    }

    public function testAnHtmlPageIsAWholePage(): void
    {
        $this->fixtures();

        foreach (['/cup', '/cup/page', '/cup/user', '/cup/catalog/product', '/cup/parameters'] as $path) {
            $body = (string) $this->cup('GET', $path)->getBody();

            $this->assertStringContainsString('</html>', $body, $path);
            $this->assertStringContainsString('<title>', $body, $path);
        }
    }

    public function testEveryReferenceKindHasItsPages(): void
    {
        foreach (['currencies', 'deliveries', 'payments', 'countries', 'order-status', 'stock-status', 'tax-rates', 'length-classes',
            'weight-classes', 'address-format', 'store-locations', 'social-networks', 'manufacturer', 'documents'] as $kind) {
            $this->assertRenders($this->cup('GET', "/cup/reference/{$kind}"), $kind . ' list');
            $this->assertRenders($this->cup('GET', "/cup/reference/{$kind}/add"), $kind . ' add');
        }
    }

    public function testAnUnknownKindOfReferenceIsNotAPage(): void
    {
        foreach (['nothing', '..%2f..%2feditor', '%2e%2e'] as $kind) {
            $response = $this->cup('GET', "/cup/reference/{$kind}");

            $this->assertNotEquals(200, $response->getStatusCode(), $kind);
        }
    }

    // ---------------------------------------------------------------------- access

    public function testAStrangerIsSentToTheLoginPageAndChangesNothing(): void
    {
        $this->fixtures();
        $before = $this->counts();

        foreach ($this->panelRoutes() as $route) {
            $path = $this->addressOf($route);

            foreach (array_intersect($route['methods'], ['GET', 'POST']) ?: ['POST'] as $method) {
                $response = $this->http($method, $path);

                $this->assertEquals(307, $response->getStatusCode(), "{$method} {$path}");
                $this->assertSame('/cup/login?redirect=' . parse_url($path, PHP_URL_PATH), $response->getHeaderLine('Location'), "{$method} {$path}");
            }
        }

        $this->assertSame($before, $this->counts(), 'a stranger deleted or created something');
    }

    public function testAUserWithoutAGroupIsTurnedAwayFromEverythingAndChangesNothing(): void
    {
        $this->fixtures();
        $cookie = $this->cookie($this->loginAs($this->makeUser(['username' => 'plain', 'email' => 'plain@example.com']))['access_token']);
        $before = $this->counts();

        foreach ($this->panelRoutes() as $route) {
            $path = $this->addressOf($route);

            foreach (array_intersect($route['methods'], ['GET', 'POST']) ?: ['POST'] as $method) {
                $response = $this->http($method, $path, ['headers' => $cookie]);

                $this->assertEquals(307, $response->getStatusCode(), "{$method} {$path}");
                $this->assertSame('/cup/forbidden', $response->getHeaderLine('Location'), "{$method} {$path}");
            }
        }

        $this->assertSame($before, $this->counts());
    }

    public function testAGroupMayUseOnlyTheRoutesItWasGiven(): void
    {
        $this->fixtures();
        $group = $this->getService(GroupService::class)->create(['title' => 'Editors', 'access' => ['cup:main', 'cup:page:list', 'cup:page:edit']]);
        $user = $this->makeUser(['username' => 'editor', 'email' => 'editor@example.com', 'group_uuid' => $group->uuid]);
        $cookie = $this->cookie($this->loginAs($user)['access_token']);
        $before = $this->counts();

        foreach ($this->panelRoutes() as $route) {
            if (!in_array('GET', $route['methods'], true) && !in_array('POST', $route['methods'], true)) {
                continue;
            }

            $allowed = in_array($route['name'], ['cup:main', 'cup:page:list', 'cup:page:edit'], true);
            $path = $this->addressOf($route);
            $method = in_array('GET', $route['methods'], true) ? 'GET' : 'POST';

            $response = $this->http($method, $path, ['headers' => $cookie]);

            if ($allowed) {
                $this->assertContains($response->getStatusCode(), [200, 302], "{$route['name']} {$this->why($response)}");
                $this->assertNotSame('/cup/forbidden', $response->getHeaderLine('Location'), $route['name']);
            } else {
                $this->assertEquals(307, $response->getStatusCode(), $route['name']);
                $this->assertSame('/cup/forbidden', $response->getHeaderLine('Location'), $route['name']);
            }
        }

        $this->assertSame($before, $this->counts());
    }

    public function testAdeletedOrBlockedAdministratorLosesTheAccessAtOnce(): void
    {
        $this->fixtures();
        $user = User::where('username', 'admin')->first();
        $this->assertNull($user);

        $this->admin();
        $user = User::where('username', 'admin')->first();
        $this->getService(\App\Domain\Service\User\UserService::class)->update($user, ['status' => UserStatus::BLOCK]);

        $response = $this->cup('GET', '/cup/page');

        $this->assertEquals(307, $response->getStatusCode());
        $this->assertStringStartsWith('/auth/logout', $response->getHeaderLine('Location'));
    }

    public function testTheMenuFollowsTheSwitchesOfTheModules(): void
    {
        $this->fixtures();
        $this->assertStringContainsString('href="/cup/page"', (string) $this->cup('GET', '/cup')->getBody());

        $this->getService(\App\Domain\Service\Parameter\ParameterService::class)->create(['name' => 'page_is_enabled', 'value' => 'no']);

        $this->assertStringNotContainsString('href="/cup/page"', (string) $this->cup('GET', '/cup')->getBody());
    }

    /**
     * Pins how it is today: the menu is not cut down to the routes of the group, a user who may open
     * only the pages sees the links to everything and meets the forbidden page behind each of them
     */
    public function testTheMenuIsTheSameForEveryoneWhoMayOpenTheDashboard(): void
    {
        $this->fixtures();
        $group = $this->getService(GroupService::class)->create(['title' => 'Editors', 'access' => ['cup:main', 'cup:page:list']]);
        $user = $this->makeUser(['username' => 'editor', 'email' => 'editor@example.com', 'group_uuid' => $group->uuid]);

        $menu = (string) $this->http('GET', '/cup', ['headers' => $this->cookie($this->loginAs($user)['access_token'])])->getBody();

        $this->assertStringContainsString('href="/cup/api-key"', $menu);
        $this->assertStringContainsString('href="/cup/user/group"', $menu);
    }
}
