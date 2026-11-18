<?php declare(strict_types=1);

namespace tests\Concerns;

use App\Domain\Models\Reference;
use App\Domain\Service\ApiKey\ApiKeyService;
use App\Domain\Service\Catalog\AttributeService;
use App\Domain\Service\Catalog\CategoryService as CatalogCategoryService;
use App\Domain\Service\Catalog\OrderService;
use App\Domain\Service\Catalog\ProductService;
use App\Domain\Service\File\FileService;
use App\Domain\Service\Form\DataService as FormDataService;
use App\Domain\Service\Form\FormService;
use App\Domain\Service\GuestBook\GuestBookService;
use App\Domain\Service\Page\PageService;
use App\Domain\Service\Publication\CategoryService as PublicationCategoryService;
use App\Domain\Service\Publication\PublicationService;
use App\Domain\Service\Reference\ReferenceService;
use App\Domain\Service\Review\ReviewService;
use App\Domain\Service\User\GroupService;
use App\Domain\Service\User\SubscriberService;
use Psr\Http\Message\ResponseInterface;

/**
 * Working in the admin panel: an administrator who may use every route,
 * one record of everything to open, and the real route table
 */
trait UsesCup
{
    use MakesAuthRequests;

    /** routes any visitor may open */
    protected const CUP_PUBLIC = ['cup:login', 'cup:forbidden', 'cup:system'];

    private ?array $cupCookie = null;

    private string $cupToken = '';

    protected ?array $cupFixtures = null;

    /** @var string[] files to remove after the test */
    protected array $cupFiles = [];

    protected function cupTearDown(): void
    {
        foreach ($this->cupFiles as $file) {
            try {
                $this->getService(FileService::class)->delete($file);
            } catch (\Throwable) {
                // gone already
            }
        }
        $this->cupFiles = [];
    }

    /**
     * @return string[] names of the routes behind the access check
     */
    protected function cupRouteNames(): array
    {
        $names = [];
        foreach ($this->allRoutes() as $route) {
            if (str_starts_with($route['name'], 'cup:') && !in_array($route['name'], self::CUP_PUBLIC, true)) {
                $names[$route['name']] = $route['name'];
            }
        }

        return array_values($names);
    }

    /**
     * The cookie of an administrator: a group that may use every route of the panel
     *
     * @return array{Cookie: string}
     */
    protected function admin(): array
    {
        if ($this->cupCookie === null) {
            $group = $this->getService(GroupService::class)->create(['title' => 'Administrators', 'access' => $this->cupRouteNames()]);
            $user = $this->makeUser(['username' => 'admin', 'email' => 'admin@example.com', 'group_uuid' => $group->uuid]);

            $this->cupCookie = $this->cookie($this->loginAs($user)['access_token']);

            // the token is read where a browser reads it: from the page of the panel
            preg_match('/<meta name="csrf-token" content="([0-9a-f]{64})"/', (string) $this->http('GET', '/cup', ['headers' => $this->cupCookie])->getBody(), $m);
            $this->cupToken = $m[1] ?? '';
        }

        return $this->cupCookie;
    }

    protected function cupToken(): string
    {
        $this->admin();

        return $this->cupToken;
    }

    /**
     * A request made by the administrator
     */
    protected function cup(string $method, string $path, array $options = []): ResponseInterface
    {
        $options['headers'] = ($options['headers'] ?? []) + $this->admin();

        // what the scripts of the panel do: every change carries the token, unless a test wants to see it refused
        $withToken = $options['csrf'] ?? true;
        unset($options['csrf']);
        if ($withToken && !in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
            $options['headers'] += ['X-CSRF-Token' => $this->cupToken];
        }

        return $this->http($method, $path, $options);
    }

    protected function assertRenders(ResponseInterface $response, string $what = ''): void
    {
        $this->assertEquals(200, $response->getStatusCode(), $what . ' ' . $this->why($response));
        $this->assertStringNotContainsString('Slim Application Error', (string) $response->getBody(), $what);
        $this->assertStringNotContainsString('<b>Warning</b>', (string) $response->getBody(), $what);
        $this->assertStringNotContainsString('<b>Notice</b>', (string) $response->getBody(), $what);
    }

    protected function assertRedirectsTo(ResponseInterface $response, string $location, string $what = ''): void
    {
        $this->assertContains($response->getStatusCode(), [301, 302, 303, 307, 308], $what . ' ' . $this->why($response));
        $this->assertSame($location, $response->getHeaderLine('Location'), $what);
    }

    /**
     * One record of everything the panel can open, by the name the routes use for it
     *
     * @return array<string, string>
     */
    protected function fixtures(): array
    {
        if ($this->cupFixtures !== null) {
            return $this->cupFixtures;
        }

        $f = [];

        $f['apikey'] = $this->getService(ApiKeyService::class)->create(['title' => 'Partner', 'is_full_access' => true])->uuid;
        $f['group'] = $this->getService(GroupService::class)->create(['title' => 'Staff', 'access' => []])->uuid;
        $f['subscriber'] = $this->getService(SubscriberService::class)->create(['email' => 'reader@example.com'])->uuid;
        $f['user'] = $this->makeUser(['username' => 'customer', 'email' => 'customer@example.com'])->uuid;

        $f['page'] = $this->getService(PageService::class)->create(['title' => 'About', 'address' => 'about', 'content' => '<p>x</p>'])->uuid;

        $f['publication_category'] = ($category = $this->getService(PublicationCategoryService::class)->create(['title' => 'Blog', 'address' => 'blog']))->uuid;
        $f['publication'] = $this->getService(PublicationService::class)->create([
            'title' => 'Hello', 'address' => 'blog/hello', 'category_uuid' => $category->uuid, 'content' => ['short' => 'a', 'full' => 'b'],
        ])->uuid;

        $f['guestbook'] = $this->getService(GuestBookService::class)->create(['name' => 'Ann', 'email' => 'ann@example.com', 'message' => 'Hi'])->uuid;

        $f['form'] = ($form = $this->getService(FormService::class)->create(['title' => 'Contact', 'address' => 'contact', 'mailto' => 'boss@example.com']))->uuid;
        $f['form_data'] = $this->getService(FormDataService::class)->create(['form_uuid' => $form->uuid, 'data' => ['email' => 'v@example.com'], 'message' => 'hello'])->uuid;

        $f['catalog_category'] = ($catalogCategory = $this->getService(CatalogCategoryService::class)->create(['title' => 'Phones', 'address' => 'phones']))->uuid;
        $f['product'] = ($product = $this->getService(ProductService::class)->create([
            'title' => 'Phone', 'address' => 'phones/phone', 'category_uuid' => $catalogCategory->uuid, 'type' => 'product', 'price' => 10,
        ]))->uuid;
        $f['attribute'] = $this->getService(AttributeService::class)->create(['title' => 'Color', 'address' => 'color', 'type' => 'string'])->uuid;
        $f['order'] = $this->getService(OrderService::class)->create([
            'email' => 'buyer@example.com', 'phone' => '+380501234567', 'delivery' => ['address' => 'x'],
            'products' => [$product->uuid => ['count' => 1, 'price_type' => 'price']],
        ])->uuid;

        $reviews = $this->getService(ReviewService::class);
        $f['review_publication'] = $reviews->create(['type' => 'review', 'entity_type' => 'publication', 'entity_uuid' => $f['publication'], 'message' => 'Good', 'rating' => 5])->uuid;
        $f['review_catalog'] = $reviews->create(['type' => 'review', 'entity_type' => 'catalog_product', 'entity_uuid' => $f['product'], 'message' => 'Good', 'rating' => 5])->uuid;
        $f['question_catalog'] = $reviews->create(['type' => 'question', 'entity_type' => 'catalog_product', 'entity_uuid' => $f['product'], 'message' => 'How?'])->uuid;

        $f['reference'] = $this->getService(ReferenceService::class)->create(['title' => 'USD', 'type' => 'currency', 'value' => ['code' => 'USD']])->uuid;
        $f['document'] = Reference::where('type', 'document')->value('uuid') ?? $this->getService(ReferenceService::class)->create([
            'title' => 'Invoice', 'type' => 'document', 'value' => ['template' => '<p>{{ order.serial }}</p>', 'target' => 'order'],
        ])->uuid;

        $path = CACHE_DIR . '/' . md5(uniqid('', true)) . '.txt';
        file_put_contents($path, 'words ' . uniqid());
        $this->cupFiles[] = $file = $this->getService(FileService::class)->createFromPath($path, 'notes.txt');
        $f['file'] = $file->uuid;

        return $this->cupFixtures = $f;
    }

    /**
     * What each placeholder of a route stands for
     *
     * @return array<string, string>
     */
    protected function bindings(string $name): array
    {
        $f = $this->fixtures();

        return match (true) {
            str_starts_with($name, 'cup:api-key') => ['uuid' => $f['apikey']],
            str_starts_with($name, 'cup:user:group') => ['uuid' => $f['group']],
            str_starts_with($name, 'cup:user:subscriber') => ['uuid' => $f['subscriber']],
            str_starts_with($name, 'cup:user:') => ['uuid' => $f['user']],
            str_starts_with($name, 'cup:page') => ['uuid' => $f['page']],
            str_starts_with($name, 'cup:publication:category') => ['uuid' => $f['publication_category']],
            str_starts_with($name, 'cup:publication:review') => ['uuid' => $f['review_publication']],
            str_starts_with($name, 'cup:publication') => ['uuid' => $f['publication']],
            str_starts_with($name, 'cup:form:view') => ['uuid' => $f['form'], 'data' => $f['form_data']],
            str_starts_with($name, 'cup:form') => ['uuid' => $f['form']],
            str_starts_with($name, 'cup:catalog:category') => ['category' => $f['catalog_category']],
            str_starts_with($name, 'cup:catalog:product') => ['product' => $f['product']],
            str_starts_with($name, 'cup:catalog:attribute') => ['attribute' => $f['attribute']],
            str_starts_with($name, 'cup:catalog:order') => ['order' => $f['order'], 'uuid' => $f['document']],
            str_starts_with($name, 'cup:catalog:review') => ['uuid' => $f['review_catalog']],
            str_starts_with($name, 'cup:catalog:question') => ['uuid' => $f['question_catalog']],
            str_starts_with($name, 'cup:guestbook') => ['uuid' => $f['guestbook']],
            str_starts_with($name, 'cup:file') => ['uuid' => $f['file']],
            str_starts_with($name, 'cup:reference') => ['entity' => 'currencies', 'uuid' => $f['reference']],
            str_starts_with($name, 'cup:api:v1') => ['args' => 'page'],
            default => [],
        };
    }

    /**
     * The address of a route with real records in place of its placeholders
     * (optional parts, like the file of the editor, are left out)
     */
    protected function addressOf(array $route): string
    {
        $bindings = $this->bindings($route['name']);
        $path = preg_replace('/\[[^\[\]]*\]/', '', $route['pattern']);

        return preg_replace_callback('/\{(\w+)(?::[^}]*)?\}/', static fn (array $m): string => $bindings[$m[1]] ?? 'x', $path);
    }
}
