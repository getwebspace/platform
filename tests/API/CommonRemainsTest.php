<?php declare(strict_types=1);

namespace tests\API;

use App\Domain\Models\CatalogOrder;
use App\Domain\Models\FormData;
use App\Domain\Models\GuestBook;
use App\Domain\Models\Review;
use App\Domain\Models\UserSubscriber;
use App\Domain\Service\Catalog\CategoryService;
use App\Domain\Service\Catalog\ProductService;
use App\Domain\Service\Form\FormService;
use App\Domain\Service\Page\PageService;
use App\Domain\Service\Parameter\ParameterService;
use App\Domain\Service\Publication\CategoryService as PublicationCategoryService;
use App\Domain\Service\Publication\PublicationService;
use App\Domain\Service\Review\ReviewService;
use App\Domain\Service\User\UserService;
use tests\API\Mcp\McpTestCase;
use tests\Concerns\MakesAuthRequests;

/**
 * The corners of the public site the main scenarios do not reach: the answers to a wrong captcha,
 * a missing record, a taken value, and what a form sends along with the data
 *
 * @internal
 *
 * #[CoversNothing]
 */
class CommonRemainsTest extends McpTestCase
{
    use MakesAuthRequests;

    private const AJAX = ['X-Requested-With' => 'XMLHttpRequest'];

    private ?string $pagesTheme = null;

    public function tearDown(): void
    {
        if ($this->pagesTheme !== null) {
            foreach (glob($this->pagesTheme . '/*') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($this->pagesTheme);
            $this->pagesTheme = null;
        }

        parent::tearDown();
    }

    /**
     * The default theme of the repository has no pages for the visitor's account: a theme with the few that show the errors
     * of the form, so that the answer to a wrong value can be seen
     */
    private function pagesTheme(): void
    {
        $this->pagesTheme = THEME_DIR . '/zz-test-pages';
        @mkdir($this->pagesTheme, 0o777);
        chmod($this->pagesTheme, 0o777);

        foreach (['login', 'register', 'profile', 'confirmation', 'lostpassword'] as $page) {
            file_put_contents($this->pagesTheme . "/user.{$page}.twig", "PAGE:{$page} ERRORS:{{ _error|keys|join(',') }}");
            chmod($this->pagesTheme . "/user.{$page}.twig", 0o666);
        }

        $this->setting('common_theme', 'zz-test-pages');
    }

    private function setting(string $name, string $value): void
    {
        $this->getService(ParameterService::class)->create(['name' => $name, 'value' => $value]);
    }

    /**
     * With the check on and no way to reach the service in the tests, no answer is a right one
     */
    private function captchaOn(): void
    {
        $this->setting('recaptcha_is_enabled', 'on');
        $this->setting('recaptcha_private', 'not-a-real-secret');
    }

    private ?\App\Domain\Models\CatalogProduct $product = null;

    private function product(): \App\Domain\Models\CatalogProduct
    {
        return $this->product ??= $this->makeGadget();
    }

    private function makeGadget(): \App\Domain\Models\CatalogProduct
    {
        $category = $this->getService(CategoryService::class)->create(['title' => 'Goods', 'address' => 'goods']);

        return $this->getService(ProductService::class)->create(['title' => 'Gadget', 'address' => 'gadget', 'category_uuid' => $category->uuid, 'type' => 'product', 'price' => 10, 'stock' => 100]);
    }

    private function publication(): \App\Domain\Models\Publication
    {
        $category = $this->getService(PublicationCategoryService::class)->create(['title' => 'News', 'address' => 'news']);

        return $this->getService(PublicationService::class)->create(['title' => 'Post', 'address' => 'news/post', 'category_uuid' => $category->uuid, 'content' => ['short' => 'a', 'full' => 'b']]);
    }

    // --------------------------------------------------------------------- cart

    private function cartFields(array $extra = []): array
    {
        return $extra + [
            'email' => 'buyer@example.com', 'phone' => '+380501234567', 'delivery' => ['address' => 'Main st 1'],
            'products' => [$this->product()->uuid => ['count' => 1, 'price_type' => 'price']],
        ];
    }

    public function testTheOtherFieldsOfTheFormGoToTheCommentWhenThatIsSwitchedOn(): void
    {
        $this->setting('catalog_order_fields', 'on');

        $response = $this->http('POST', '/cart', ['headers' => self::AJAX, 'form_params' => $this->cartFields(['comment' => 'ring twice', 'gift' => 'yes', 'empty' => ''])]);

        $this->assertSame(200, $response->getStatusCode(), $this->why($response));
        $this->assertSame("ring twice\ngift yes", CatalogOrder::first()->comment);
    }

    public function testAnAddressInPartsIsJoinedAndCanBeSortedByTheirNames(): void
    {
        $this->http('POST', '/cart', ['headers' => self::AJAX, 'form_params' => $this->cartFields(['delivery' => ['address' => ['b_house' => '5', 'a_city' => 'Kyiv']]])]);
        $this->assertSame('5, Kyiv', CatalogOrder::first()->delivery['address']);

        CatalogOrder::query()->delete();
        $this->setting('catalog_order_address', 'on');
        $this->http('POST', '/cart', ['headers' => self::AJAX, 'form_params' => $this->cartFields(['delivery' => ['address' => ['b_house' => '5', 'a_city' => 'Kyiv']]])]);
        $this->assertSame('Kyiv, 5', CatalogOrder::first()->delivery['address']);
    }

    public function testACartIsNotAnOrderWithoutThePassedCaptcha(): void
    {
        $this->captchaOn();

        $response = $this->http('POST', '/cart?format=json', ['headers' => self::AJAX, 'form_params' => $this->cartFields()]);

        $this->assertArrayHasKey('grecaptcha', $this->json($response)['errors']);
        $this->assertSame(0, CatalogOrder::count());
    }

    public function testAnOrderMadeFromAPageGoesOnWithAnOrdinaryRedirect(): void
    {
        $response = $this->http('POST', '/cart', ['headers' => ['Referer' => 'http://localhost/cart'], 'form_params' => $this->cartFields()]);

        $this->assertSame(302, $response->getStatusCode(), 'a 301 after a post is remembered by the browser');
        $this->assertStringStartsWith('/cart/done/', $response->getHeaderLine('Location'));
    }

    public function testTheOrderPageOfAnUnknownOrderIsNotShown(): void
    {
        $response = $this->http('GET', '/cart/done/' . \Ramsey\Uuid\Uuid::uuid4()->toString());

        $this->assertContains($response->getStatusCode(), [302, 404]);
    }

    // -------------------------------------------------------------------- forms

    private function contactForm(array $extra = []): void
    {
        $this->getService(FormService::class)->create($extra + ['title' => 'Contact', 'address' => 'contact', 'mailto' => 'boss@example.com']);
    }

    public function testAFormThatAllowsEveryOriginAnswersToEveryOrigin(): void
    {
        $this->contactForm(['origin' => '*']);

        $response = $this->http('POST', '/form/contact', ['headers' => self::AJAX + ['Origin' => 'https://anywhere.example'], 'form_params' => ['email' => 'v@example.com']]);

        $this->assertSame('*', $response->getHeaderLine('Access-Control-Allow-Origin'));
        $this->assertSame('POST, OPTIONS', $response->getHeaderLine('Access-Control-Allow-Methods'));
    }

    public function testAFileSentWithAFormIsKeptAndListed(): void
    {
        $this->contactForm();

        $response = $this->http('POST', '/form/contact', [
            'headers' => self::AJAX,
            'multipart' => [
                ['name' => 'email', 'contents' => 'v@example.com'],
                ['name' => 'files[]', 'contents' => 'the words', 'filename' => 'note.txt', 'headers' => ['Content-Type' => 'text/plain']],
            ],
        ]);

        $this->assertSame(200, $response->getStatusCode(), $this->why($response));
        $data = FormData::first();
        $this->assertNotNull($data);
        $this->assertSame(1, $data->files()->count());
        $this->assertSame('note.txt', $data->files->first()->filename());
        $this->assertFileExists($data->files->first()->internal_path());
    }

    public function testAFormWithACaptchaIsNotAcceptedWithoutIt(): void
    {
        $this->captchaOn();
        $this->contactForm(['recaptcha' => true]);

        $response = $this->http('POST', '/form/contact', ['headers' => self::AJAX, 'form_params' => ['email' => 'v@example.com']]);

        $this->assertSame(500, $response->getStatusCode());
        $this->assertSame(0, FormData::count());
    }

    // ---------------------------------------------------------------- guestbook

    public function testAGuestBookMessageGoesBackToThePageItWasWrittenOn(): void
    {
        $response = $this->http('POST', '/guestbook', [
            'headers' => ['Referer' => 'http://localhost/guestbook'],
            'form_params' => ['name' => 'Ann', 'email' => 'ann@example.com', 'message' => 'Hello'],
        ]);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('http://localhost/guestbook', $response->getHeaderLine('Location'));
        $this->assertSame(1, GuestBook::count());
    }

    public function testAGuestBookMessageIsNotAcceptedWithoutTheCaptcha(): void
    {
        $this->captchaOn();

        $this->http('POST', '/guestbook', ['headers' => self::AJAX, 'form_params' => ['name' => 'Ann', 'email' => 'ann@example.com', 'message' => 'Hello']]);

        $this->assertSame(0, GuestBook::count());
    }

    // ------------------------------------------------------------------ reviews

    private function reviewer(): array
    {
        $user = $this->getService(UserService::class)->create(['username' => 'critic', 'email' => 'critic@example.com', 'password' => self::PASSWORD]);

        return [$user, ['Cookie' => 'access_token=' . $this->signToken('user', $user->uuid)]];
    }

    private function review(string $path, array $headers, array $fields): \Psr\Http\Message\ResponseInterface
    {
        return $this->http('POST', $path, ['headers' => $headers, 'form_params' => $fields]);
    }

    public function testAReviewOfSomethingThatDoesNotExistIsNotTaken(): void
    {
        $this->setting('review_publication_is_enabled', 'yes');
        [, $headers] = $this->reviewer();

        $response = $this->review('/publication/' . \Ramsey\Uuid\Uuid::uuid4()->toString() . '/review', $headers, ['message' => 'Hi']);

        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame('Entity not found', $this->json($response)['error']);
    }

    public function testAReviewIsNotTakenWithoutTheCaptcha(): void
    {
        $this->setting('review_publication_is_enabled', 'yes');
        $this->captchaOn();
        [, $headers] = $this->reviewer();

        $response = $this->review("/publication/{$this->publication()->uuid}/review", $headers, ['message' => 'Hi']);

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame(0, Review::count());
    }

    public function testAReplyToAnEntryOfAnotherEntityIsNotTaken(): void
    {
        $this->setting('review_publication_is_enabled', 'yes');
        [, $headers] = $this->reviewer();
        $publication = $this->publication();
        $other = $this->getService(PublicationService::class)->create(['title' => 'Other', 'address' => 'news/other', 'category_uuid' => $publication->category_uuid, 'content' => ['short' => 'a', 'full' => 'b']]);
        $foreign = $this->getService(ReviewService::class)->create(['type' => 'review', 'entity_type' => 'publication', 'entity_uuid' => $other->uuid, 'message' => 'Elsewhere']);

        $response = $this->review("/publication/{$publication->uuid}/review", $headers, ['message' => 'Hi', 'parent_uuid' => $foreign->uuid]);

        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame('Parent not found', $this->json($response)['error']);
        $this->assertSame(1, Review::count());
    }

    public function testAReviewWithoutAMessageIsRefused(): void
    {
        $this->setting('review_publication_is_enabled', 'yes');
        [, $headers] = $this->reviewer();

        $response = $this->review("/publication/{$this->publication()->uuid}/review", $headers, ['message' => '']);

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame(0, Review::count());
    }

    // ------------------------------------------------------------------- search

    public function testTheSearchPageSendsTheCatalogWithTheCatalogPrefix(): void
    {
        $this->withSearchIndexAside(function (): void {
            $this->product();
            $this->getService(PageService::class)->create(['title' => 'Gadget guide', 'address' => 'gadget-guide', 'content' => 'gadget']);
            $container = $this->getService(\Psr\Container\ContainerInterface::class);
            $model = (new \App\Domain\Tasks\SearchIndexTask($container))->execute();
            (new \App\Domain\Tasks\SearchIndexTask($container, $model))->run();
            foreach (glob(CACHE_DIR . '/*.index') as $file) {
                chmod($file, 0o666);
            }

            $found = $this->json($this->http('GET', '/search?format=json&q=gadget'))['data'];

            $addresses = array_column($found['result'] ?? [], 'address');
            $this->assertContains('catalog/gadget', $addresses);
            $this->assertContains('gadget-guide', $addresses);
        });
    }

    // ---------------------------------------------------------------- files etc.

    public function testAFileThatDoesNotExistIsNotFound(): void
    {
        $this->assertSame(404, $this->http('GET', '/file/get/nosalt/nohash')->getStatusCode());
    }

    // -------------------------------------------------------------------- users

    public function testLoginRegistrationAndConfirmationAreNotAnsweredWithoutTheCaptcha(): void
    {
        $this->pagesTheme();
        $this->captchaOn();
        $user = $this->makeUser();

        $login = $this->http('POST', '/user/login', ['form_params' => ['username' => $user->username, 'password' => self::PASSWORD]]);
        $this->assertSame(200, $login->getStatusCode(), 'the form again, not a session');
        $this->assertStringContainsString('ERRORS:grecaptcha', (string) $login->getBody());
        $this->assertArrayNotHasKey('access_token', $this->cookiesOf($login));

        $register = $this->http('POST', '/user/register', ['form_params' => ['username' => 'newbie', 'email' => 'newbie@example.com', 'password' => self::PASSWORD, 'password_again' => self::PASSWORD]]);
        $this->assertStringContainsString('ERRORS:grecaptcha', (string) $register->getBody());
        $this->assertNull(\App\Domain\Models\User::firstWhere('username', 'newbie'));

        $resend = $this->http('POST', '/user/confirm', ['form_params' => ['identifier' => $user->email]]);
        $this->assertStringContainsString('ERRORS:grecaptcha', (string) $resend->getBody());

        $recovery = $this->http('POST', '/user/recovery', ['form_params' => ['identifier' => $user->email]]);
        $this->assertStringContainsString('ERRORS:grecaptcha', (string) $recovery->getBody());
    }

    public function testTheLetterIsNotSentAgainWithoutSayingToWhom(): void
    {
        $this->pagesTheme();

        $response = $this->http('POST', '/user/confirm', ['form_params' => ['identifier' => '  ']]);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('ERRORS:identifier', (string) $response->getBody());
        $this->assertSame(0, \App\Domain\Models\Task::count());
    }

    public function testAMalformedPhoneIsReportedOnRegistrationAndOnTheProfile(): void
    {
        $this->pagesTheme();

        $register = $this->http('POST', '/user/register', ['form_params' => ['username' => 'newbie', 'email' => 'newbie@example.com', 'phone' => 'abc', 'password' => self::PASSWORD, 'password_again' => self::PASSWORD]]);
        $this->assertStringContainsString('ERRORS:phone', (string) $register->getBody());
        $this->assertNull(\App\Domain\Models\User::firstWhere('username', 'newbie'));

        $user = $this->makeUser();
        $profile = $this->http('POST', '/user/profile', ['headers' => $this->cookie($this->loginAs($user)['access_token'] ?? ''), 'form_params' => ['phone' => 'abc']]);
        $this->assertSame(200, $profile->getStatusCode());
        $this->assertStringContainsString('ERRORS:phone', (string) $profile->getBody());
        $this->assertNotSame('abc', \App\Domain\Models\User::find($user->uuid)->phone);
    }

    public function testAUsernameOfAnotherUserCannotBeTakenOnTheProfile(): void
    {
        $this->pagesTheme();
        $taken = $this->makeUser();
        $user = $this->makeUser();

        $profile = $this->http('POST', '/user/profile', ['headers' => $this->cookie($this->loginAs($user)['access_token'] ?? ''), 'form_params' => ['username' => $taken->username]]);

        $this->assertSame(200, $profile->getStatusCode());
        $this->assertStringContainsString('ERRORS:username', (string) $profile->getBody());
        $this->assertNotSame($taken->username, \App\Domain\Models\User::find($user->uuid)->username);
    }

    public function testASubscriptionGoesBackToThePageItCameFrom(): void
    {
        $response = $this->http('POST', '/user/subscriber', ['headers' => ['Referer' => 'http://localhost/news'], 'form_params' => ['email' => 'reader@example.com']]);

        $this->assertContains($response->getStatusCode(), [301, 302]);
        $this->assertSame('http://localhost/news', $response->getHeaderLine('Location'));
        $this->assertSame(1, UserSubscriber::count());
    }

    public function testRevokingATokenPassesTheWishedPlaceOn(): void
    {
        $response = $this->http('GET', '/user/revoke?redirect=/user/login&uuid=abc');

        $this->assertContains($response->getStatusCode(), [301, 302, 307]);
        $this->assertStringStartsWith('/auth/revoke?', $response->getHeaderLine('Location'));
        $this->assertStringContainsString('redirect=/user/login', $response->getHeaderLine('Location'));
    }

    // ------------------------------------------------------------------ catalog

    public function testTheCatalogCanBeNarrowedByPriceCountryAndManufacturer(): void
    {
        $category = $this->getService(CategoryService::class)->create(['title' => 'Goods', 'address' => 'goods']);
        $products = $this->getService(ProductService::class);
        $make = fn (string $title, float $price, string $country, string $maker) => $products->create([
            'title' => $title, 'address' => 'goods/' . strtolower($title), 'category_uuid' => $category->uuid, 'type' => 'product',
            'price' => $price, 'country' => $country, 'manufacturer' => $maker,
        ]);
        $make('Cheap', 5, 'UA', 'Acme');
        $make('Middle', 50, 'PL', 'Acme');
        $make('Costly', 500, 'PL', 'Zeta');

        $titles = function (string $query): array {
            $data = $this->json($this->http('GET', '/catalog/goods?format=json&' . $query));

            return array_values(array_unique(array_filter(array_map(fn ($p) => is_array($p) ? ($p['title'] ?? null) : null, $data['data']['products']['filtered'] ?? []))));
        };

        $this->assertEqualsCanonicalizing(['Middle', 'Costly'], $titles('price[min]=10'));
        $this->assertEqualsCanonicalizing(['Cheap', 'Middle'], $titles('price[max]=100'));
        $this->assertSame(['Middle'], $titles('price[min]=10&price[max]=100'));
        $this->assertEqualsCanonicalizing(['Middle', 'Costly'], $titles('country=PL'));
        $this->assertSame(['Costly'], $titles('manufacturer=Zeta'));
        $this->assertSame(['Middle'], $titles('country=PL&manufacturer=Acme'));
    }

    public function testACatalogAddressThatDoesNotExistIsNotFound(): void
    {
        $this->assertSame(404, $this->http('GET', '/catalog/no/such/thing')->getStatusCode());
    }
}
