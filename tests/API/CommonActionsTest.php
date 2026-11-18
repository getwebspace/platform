<?php declare(strict_types=1);

namespace tests\API;

use App\Domain\Models\CatalogOrder;
use App\Domain\Models\GuestBook;
use App\Domain\Models\UserSubscriber;
use App\Domain\Service\Catalog\CategoryService;
use App\Domain\Service\Catalog\ProductService;
use App\Domain\Service\GuestBook\GuestBookService;
use App\Domain\Service\Page\PageService;
use App\Domain\Service\Parameter\ParameterService;
use App\Domain\Service\User\UserService;
use tests\Concerns\MakesAuthRequests;
use tests\TestCase;

/**
 * The public site: sign up / sign in pages, profile, subscription,
 * guestbook, search and the cart
 *
 * @internal
 *
 * #[CoversNothing]
 */
class CommonActionsTest extends TestCase
{
    use MakesAuthRequests;

    private const AJAX = ['X-Requested-With' => 'XMLHttpRequest'];

    private function setParameter(string $name, string $value): void
    {
        $this->getService(ParameterService::class)->create(['name' => $name, 'value' => $value]);
    }

    private function setCookies(\Psr\Http\Message\ResponseInterface $response): array
    {
        $cookies = [];
        foreach ($response->getHeader('Set-Cookie') as $line) {
            [$pair] = explode(';', $line, 2);
            [$name, $value] = array_pad(explode('=', $pair, 2), 2, '');
            $cookies[$name] = $value;
        }

        return $cookies;
    }

    // ---------------------------------------------------------- register / login

    public function testRegisteringOnTheSiteLogsTheNewUserIn(): void
    {
        $response = $this->http('POST', '/user/register', ['form_params' => [
            'username' => 'visitor',
            'email' => 'visitor@example.com',
            'password' => self::PASSWORD,
            'password_again' => self::PASSWORD,
        ]]);

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals('/user/profile', $response->getHeaderLine('Location'));
        $cookies = $this->setCookies($response);
        $this->assertNotEmpty($cookies['access_token'] ?? '');
        $this->assertNotEmpty($cookies['refresh_token'] ?? '');
    }

    public function testRegisteringWithConfirmationOnSetsNoSession(): void
    {
        $this->setParameter('user_confirmation', 'on');

        $response = $this->http('POST', '/user/register?format=json', ['form_params' => [
            'username' => 'visitor',
            'email' => 'visitor@example.com',
            'password' => self::PASSWORD,
            'password_again' => self::PASSWORD,
        ]]);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('sent', $this->json($response)['data']['stage']);
        $this->assertArrayNotHasKey('access_token', $this->setCookies($response));
        $this->assertEquals(
            \App\Domain\Casts\User\Status::CONFIRMATION,
            $this->getService(UserService::class)->read(['username' => 'visitor'])->status
        );
    }

    public function testRegistrationErrorsAreReportedPerField(): void
    {
        $this->makeUser(['username' => 'taken', 'email' => 'taken@example.com']);

        $cases = [
            'username' => ['username' => 'taken', 'email' => 'new@example.com'],
            'email' => ['username' => 'fresh', 'email' => 'taken@example.com'],
            'password_again' => ['username' => 'fresh', 'email' => 'new@example.com', 'password_again' => 'different'],
        ];

        foreach ($cases as $field => $form) {
            $response = $this->http('POST', '/user/register?format=json', ['form_params' => $form + [
                'password' => self::PASSWORD,
                'password_again' => self::PASSWORD,
            ]]);

            $this->assertEquals(200, $response->getStatusCode(), $field);
            $this->assertArrayHasKey($field, $this->json($response)['errors'], $field);
        }
    }

    public function testRegistrationWithNothingToIdentifyTheAccountIsRefused(): void
    {
        $response = $this->http('POST', '/user/register?format=json', ['form_params' => [
            'password' => self::PASSWORD,
            'password_again' => self::PASSWORD,
        ]]);

        $errors = $this->json($response)['errors'];
        $this->assertArrayHasKey('username', $errors);
        $this->assertArrayHasKey('email', $errors);
        $this->assertArrayHasKey('phone', $errors);
        $this->assertSame(0, \App\Domain\Models\User::count());
    }

    public function testRegisteringCannotMakeYourselfAnAdministrator(): void
    {
        $group = $this->getService(\App\Domain\Service\User\GroupService::class)->create(['title' => 'admins', 'access' => ['cup:main']]);

        $this->http('POST', '/user/register', ['form_params' => [
            'username' => 'sneaky',
            'password' => self::PASSWORD,
            'password_again' => self::PASSWORD,
            'group_uuid' => $group->uuid,
            'status' => 'work',
        ]]);

        $this->assertNull($this->getService(UserService::class)->read(['username' => 'sneaky'])->group_uuid);
    }

    public function testLoggingInOnTheSiteSetsTheCookiesAndRedirects(): void
    {
        $user = $this->makeUser();

        $response = $this->http('POST', '/user/login', ['form_params' => ['username' => $user->username, 'password' => self::PASSWORD]]);

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals('/user/profile', $response->getHeaderLine('Location'));
        $this->assertArrayHasKey('access_token', $this->setCookies($response));
    }

    public function testLoginAcceptsAnyIdentifierWhenTheFormSendsOne(): void
    {
        $user = $this->makeUser();

        foreach ([$user->email, $user->username] as $identifier) {
            $response = $this->http('POST', '/user/login', ['form_params' => ['identifier' => $identifier, 'password' => self::PASSWORD]]);

            $this->assertEquals(302, $response->getStatusCode(), $identifier);
        }
    }

    public function testLoginRedirectStaysOnTheSite(): void
    {
        $user = $this->makeUser();

        $good = $this->http('POST', '/user/login?redirect=/cart', ['form_params' => ['username' => $user->username, 'password' => self::PASSWORD]]);
        $this->assertEquals('/cart', $good->getHeaderLine('Location'));

        foreach (['//evil.example/phish', 'https://evil.example', '/\\evil.example'] as $target) {
            $bad = $this->http('POST', '/user/login?redirect=' . urlencode($target), ['form_params' => ['username' => $user->username, 'password' => self::PASSWORD]]);

            $this->assertEquals('/user/profile', $bad->getHeaderLine('Location'), $target);
        }
    }

    public function testFailedLoginsShowAnErrorAndSetNoCookie(): void
    {
        $user = $this->makeUser();

        $wrong = $this->http('POST', '/user/login?format=json', ['form_params' => ['username' => $user->username, 'password' => 'nope']]);
        $this->assertEquals(200, $wrong->getStatusCode());
        $this->assertArrayHasKey('password', $this->json($wrong)['errors']);
        $this->assertArrayNotHasKey('access_token', $this->setCookies($wrong));

        $unknown = $this->http('POST', '/user/login?format=json', ['form_params' => ['username' => 'ghost', 'password' => 'nope']]);
        $this->assertArrayHasKey('username', $this->json($unknown)['errors']);
    }

    public function testRepeatedFailuresLockTheAccountOutEvenWithTheRightPassword(): void
    {
        $this->setParameter('user_login_attempts', '3');
        $user = $this->makeUser();

        for ($i = 0; $i < 3; ++$i) {
            $this->http('POST', '/user/login?format=json', ['form_params' => ['username' => $user->username, 'password' => 'nope']]);
        }

        $response = $this->http('POST', '/user/login?format=json', ['form_params' => ['username' => $user->username, 'password' => self::PASSWORD]]);

        $this->assertArrayHasKey('username', $this->json($response)['errors']);
        $this->assertStringContainsString('EXCEPTION_TOO_MANY_ATTEMPTS', json_encode($this->json($response)['errors']['username']));
        $this->assertArrayNotHasKey('access_token', $this->setCookies($response));
    }

    public function testThrottleIsPerAccountSoOthersAreNotLockedOut(): void
    {
        $this->setParameter('user_login_attempts', '2');
        $victim = $this->makeUser();
        $other = $this->makeUser();

        for ($i = 0; $i < 2; ++$i) {
            $this->http('POST', '/user/login?format=json', ['form_params' => ['username' => $victim->username, 'password' => 'nope']]);
        }

        $response = $this->http('POST', '/user/login', ['form_params' => ['username' => $other->username, 'password' => self::PASSWORD]]);

        $this->assertEquals(302, $response->getStatusCode());
    }

    public function testASuccessfulLoginResetsTheFailureCount(): void
    {
        $this->setParameter('user_login_attempts', '3');
        $user = $this->makeUser();

        for ($round = 0; $round < 3; ++$round) {
            $this->http('POST', '/user/login?format=json', ['form_params' => ['username' => $user->username, 'password' => 'nope']]);
            $this->http('POST', '/user/login?format=json', ['form_params' => ['username' => $user->username, 'password' => 'nope']]);

            $ok = $this->http('POST', '/user/login', ['form_params' => ['username' => $user->username, 'password' => self::PASSWORD]]);
            $this->assertEquals(302, $ok->getStatusCode(), 'round ' . $round);
        }
    }

    public function testRedirectsAfterAFormAreTemporary(): void
    {
        $user = $this->makeUser();

        $login = $this->http('POST', '/user/login', ['form_params' => ['username' => $user->username, 'password' => self::PASSWORD]]);

        // a permanent redirect would be cached by the browser: the login page would jump to the profile for good
        $this->assertEquals(302, $login->getStatusCode());
    }

    public function testLogoutGoesThroughTheAuthEndpoint(): void
    {
        $response = $this->http('GET', '/user/logout');

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals('/auth/logout', $response->getHeaderLine('Location'));
    }

    // ------------------------------------------------------------------- profile

    public function testProfileChangesOnlyWhatTheFormSent(): void
    {
        $user = $this->makeUser(['firstname' => 'Ann', 'city' => 'Kyiv']);
        $login = $this->loginAs($user);

        $response = $this->http('POST', '/user/profile', [
            'headers' => $this->cookie($login['access_token']),
            'form_params' => ['firstname' => 'Anna'],
        ]);

        $this->assertEquals(302, $response->getStatusCode());
        $fresh = $this->getService(UserService::class)->read(['uuid' => $user->uuid]);
        $this->assertEquals('Anna', $fresh->firstname);
        $this->assertEquals('Kyiv', $fresh->city, 'a field that was not sent keeps its value');
        $this->assertEquals($user->username, $fresh->username);
    }

    public function testProfileCanChangeThePassword(): void
    {
        $user = $this->makeUser();
        $login = $this->loginAs($user);

        $this->http('POST', '/user/profile', [
            'headers' => $this->cookie($login['access_token']),
            'form_params' => ['password' => 'a brand new one'],
        ]);

        $this->loginAs($user, 'a brand new one');
    }

    public function testProfileKeepsThePasswordWhenTheFieldIsEmpty(): void
    {
        $user = $this->makeUser();
        $login = $this->loginAs($user);

        $this->http('POST', '/user/profile', [
            'headers' => $this->cookie($login['access_token']),
            'form_params' => ['password' => '', 'firstname' => 'Same'],
        ]);

        $this->loginAs($user);
    }

    public function testProfileReportsAnEmailThatBelongsToSomeoneElse(): void
    {
        $this->makeUser(['email' => 'taken@example.com']);
        $user = $this->makeUser();
        $login = $this->loginAs($user);

        $response = $this->http('POST', '/user/profile?format=json', [
            'headers' => $this->cookie($login['access_token']),
            'form_params' => ['email' => 'taken@example.com'],
        ]);

        $this->assertArrayHasKey('email', $this->json($response)['errors']);
        $this->assertNotEquals('taken@example.com', $this->getService(UserService::class)->read(['uuid' => $user->uuid])->email);
    }

    public function testProfileCannotElevateTheAccount(): void
    {
        $group = $this->getService(\App\Domain\Service\User\GroupService::class)->create(['title' => 'admins', 'access' => ['cup:main']]);
        $user = $this->makeUser();
        $login = $this->loginAs($user);

        $this->http('POST', '/user/profile', [
            'headers' => $this->cookie($login['access_token']),
            'form_params' => ['group_uuid' => $group->uuid, 'status' => 'delete', 'firstname' => 'x'],
        ]);

        $fresh = $this->getService(UserService::class)->read(['uuid' => $user->uuid]);
        $this->assertNull($fresh->group_uuid);
        $this->assertEquals(\App\Domain\Casts\User\Status::WORK, $fresh->status);
    }

    // ---------------------------------------------------------------- subscribe

    public function testSubscribingAndUnsubscribing(): void
    {
        $subscribe = $this->http('POST', '/user/subscriber', ['form_params' => ['email' => 'reader@example.com']]);
        $this->assertEquals(201, $this->json($subscribe)['status']);
        $this->assertSame(1, UserSubscriber::count());

        $unsubscribe = $this->http('POST', '/user/subscriber', ['form_params' => ['email' => 'reader@example.com', 'action' => 'unsubscribe']]);
        $this->assertEquals(202, $this->json($unsubscribe)['status']);
        $this->assertSame(0, UserSubscriber::count());
    }

    public function testSubscribingTwiceIsNotAnError(): void
    {
        $this->http('POST', '/user/subscriber', ['form_params' => ['email' => 'reader@example.com']]);

        $twice = $this->http('POST', '/user/subscriber', ['form_params' => ['email' => 'reader@example.com']]);

        $this->assertEquals(304, $this->json($twice)['status']);
        $this->assertSame(1, UserSubscriber::count());
    }

    public function testASubscriberNeedsAValidAddress(): void
    {
        $this->http('POST', '/user/subscriber', ['form_params' => ['email' => 'nonsense']]);

        $this->assertSame(0, UserSubscriber::count());
    }

    // ----------------------------------------------------------------- guestbook

    public function testAGuestbookEntryWaitsForModeration(): void
    {
        $response = $this->http('POST', '/guestbook', [
            'headers' => self::AJAX,
            'form_params' => ['name' => 'Ann', 'email' => 'ann@example.com', 'message' => 'Hello'],
        ]);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertSame(1, GuestBook::count());

        $page = $this->http('GET', '/guestbook?format=json');
        $this->assertSame(0, $this->json($page)['data']['pagination']['count'], 'not shown before it is approved');
        $this->assertStringNotContainsString('Hello', (string) $page->getBody());
    }

    public function testApprovedEntriesAreListedWithTheEmailMasked(): void
    {
        $entry = $this->getService(GuestBookService::class)->create(['name' => 'Ann', 'email' => 'ann.smith@example.com', 'message' => 'Visible words']);
        $this->getService(GuestBookService::class)->update($entry, ['status' => \App\Domain\Casts\GuestBook\Status::WORK]);

        $body = (string) $this->http('GET', '/guestbook?format=json')->getBody();

        $this->assertStringContainsString('Visible words', $body);
        $this->assertStringNotContainsString('ann.smith@example.com', $body, 'the address must not be published');
    }

    public function testGuestbookReportsTheMissingField(): void
    {
        $full = ['name' => 'Ann', 'email' => 'ann@example.com', 'message' => 'Hello'];

        foreach (array_keys($full) as $missing) {
            $response = $this->http('POST', '/guestbook?format=json', ['form_params' => array_merge($full, [$missing => ''])]);

            $this->assertArrayHasKey($missing, $this->json($response)['errors'], $missing);
        }

        $this->assertSame(0, GuestBook::count());
    }

    public function testGuestbookPaginates(): void
    {
        $this->setParameter('guestbook_pagination', '2');
        $service = $this->getService(GuestBookService::class);
        foreach (range(1, 5) as $i) {
            $entry = $service->create(['name' => 'N' . $i, 'email' => "n{$i}@example.com", 'message' => 'm' . $i]);
            $service->update($entry, ['status' => \App\Domain\Casts\GuestBook\Status::WORK]);
        }

        $first = $this->json($this->http('GET', '/guestbook?format=json'))['data'];
        $last = $this->json($this->http('GET', '/guestbook/2?format=json'))['data'];

        $this->assertSame(5, $first['pagination']['count']);
        $this->assertCount(2, $first['messages']);
        $this->assertCount(1, $last['messages']);
    }

    // -------------------------------------------------------------------- search

    public function testSearchFindsAPageAndEscapesTheQuery(): void
    {
        $this->getService(PageService::class)->create(['title' => 'Delivery terms', 'address' => 'delivery', 'content' => 'We deliver everywhere']);

        $plain = $this->http('GET', '/search?format=json&q=Delivery');
        $this->assertEquals(200, $plain->getStatusCode());
        $this->assertEquals('noindex, nofollow', $plain->getHeaderLine('X-Robots-Tag'));

        $hostile = $this->http('GET', '/search?format=json&q=' . urlencode('<script>alert(1)</script>'));
        $this->assertEquals(200, $hostile->getStatusCode());
        $this->assertEquals('alert(1)', $this->json($hostile)['data']['query'], 'markup is stripped from the query');
    }

    public function testSearchPageSizeIsClamped(): void
    {
        $huge = $this->json($this->http('GET', '/search?format=json&q=a&limit=100000'))['data'];
        $zero = $this->json($this->http('GET', '/search?format=json&q=a&limit=0&page=-5'))['data'];

        $this->assertSame(100, $huge['limit']);
        $this->assertSame(1, $zero['limit']);
        $this->assertSame(1, $zero['page']);
    }

    // ---------------------------------------------------------------------- cart

    private function makeProduct(string $title = 'Gadget', float $price = 10.0): \App\Domain\Models\CatalogProduct
    {
        $category = $this->getService(CategoryService::class)->create(['title' => 'Cat ' . $title, 'address' => 'cat-' . strtolower($title)]);

        return $this->getService(ProductService::class)->create([
            'title' => $title,
            'address' => strtolower($title),
            'category_uuid' => $category->uuid,
            'type' => \App\Domain\Casts\Catalog\Product\Type::PRODUCT,
            'price' => $price,
            'stock' => 100,
        ]);
    }

    public function testACartWithAMalformedContactReportsTheField(): void
    {
        $form = ['email' => 'buyer@example.com', 'phone' => '+380501234567', 'delivery' => ['address' => 'x']];

        $badEmail = $this->http('POST', '/cart?format=json', ['headers' => self::AJAX, 'form_params' => ['email' => 'nonsense'] + $form]);
        $this->assertArrayHasKey('email', $this->json($badEmail)['errors']);

        $badPhone = $this->http('POST', '/cart?format=json', ['headers' => self::AJAX, 'form_params' => ['phone' => 'abc'] + $form]);
        $this->assertArrayHasKey('phone', $this->json($badPhone)['errors']);

        $this->assertSame(0, CatalogOrder::count());
    }

    public function testCartPageIsNotIndexed(): void
    {
        $response = $this->http('GET', '/cart?format=json');

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('noindex, nofollow', $response->getHeaderLine('X-Robots-Tag'));
    }

    public function testAnOrderIsCreatedFromTheCart(): void
    {
        $product = $this->makeProduct();

        $response = $this->http('POST', '/cart', [
            'headers' => self::AJAX,
            'form_params' => [
                'email' => 'buyer@example.com',
                'phone' => '+380501234567',
                'comment' => 'ring twice',
                'delivery' => ['address' => 'Main st 1'],
                'phone' => '+380501234567',
                'products' => [$product->uuid => ['count' => 2, 'price_type' => 'price']],
            ],
        ]);

        $this->assertEquals(200, $response->getStatusCode(), (string) $response->getBody());
        $order = CatalogOrder::first();
        $this->assertNotNull($order);
        $this->assertEquals('/cart/done/' . $order->uuid, $this->json($response)['redirect']);
        $this->assertEquals('buyer@example.com', $order->email);
        $this->assertEquals(2.0, (float) $order->products->first()->pivot->count);
    }

    public function testACartWithoutAPriceTypeStillMakesAnOrder(): void
    {
        $product = $this->makeProduct();

        $response = $this->http('POST', '/cart', [
            'headers' => self::AJAX,
            'form_params' => [
                'email' => 'buyer@example.com',
                'delivery' => ['address' => 'Main st 1'],
                'phone' => '+380501234567',
                'products' => [$product->uuid => ['count' => 1]],
            ],
        ]);

        $this->assertEquals(200, $response->getStatusCode(), $this->why($response));
        $this->assertSame(1, CatalogOrder::count());
    }

    public function testAnOrderIgnoresUnknownProducts(): void
    {
        $response = $this->http('POST', '/cart', [
            'headers' => self::AJAX,
            'form_params' => [
                'email' => 'buyer@example.com',
                'delivery' => ['address' => 'Main st 1'],
                'phone' => '+380501234567',
                'products' => [\Ramsey\Uuid\Uuid::uuid4()->toString() => ['count' => 1, 'price_type' => 'price']],
            ],
        ]);

        $this->assertEquals(200, $response->getStatusCode(), $this->why($response));
        $this->assertCount(0, CatalogOrder::first()->products);
    }

    public function testAnOrderIsBoundToTheLoggedInUser(): void
    {
        $user = $this->makeUser();
        $login = $this->loginAs($user);

        $this->http('POST', '/cart', [
            'headers' => self::AJAX + $this->cookie($login['access_token']),
            'form_params' => ['email' => 'buyer@example.com', 'phone' => '+380501234567', 'delivery' => ['address' => 'x']],
        ]);

        $this->assertEquals($user->uuid, CatalogOrder::first()->user_uuid);
    }

    public function testAnOrderCannotBeAttributedToAnotherUser(): void
    {
        $victim = $this->makeUser();

        $this->http('POST', '/cart', [
            'headers' => self::AJAX,
            'form_params' => ['email' => 'buyer@example.com', 'phone' => '+380501234567', 'delivery' => ['address' => 'x'], 'user_uuid' => $victim->uuid],
        ]);

        $this->assertNull(CatalogOrder::first()->user_uuid);
    }

    public function testACartWithoutDeliveryStillMakesAnOrder(): void
    {
        $response = $this->http('POST', '/cart', ['headers' => self::AJAX, 'form_params' => ['email' => 'buyer@example.com']]);

        $this->assertEquals(200, $response->getStatusCode(), $this->why($response));
        $this->assertSame(1, CatalogOrder::count());
    }

    private function orderForLimit(): array
    {
        return $this->json($this->http('POST', '/cart', ['headers' => self::AJAX, 'form_params' => [
            'email' => 'buyer@example.com',
            'phone' => '+380501234567',
            'delivery' => ['address' => 'x'],
            'shipping' => 'now',
        ]]));
    }

    public function testShippingLimitEventuallyStopsFurtherOrders(): void
    {
        $this->setParameter('catalog_order_limit', '3');

        $results = [$this->orderForLimit(), $this->orderForLimit(), $this->orderForLimit(), $this->orderForLimit()];

        $this->assertArrayHasKey('redirect', $results[0]);
        $this->assertArrayHasKey('shipping', $results[3]['errors'] ?? []);
        $this->assertLessThanOrEqual(3, CatalogOrder::count());
    }

    public function testShippingLimitAllowsExactlyTheConfiguredNumberOfOrders(): void
    {
        $this->setParameter('catalog_order_limit', '2');

        $this->assertArrayHasKey('redirect', $this->orderForLimit());
        $this->assertArrayHasKey('redirect', $this->orderForLimit());
        $this->assertArrayHasKey('shipping', $this->orderForLimit()['errors'] ?? []);
        $this->assertSame(2, CatalogOrder::count());
    }

    public function testCartDoneShowsTheOrderAndRejectsEverythingElse(): void
    {
        $order = $this->getService(\App\Domain\Service\Catalog\OrderService::class)->create(['email' => 'buyer@example.com']);

        $ok = $this->http('GET', '/cart/done/' . $order->uuid . '?format=json');
        $this->assertEquals(200, $ok->getStatusCode());
        $this->assertEquals('noindex, nofollow', $ok->getHeaderLine('X-Robots-Tag'));

        $unknown = $this->http('GET', '/cart/done/' . \Ramsey\Uuid\Uuid::uuid4() . '?format=json');
        $this->assertEquals(404, $unknown->getStatusCode());

        $junk = $this->http('GET', '/cart/done/not-a-uuid');
        $this->assertEquals(302, $junk->getStatusCode());
        $this->assertEquals('/cart', $junk->getHeaderLine('Location'));
    }
}
