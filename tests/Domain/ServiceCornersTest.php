<?php declare(strict_types=1);

namespace tests\Domain;

use App\Application\Middlewares\MultipartParserMiddleware;
use App\Application\Middlewares\PluginMiddleware;
use App\Domain\Models\User;
use App\Domain\Models\UserToken;
use App\Domain\Plugin\AbstractLegacyPlugin;
use App\Domain\Service\File\Exception\FileNotFoundException;
use App\Domain\Service\File\FileService;
use App\Domain\Service\Parameter\ParameterService;
use App\Domain\Service\User\Exception\EmailAlreadyExistsException;
use App\Domain\Service\User\Exception\EmailBannedException;
use App\Domain\Service\User\Exception\TokenNotFoundException;
use App\Domain\Service\User\GroupService;
use App\Domain\Service\User\SubscriberService;
use App\Domain\Service\User\TokenService;
use App\Domain\Service\User\UserService;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Response;
use Slim\Routing\RouteContext;
use tests\TestCase;

/**
 * The services of users and files, and the middlewares around them, in the corners the scenarios do not reach
 *
 * @internal
 *
 * #[CoversNothing]
 */
class ServiceCornersTest extends TestCase
{
    private array $made = [];

    public function tearDown(): void
    {
        foreach ($this->made as $path) {
            is_dir($path) ? @rmdir($path) : @unlink($path);
        }
        $this->made = [];

        parent::tearDown();
    }

    private function setting(string $name, string $value): void
    {
        $this->getService(ParameterService::class)->create(['name' => $name, 'value' => $value]);
        $this->getService(\Illuminate\Cache\ArrayStore::class)->flush();
    }

    private function user(array $extra = []): User
    {
        static $n = 0;
        ++$n;

        return $this->getService(UserService::class)->create($extra + ['username' => 'user' . $n, 'email' => "user{$n}@example.com", 'password' => 'a long password']);
    }

    // ---------------------------------------------------------------- the users

    public function testUsersAreFoundByAnyOfTheirFields(): void
    {
        $service = $this->getService(UserService::class);
        $ann = $this->user(['username' => 'ann', 'phone' => '+380501111111', 'country' => 'UA', 'city' => 'Kyiv', 'postcode' => '01001', 'additional' => 'VIP', 'external_id' => 'EXT-1', 'firstname' => 'Ann', 'lastname' => 'Lee']);
        $this->user(['username' => 'bob', 'phone' => '+380502222222', 'country' => 'PL', 'city' => 'Warsaw']);

        foreach (['phone' => '+380501111111', 'country' => 'UA', 'city' => 'Kyiv', 'postcode' => '01001', 'additional' => 'VIP', 'external_id' => 'EXT-1', 'firstname' => 'Ann', 'lastname' => 'Lee'] as $field => $value) {
            $found = $service->read([$field => [$value]]);

            $this->assertSame([$ann->uuid], $found->pluck('uuid')->all(), $field);
        }
    }

    public function testASingleUserIsFoundByTheCaseInsensitiveValueOfOneField(): void
    {
        $service = $this->getService(UserService::class);
        $ann = $this->user(['username' => 'ann', 'phone' => '+380501111111', 'external_id' => 'Ext-1']);

        $this->assertSame($ann->uuid, $service->read(['phone' => '+380501111111'])->uuid);
        $this->assertSame($ann->uuid, $service->read(['external_id' => 'EXT-1'])->uuid);
        $this->assertSame($ann->uuid, $service->read(['username' => 'ANN'])->uuid);
        $this->assertSame($ann->uuid, $service->read(['identifier' => '+380501111111'])->uuid);
    }

    public function testAnAddressOnTheListOfBannedOnesCannotBeTaken(): void
    {
        $this->setting('user_email_list', "spam.example\n\nbad.example");
        $user = $this->user();

        $this->expectException(EmailBannedException::class);
        $this->getService(UserService::class)->update($user, ['email' => 'someone@spam.example']);
    }

    public function testOnlyTheAddressesOfAWhiteListAreLetIn(): void
    {
        $this->setting('user_email_list', 'good.example');
        $this->setting('user_email_list_mode', 'whitelist');
        $service = $this->getService(UserService::class);
        $user = $this->user(['email' => 'first@good.example']);

        $this->assertSame('second@good.example', $service->update($user, ['email' => 'second@good.example'])->email);
        $this->expectException(EmailBannedException::class);
        $service->update($user, ['email' => 'third@other.example']);
    }

    public function testAUserCanBeUpdatedAndFoundByTheUuidAsAString(): void
    {
        $service = $this->getService(UserService::class);
        $user = $this->user();

        $this->assertSame('Ann', $service->update($user->uuid, ['firstname' => 'Ann'])->firstname);
        $service->delete($user->uuid);
        $this->assertSame(\App\Domain\Casts\User\Status::DELETE, User::find($user->uuid)->status, 'a user is marked as removed, not erased');
    }

    // -------------------------------------------------------------- subscribers

    public function testASubscriberIsFoundByDateAndKeepsHisAddressUnique(): void
    {
        $service = $this->getService(SubscriberService::class);
        $first = $service->create(['email' => 'one@example.com']);
        $second = $service->create(['email' => 'two@example.com']);

        $this->assertSame('three@example.com', $service->update($first->uuid, ['email' => 'three@example.com'])->email, 'by the uuid as a string');
        $this->assertGreaterThanOrEqual(1, $service->read(['date' => [$second->date]])->count());

        $this->expectException(EmailAlreadyExistsException::class);
        $service->update($second, ['email' => 'three@example.com']);
    }

    public function testAGroupIsUpdatedAndDeletedByTheUuidAsAString(): void
    {
        $service = $this->getService(GroupService::class);
        $group = $service->create(['title' => 'Staff', 'access' => []]);

        $this->assertSame('Employees', $service->update($group->uuid, ['title' => 'Employees'])->title);
        $this->assertTrue($service->delete($group->uuid));
    }

    // ------------------------------------------------------------------- tokens

    public function testATokenIsMadeFoundChangedAndDeleted(): void
    {
        $service = $this->getService(TokenService::class);
        $user = $this->user();

        $token = $service->create(['user_uuid' => $user->uuid, 'unique' => 'abc', 'agent' => 'Firefox', 'ip' => '10.0.0.1']);

        $this->assertSame($token->uuid, $service->read(['uuid' => $token->uuid])->uuid);
        $this->assertSame([$token->uuid], $service->read(['user_uuid' => [$user->uuid]])->pluck('uuid')->all());
        $this->assertSame([$token->uuid], $service->read(['ip' => ['10.0.0.1']])->pluck('uuid')->all());
        $this->assertSame('Chrome', $service->update($token->uuid, ['agent' => 'Chrome'])->agent, 'by the uuid as a string');
        $this->assertTrue($service->delete($token->uuid));
        $this->assertSame(0, UserToken::count());
    }

    public function testATokenWithoutAUserIsNotMade(): void
    {
        $this->expectException(\RuntimeException::class);

        $this->getService(TokenService::class)->create(['unique' => 'abc']);
    }

    public function testAMissingTokenIsReported(): void
    {
        $service = $this->getService(TokenService::class);
        $gone = \Ramsey\Uuid\Uuid::uuid4()->toString();

        foreach ([fn () => $service->update(new \stdClass(), []), fn () => $service->delete(new \stdClass())] as $call) {
            try {
                $call();
                $this->fail('a thing that is not a token is not changed');
            } catch (TokenNotFoundException) {
                $this->addToAssertionCount(1);
            }
        }
        $this->expectException(TokenNotFoundException::class);
        $service->read(['uuid' => $gone]);
    }

    // -------------------------------------------------------------------- files

    private function stored(string $content = 'words'): \App\Domain\Models\File
    {
        $path = CACHE_DIR . '/' . md5(uniqid('', true)) . '.txt';
        file_put_contents($path, $content . uniqid());
        $this->made[] = $path;
        $file = $this->getService(FileService::class)->createFromPath($path, 'notes.txt');
        $this->made[] = $file->internal_path();

        return $file;
    }

    public function testFilesAreFoundByNameTypeAndSize(): void
    {
        $service = $this->getService(FileService::class);
        $file = $this->stored();

        $this->assertSame([$file->uuid], $service->read(['type' => [$file->type]])->pluck('uuid')->all());
        $this->assertSame([$file->uuid], $service->read(['size' => [$file->size]])->pluck('uuid')->all());
        $this->assertSame($file->uuid, $service->read(['hash' => $file->hash])->uuid);
    }

    public function testAFileIsUpdatedAndDeletedByTheUuidAsAString(): void
    {
        $service = $this->getService(FileService::class);
        $file = $this->stored();

        $this->assertSame('renamed', $service->update($file->uuid, ['name' => 'renamed'])->name);
        $this->assertTrue($service->delete($file->uuid));
        $this->expectException(FileNotFoundException::class);
        $service->read(['uuid' => $file->uuid]);
    }

    public function testTheSameFileTwiceIsOneFile(): void
    {
        $service = $this->getService(FileService::class);
        $paths = [CACHE_DIR . '/' . md5(uniqid('a', true)) . '.txt', CACHE_DIR . '/' . md5(uniqid('b', true)) . '.txt'];
        foreach ($paths as $path) {
            file_put_contents($path, 'the same words');
            $this->made[] = $path;
        }

        $first = $service->createFromPath($paths[0], 'a.txt');
        $second = $service->createFromPath($paths[1], 'b.txt');
        $this->made[] = $first->internal_path();

        $this->assertSame($first->uuid, $second->uuid, 'one row, the copy of the folder is dropped');
        $this->assertDirectoryDoesNotExist(UPLOAD_DIR . '/__nothing__');
    }

    // -------------------------------------------------------------- remote files

    /**
     * A web server of a few lines, with the kinds of answers a remote file can give
     *
     * @return array{0: resource, 1: int, 2: string}
     */
    private function remote(): array
    {
        $dir = sys_get_temp_dir() . '/remote-' . bin2hex(random_bytes(4));
        mkdir($dir);
        file_put_contents($dir . '/router.php', <<<'PHP'
            <?php
            $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
            switch ($path) {
                case '/relative':
                    header('Location: /final.txt', true, 302);
                    break;
                case '/protocol':
                    header('Location: //' . $_SERVER['HTTP_HOST'] . '/final.txt', true, 301);
                    break;
                case '/loop':
                    header('Location: /loop', true, 302);
                    break;
                case '/nowhere':
                    header('Location: ', true, 302);
                    break;
                case '/final.txt':
                    header('Content-Type: text/plain');
                    echo 'remote words';
                    break;
                default:
                    http_response_code(404);
            }
            PHP);

        $port = random_int(20000, 40000);
        $process = proc_open(['php', '-S', "127.0.0.1:{$port}", $dir . '/router.php'], [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes);
        for ($i = 0; $i < 50 && !@fsockopen('127.0.0.1', $port); ++$i) {
            usleep(100000);
        }

        return [$process, $port, $dir];
    }

    public function testARemoteFileIsFollowedThroughRedirectsAndNoFurther(): void
    {
        [$process, $port, $dir] = $this->remote();

        try {
            foreach (['/final.txt', '/relative', '/protocol'] as $path) {
                $saved = FileService::getFileFromRemote("http://127.0.0.1:{$port}{$path}");

                $this->assertIsString($saved, $path);
                $this->assertSame('remote words', file_get_contents($saved), $path);
                $this->made[] = $saved;
            }

            $this->assertFalse(FileService::getFileFromRemote("http://127.0.0.1:{$port}/loop"), 'a redirect to itself ends');
            $this->assertFalse(FileService::getFileFromRemote("http://127.0.0.1:{$port}/nowhere"), 'a redirect to nowhere');
            $this->assertFalse(FileService::getFileFromRemote("http://127.0.0.1:{$port}/missing.txt"), 'not found');
            $this->assertFalse(FileService::getFileFromRemote("http://127.0.0.1:{$port}/final.txt", 0, 5), 'bigger than allowed');
        } finally {
            proc_terminate($process);
            proc_close($process);
            @unlink($dir . '/router.php');
            @rmdir($dir);
        }
    }

    // ------------------------------------------------------------- middlewares

    private function handler(?\Closure $do = null): RequestHandlerInterface
    {
        return new class($do) implements RequestHandlerInterface {
            public ?ServerRequestInterface $seen = null;

            public function __construct(private ?\Closure $do) {}

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->seen = $request;

                return $this->do ? ($this->do)($request) : new Response();
            }
        };
    }

    private function multipart(string $boundary, array $parts): string
    {
        $body = '';
        foreach ($parts as $headers => $content) {
            $body .= "--{$boundary}\r\n{$headers}\r\n\r\n{$content}\r\n";
        }

        return $body . "--{$boundary}--\r\n";
    }

    public function testAPutWithFormDataIsReadLikeAPost(): void
    {
        $middleware = new MultipartParserMiddleware($this->getService(ContainerInterface::class));
        $body = $this->multipart('XYZ', [
            'Content-Disposition: form-data; name="title"' => 'Hello',
            'Content-Disposition: form-data; name="file"; filename="a.txt"' . "\r\n" . 'Content-Type: text/plain' => 'the words',
            'Content-Disposition: form-data; name="empty"; filename=""' => '',
            'Content-Type: text/plain' => 'a part with no disposition',
            'Content-Disposition: form-data' => 'a part with no name',
        ]);
        $request = (new ServerRequestFactory())->createServerRequest('PUT', '/')->withHeader('Content-Type', 'multipart/form-data; boundary=XYZ');
        $request->getBody()->write($body);
        $handler = $this->handler();

        $middleware($request, $handler);

        $this->assertSame('Hello', $handler->seen->getParsedBody()['title']);
        $file = $handler->seen->getUploadedFiles()['file'];
        $this->assertSame('a.txt', $file->getClientFilename());
        $this->assertSame('text/plain', $file->getClientMediaType());
        $this->assertSame('the words', (string) $file->getStream());
        $this->assertArrayNotHasKey('empty', $handler->seen->getUploadedFiles(), 'a field without a file is a field');
    }

    public function testADataFormWithoutABoundaryOrOfAnotherMethodIsLeftAlone(): void
    {
        $middleware = new MultipartParserMiddleware($this->getService(ContainerInterface::class));
        $factory = new ServerRequestFactory();

        $noBoundary = $factory->createServerRequest('PATCH', '/')->withHeader('Content-Type', 'multipart/form-data');
        $handler = $this->handler();
        $middleware($noBoundary, $handler);
        $this->assertNull($handler->seen->getParsedBody());

        $post = $factory->createServerRequest('POST', '/')->withHeader('Content-Type', 'multipart/form-data; boundary=XYZ');
        $handler = $this->handler();
        $middleware($post, $handler);
        $this->assertSame($post, $handler->seen);
    }

    /**
     * A plugin of the old kind: it is told before and after the routes it handles
     */
    private function legacyPlugin(array &$log): AbstractLegacyPlugin
    {
        $container = $this->getService(ContainerInterface::class);

        return new class($container, $log) extends AbstractLegacyPlugin {
            public const NAME = 'ZzLegacyPlugin';
            public const TITLE = 'Legacy';
            public const AUTHOR = 'Tester';

            public function __construct(ContainerInterface $container, private array &$log)
            {
                parent::__construct($container);
                $this->setHandledRoute('common:page');
            }

            public function before(\Slim\Psr7\Request $request, string $routeName): void
            {
                $this->log[] = 'before ' . $routeName;
            }

            public function after(\Slim\Psr7\Request $request, Response $response, string $routeName): Response
            {
                $this->log[] = 'after ' . $routeName;

                return $response->withHeader('X-Legacy', 'seen');
            }
        };
    }

    private function routed(string $name): \Slim\Psr7\Request
    {
        $route = (new \Slim\Routing\Route(['GET'], '/', fn () => null, new \Slim\Psr7\Factory\ResponseFactory(), new \Slim\CallableResolver()))->setName($name);

        return (new ServerRequestFactory())->createServerRequest('GET', '/')
            ->withAttribute(RouteContext::ROUTE, $route)
            ->withAttribute(RouteContext::ROUTE_PARSER, $this->createStub(\Slim\Interfaces\RouteParserInterface::class))
            ->withAttribute(RouteContext::ROUTING_RESULTS, new \Slim\Routing\RoutingResults($this->createStub(\Slim\Interfaces\DispatcherInterface::class), 'GET', '/', 1, '0'));
    }

    /**
     * The middleware over a container whose list of plugins is the one the test gives
     */
    private function pluginMiddleware(array $plugins): PluginMiddleware
    {
        $real = $this->getService(ContainerInterface::class);
        $registry = new class(collect($plugins)) {
            public function __construct(private \Illuminate\Support\Collection $plugins) {}

            public function get(): \Illuminate\Support\Collection
            {
                return $this->plugins;
            }
        };
        $container = new class($real, $registry) implements ContainerInterface {
            public function __construct(private ContainerInterface $real, private object $registry) {}

            public function get(string $id): mixed
            {
                return $id === 'plugin' ? $this->registry : $this->real->get($id);
            }

            public function has(string $id): bool
            {
                return $id === 'plugin' || $this->real->has($id);
            }
        };

        return new PluginMiddleware($container);
    }

    public function testAPluginOfTheOldKindIsToldAboutTheRoutesItHandles(): void
    {
        $log = [];
        $middleware = $this->pluginMiddleware(['ZzLegacy' => $this->legacyPlugin($log)]);

        $handler = $this->handler();
        $response = $middleware($this->routed('common:page'), $handler);

        $this->assertSame(['before common:page', 'after common:page'], $log);
        $this->assertSame('seen', $response->getHeaderLine('X-Legacy'), 'what the plugin returns is the answer');

        $log = [];
        $response = $middleware($this->routed('common:catalog'), $this->handler());
        $this->assertSame([], $log, 'another route is not its business');
        $this->assertFalse($response->hasHeader('X-Legacy'));
    }

    public function testWithoutPluginsOfTheOldKindTheRequestGoesStraightOn(): void
    {
        $handler = $this->handler();

        $this->pluginMiddleware([])($this->routed('common:page'), $handler);

        $this->assertNotNull($handler->seen);
    }
}
