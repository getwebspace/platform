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
 * Shared plumbing of the MCP endpoint tests: JSON-RPC calls, fixtures, clean-up
 *
 * @internal
 */
abstract class McpTestCase extends TestCase
{
    protected const URL = '/api/v1/mcp';

    public function setUp(): void
    {
        parent::setUp();

        $this->getService(ParameterService::class)->create(['name' => 'entity_access', 'value' => 'key']);
    }

    /**
     * Files made by the tests in the theme folder; they and their saved versions are removed in tearDown()
     *
     * @var string[]
     */
    protected array $themeFixtures = [];

    /**
     * Names of the files this test put in the public folder to be downloaded by an address
     *
     * @var string[]
     */
    private array $servedFiles = [];

    public function tearDown(): void
    {
        foreach ($this->servedFiles as $file) {
            @unlink(PUBLIC_DIR . '/' . $file);
        }

        $this->servedFiles = [];

        foreach ($this->themeFixtures as $file) {
            @unlink($file);
            foreach (glob(VAR_DIR . '/theme-backups/theme__' . basename($file) . '/*') ?: [] as $backup) {
                @unlink($backup);
            }
            @rmdir(VAR_DIR . '/theme-backups/theme__' . basename($file));
        }

        $this->themeFixtures = [];

        parent::tearDown();
    }

    protected function rpc(?string $token, mixed $body): \Psr\Http\Message\ResponseInterface
    {
        return $this->createRequest()->post(self::URL, [
            'headers' => array_filter([
                'Content-Type' => 'application/json',
                'Authorization' => $token ? 'Bearer ' . $token : null,
            ]),
            'body' => is_string($body) ? $body : json_encode($body),
        ]);
    }

    protected function call(?string $token, string $method, array $params = []): array
    {
        $response = $this->rpc($token, ['jsonrpc' => '2.0', 'id' => 1, 'method' => $method, 'params' => $params ?: new \stdClass()]);
        $this->assertEquals(200, $response->getStatusCode());

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * @return array{0: bool, 1: mixed} [isError, decoded result or error text]
     */
    protected function tool(?string $token, string $name, array $args = []): array
    {
        $result = $this->call($token, 'tools/call', ['name' => $name, 'arguments' => $args ?: new \stdClass()]);
        $this->assertArrayHasKey('result', $result, json_encode($result));

        $text = $result['result']['content'][0]['text'];

        return [$result['result']['isError'], json_decode($text, true) ?? $text];
    }

    protected function tools(?string $token): array
    {
        return array_column($this->call($token, 'tools/list')['result']['tools'], 'name');
    }

    // protocol

    protected function makeProduct(string $title, string $categoryUuid, array $extra = []): object
    {
        return $this->getService(CatalogProductService::class)->create(array_merge([
            'title' => $title,
            'address' => 'p-' . uniqid(),
            'category_uuid' => $categoryUuid,
            'price' => 100,
            'stock' => 5,
        ], $extra));
    }

    protected function prompts(?string $token): array
    {
        return array_column($this->call($token, 'prompts/list')['result']['prompts'], null, 'name');
    }

    protected function resources(?string $token): array
    {
        return array_column($this->call($token, 'resources/list')['result']['resources'], null, 'uri');
    }

    protected function ordersWithMoney(): array
    {
        $category = $this->getService(CatalogCategoryService::class)->create(['title' => 'Одежда', 'address' => 'wear']);
        $other = $this->getService(CatalogCategoryService::class)->create(['title' => 'Обувь', 'address' => 'shoes']);

        $jacket = $this->makeProduct('Куртка', (string) $category->uuid, ['price' => 1000]);
        $boots = $this->makeProduct('Ботинки', (string) $other->uuid, ['price' => 500, 'tax' => 10, 'discount' => 50]);

        $references = $this->getService(ReferenceService::class);
        $new = $references->create(['type' => ReferenceType::ORDER_STATUS, 'title' => 'Новый', 'order' => 1]);
        $done = $references->create(['type' => ReferenceType::ORDER_STATUS, 'title' => 'Выполнен', 'order' => 2]);

        $orders = $this->getService(CatalogOrderService::class);
        $created = [
            $orders->create(['phone' => '+79000000001', 'status_uuid' => $new->uuid, 'products' => [(string) $jacket->uuid => ['count' => 2, 'price_type' => 'price']]]),
            $orders->create(['phone' => '+79000000002', 'status_uuid' => $done->uuid, 'products' => [(string) $jacket->uuid => ['count' => 1, 'price_type' => 'price'], (string) $boots->uuid => ['count' => 3, 'price_type' => 'price']]]),
            $orders->create(['phone' => '+79000000003', 'status_uuid' => $done->uuid, 'products' => [(string) $boots->uuid => ['count' => 1, 'price_type' => 'price']]]),
        ];

        // the serial is built from today, so the dates are set when all orders exist
        foreach ([[0, '2026-03-02 10:00:00'], [1, '2026-03-02 18:00:00'], [2, '2026-04-15 12:00:00']] as [$i, $date]) {
            \App\Domain\Models\CatalogOrder::query()->where('uuid', $created[$i]->uuid)->update(['date' => $date]);
        }

        return [$created, $jacket, $boots];
    }

    /**
     * The search index lives in files of the cache folder, a developer's machine has them and CI has not:
     * the existing ones are put aside for the time of a test and put back (the ones the test made are removed)
     */
    protected function withSearchIndexAside(callable $test): void
    {
        $aside = [];

        foreach (glob(CACHE_DIR . '/*.index') ?: [] as $file) {
            rename($file, $file . '.mcp-aside');
            $aside[] = $file;
        }

        try {
            $test();
        } finally {
            foreach (glob(CACHE_DIR . '/*.index') ?: [] as $file) {
                @unlink($file);
            }
            foreach ($aside as $file) {
                rename($file . '.mcp-aside', $file);
            }
        }
    }

    protected function themeFile(string $name, string $content = '<p>start</p>'): string
    {
        $path = THEME_DIR . '/default/' . $name;

        file_put_contents($path, $content);
        // written by the web server, which is another user than the tests
        chmod($path, 0o666);
        $this->themeFixtures[] = $path;

        return 'theme/' . $name;
    }

    protected function allowTemplateEditing(bool $on = true): void
    {
        $this->getService(ParameterService::class)->create(['name' => 'mcp_theme_edit', 'value' => $on ? 'yes' : 'no']);
    }

    protected function db(): \Illuminate\Database\Connection
    {
        return $this->getService(\Illuminate\Database\Connection::class);
    }

    protected function makeAttribute(string $title, string $address, string $type = 'string', bool $filter = true): object
    {
        return $this->getService(AttributeService::class)->create(['title' => $title, 'address' => $address, 'type' => $type, 'is_filter' => $filter]);
    }

    /**
     * A picture that is not like any other: the same file is stored once, tests need different ones
     */
    protected function picture(string $type = 'png'): string
    {
        $image = imagecreatetruecolor(24, 16);
        imagefill($image, 0, 0, imagecolorallocate($image, random_int(0, 255), random_int(0, 255), random_int(0, 255)));
        imagestring($image, 2, 1, 1, uniqid(), imagecolorallocate($image, 255, 255, 255));

        ob_start();
        $type === 'jpg' ? imagejpeg($image) : imagepng($image);

        return (string) ob_get_clean();
    }

    /**
     * Puts a file in the public folder: the application downloads it from itself
     */
    protected function serve(string $content, string $name): string
    {
        $this->servedFiles[] = $name;
        file_put_contents(PUBLIC_DIR . '/' . $name, $content);

        return 'http://127.0.0.1:80/' . $name;
    }

    protected function fileKey(): string
    {
        return $this->createApiKeyToken();
    }

    protected function urlToPath(string $url): string
    {
        return UPLOAD_DIR . '/' . explode('/', (string) parse_url($url, PHP_URL_PATH))[2] . '/' . basename($url);
    }
}
