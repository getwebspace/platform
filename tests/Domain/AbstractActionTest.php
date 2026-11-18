<?php declare(strict_types=1);

namespace tests\Domain;

use App\Domain\AbstractAction;
use App\Domain\Exceptions\HttpBadRequestException;
use App\Domain\Exceptions\HttpForbiddenException;
use App\Domain\Exceptions\HttpMethodNotAllowedException;
use App\Domain\Exceptions\HttpNotFoundException;
use App\Domain\Exceptions\HttpNotImplementedException;
use App\Domain\Exceptions\HttpRedirectException;
use App\Domain\Models\File;
use App\Domain\Models\Page;
use App\Domain\Models\Task;
use App\Domain\Service\Page\PageService;
use App\Domain\Service\Parameter\ParameterService;
use Psr\Container\ContainerInterface;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Response;
use Slim\Psr7\UploadedFile;
use tests\TestCase;

/**
 * What every action of the application is built on: how it reads a request, answers, and turns an exception into an answer
 *
 * @internal
 *
 * #[CoversNothing]
 */
class AbstractActionTest extends TestCase
{
    private array $made = [];

    public function tearDown(): void
    {
        foreach ($this->made as $file) {
            @unlink($file);
        }
        $this->made = [];

        parent::tearDown();
    }

    /**
     * An action that does what the test says
     */
    private function action(?\Closure $do = null, ?\Slim\Interfaces\RouteCollectorInterface $routes = null): object
    {
        $container = $this->getService(ContainerInterface::class);

        return new class($container, $do, $routes) extends AbstractAction {
            public function __construct(ContainerInterface $container, private ?\Closure $do, ?\Slim\Interfaces\RouteCollectorInterface $routes)
            {
                parent::__construct($container);
                if ($routes !== null) {
                    $this->routeCollector = $routes;
                }
            }

            protected function action(): \Slim\Psr7\Response
            {
                return $this->do ? ($this->do)($this) : $this->respondWithText('done');
            }

            public function with(\Slim\Psr7\Request $request, array $args = []): static
            {
                $this->request = $request;
                $this->response = new Response();
                $this->args = $args;

                return $this;
            }

            public function __call(string $method, array $args): mixed
            {
                return $this->{$method}(...$args);
            }
        };
    }

    private function request(string $method = 'GET', string $uri = '/', array $server = [], array $headers = []): \Slim\Psr7\Request
    {
        $request = (new ServerRequestFactory())->createServerRequest($method, $uri, $server);
        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        return $request;
    }

    private function setting(string $name, string $value): void
    {
        $this->getService(ParameterService::class)->create(['name' => $name, 'value' => $value]);
        $this->getService(\Illuminate\Cache\ArrayStore::class)->flush();
    }

    // --------------------------------------------------------------- the request

    public function testTheParametersOfARequestAreReadFromWhereTheyAre(): void
    {
        $request = $this->request('POST', '/?q=query&both=from-query', ['REMOTE_ADDR' => '9.9.9.9'])
            ->withParsedBody(['b' => 'body', 'both' => 'from-body'])
            ->withCookieParams(['c' => 'cookie']);
        $action = $this->action()->with($request);

        $this->assertTrue($action->isPost());
        $this->assertFalse($action->isGet());
        $this->assertSame('query', $action->getQueryParam('q'));
        $this->assertSame('body', $action->getBodyParam('b'));
        $this->assertSame('9.9.9.9', $action->getServerParam('REMOTE_ADDR'));
        $this->assertSame('cookie', $action->getCookie('c'));
        $this->assertSame('default', $action->getCookie('nothing', 'default'));
        $this->assertSame('from-body', $action->getParam('both'), 'the body is stronger than the address');
        $this->assertSame('body', $action->getParam('b'));
        $this->assertSame('none', $action->getBodyParam('q', 'none'));
        $this->assertSame('none', $this->action()->with($this->request())->getBodyParam('b', 'none'), 'a request without a body');
    }

    public function testTheAddressOfTheCallerIsTheMostTrustedOne(): void
    {
        $this->assertSame('1.1.1.1', $this->action()->with($this->request('GET', '/', ['HTTP_X_REAL_IP' => '1.1.1.1', 'HTTP_X_FORWARDED_FOR' => '2.2.2.2', 'REMOTE_ADDR' => '3.3.3.3']))->getRequestRemoteIP());
        $this->assertSame('2.2.2.2', $this->action()->with($this->request('GET', '/', ['HTTP_X_FORWARDED_FOR' => '2.2.2.2', 'REMOTE_ADDR' => '3.3.3.3']))->getRequestRemoteIP());
        $this->assertSame('', $this->action()->with($this->request())->getRequestRemoteIP());
    }

    public function testAnArgumentOfTheRouteIsRequiredToBeThere(): void
    {
        $action = $this->action()->with($this->request(), ['uuid' => 'abc']);

        $this->assertSame('abc', $action->resolveArg('uuid'));
        $this->expectException(HttpBadRequestException::class);
        $action->resolveArg('missing');
    }

    public function testTheRoutesOfThePanelAreListedWithoutThePublicOnes(): void
    {
        $routes = new \Slim\Routing\RouteCollector(new \Slim\Psr7\Factory\ResponseFactory(), new \Slim\CallableResolver($this->getService(ContainerInterface::class)), $this->getService(ContainerInterface::class));
        foreach (['api:v1:entity', 'auth:login', 'common:forbidden', 'cup:login', 'cup:forbidden', 'cup:system', 'cup:main', 'common:page'] as $i => $name) {
            $routes->map(['GET'], '/r' . $i, fn () => null)->setName($name);
        }

        $listed = $this->action(null, $routes)->with($this->request())->getRoutes();

        $this->assertEqualsCanonicalizing(['cup:main', 'common:page'], $listed->keys()->all());
        $this->assertSame('cup:main', $listed['cup:main'], 'a name for a name, it is for a select');
    }

    // --------------------------------------------------------------- the answer

    public function testAnErrorOfTheActionIsAnAnswerWithItsOwnStatusAndKind(): void
    {
        $request = $this->request();
        $cases = [
            [new HttpNotFoundException(), 404, 'RESOURCE_NOT_FOUND'],
            [new HttpMethodNotAllowedException(), 405, 'NOT_ALLOWED'],
            [new HttpForbiddenException(), 403, 'UNAUTHENTICATED'],
            [new HttpBadRequestException('Wrong'), 400, 'BAD_REQUEST'],
            [new HttpNotImplementedException(), 501, 'NOT_IMPLEMENTED'],
        ];

        foreach ($cases as [$exception, $status, $type]) {
            $response = $this->action(fn () => throw $exception)($request, new Response(), []);

            $this->assertSame($status, $response->getStatusCode(), $type);
            $this->assertSame('application/json', $response->getHeaderLine('Content-Type'));
            $body = json_decode((string) $response->getBody(), true);
            $this->assertSame($type, $body['error']['type']);
            $this->assertSame($status, $body['code']);
        }
    }

    public function testAnErrorWithoutAStatusIsAServerError(): void
    {
        $exception = new class('No status of its own') extends \App\Domain\AbstractHttpException {
            protected $code = 0;
        };

        $response = $this->action(fn () => throw $exception)($this->request(), new Response(), []);

        $this->assertSame(500, $response->getStatusCode());
        $this->assertSame('SERVER_ERROR', json_decode((string) $response->getBody(), true)['error']['type']);
    }

    public function testARedirectThrownByTheActionIsFollowedWithItsStatus(): void
    {
        $response = $this->action(fn () => throw new HttpRedirectException('/elsewhere'))($this->request(), new Response(), []);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/elsewhere', $response->getHeaderLine('Location'));
    }

    public static function formats(): array
    {
        return [
            'an explicit format wins' => ['/?format=text', ['Accept' => 'text/html'], 'text'],
            'an address with json' => ['/?format=json', [], 'json'],
            'a browser' => ['/', ['Accept' => 'text/html,application/xhtml+xml'], 'html'],
            'a script that asks for json' => ['/', ['Accept' => 'application/json'], 'json'],
            'ajax' => ['/', ['X-Requested-With' => 'XMLHttpRequest'], 'json'],
            'plain text' => ['/', ['Accept' => 'text/plain'], 'text'],
            'nothing said' => ['/', [], 'html'],
            'a format that is not one' => ['/?format=xml', [], 'html'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('formats')]
    public function testTheFormatOfTheAnswerFollowsTheRequest(string $uri, array $headers, string $expected): void
    {
        $this->assertSame($expected, $this->action()->with($this->request('GET', $uri, [], $headers))->getResponseFormat());
    }

    public function testTheSameDataIsAnsweredAsJsonAsTextOrThroughATemplate(): void
    {
        $data = ['title' => 'Hello'];

        $json = $this->action()->with($this->request('GET', '/?format=json'))->respond('p404.twig', $data);
        $this->assertSame(['title' => 'Hello'], json_decode((string) $json->getBody(), true)['data']);
        $this->assertStringContainsString('Accept', $json->getHeaderLine('Vary'));

        $text = $this->action()->with($this->request('GET', '/?format=text'))->respond('p404.twig', $data);
        $this->assertSame('{"title":"Hello"}', (string) $text->getBody());
        $this->assertStringStartsWith('text/plain', $text->getHeaderLine('Content-Type'));

        $plain = $this->action()->with($this->request())->respondWithText('just words');
        $this->assertSame('just words', (string) $plain->getBody());
    }

    public function testATemplateThatFailsIsAnswerWithTheErrorPage(): void
    {
        $response = $this->action()->with($this->request())->respondWithTemplate('no/such/template.twig');

        $this->assertSame(400, $response->getStatusCode());
        $this->assertStringContainsString('no/such/template.twig', (string) $response->getBody());
    }

    public function testAnErrorPageThatFailsToo(): void
    {
        $this->setting('common_theme', 'no-such-theme');
        $action = $this->action()->with($this->request());
        // the error page is found in the folder of errors, nothing the theme does can take it away
        $this->assertSame(400, $action->respondWithTemplate('no/such/template.twig')->getStatusCode());
    }

    // ---------------------------------------------------------------- the files

    private function page(): Page
    {
        return $this->getService(PageService::class)->create(['title' => 'About', 'address' => 'about', 'content' => 'x']);
    }

    public function testTheCommentAndTheOrderOfTheFilesOfARecordAreSavedWithTheForm(): void
    {
        $page = $this->page();
        $first = File::create(['name' => 'a', 'ext' => 'txt', 'type' => 'text/plain', 'size' => 1, 'salt' => 'aaa111', 'hash' => sha1('a')]);
        $second = File::create(['name' => 'b', 'ext' => 'txt', 'type' => 'text/plain', 'size' => 1, 'salt' => 'bbb222', 'hash' => sha1('b')]);
        $third = File::create(['name' => 'c', 'ext' => 'txt', 'type' => 'text/plain', 'size' => 1, 'salt' => 'ccc333', 'hash' => sha1('c')]);
        $page->files()->sync([$first->uuid => [], $second->uuid => [], $third->uuid => []]);
        $request = $this->request('POST')->withParsedBody(['files' => [
            $second->uuid => ['comment' => 'The second', 'order' => 1],
            $first->uuid => ['comment' => 'The first', 'order' => 2],
            $third->uuid => ['delete' => '1'],
        ]]);

        $this->action()->with($request)->processEntityFiles(Page::find($page->uuid));

        $files = Page::find($page->uuid)->files;
        $this->assertSame(['b', 'a'], $files->pluck('name')->all());
        $this->assertSame(['The second', 'The first'], $files->map(fn (File $f) => $f->comment())->all());
        $this->assertNotNull(File::find($third->uuid), 'only taken off the record, the file is still in the library');
    }

    public function testAFilePassedWithTheFormIsKeptAndAPictureGetsItsCopiesMade(): void
    {
        $page = $this->page();
        $path = CACHE_DIR . '/' . md5(uniqid('', true)) . '.png';
        // one transparent pixel
        file_put_contents($path, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR4nGNgYAAAAAMAASsJTYQAAAAASUVORK5CYII='));
        $this->made[] = $path;
        $upload = new UploadedFile($path, 'pixel.png', 'image/png', filesize($path), UPLOAD_ERR_OK);
        $request = $this->request('POST')->withUploadedFiles(['files' => ['The caption' => $upload]]);

        $this->action()->with($request)->processEntityFiles($page);

        $files = Page::find($page->uuid)->files;
        $this->assertCount(1, $files);
        $this->assertSame('The caption', $files->first()->comment());
        $this->assertStringStartsWith('image/', $files->first()->type);
        $this->assertSame(1, Task::where('action', \App\Domain\Tasks\ConvertImageTask::class)->count());
        $this->made[] = $files->first()->internal_path();
    }

    public function testFilesAreNotTakenWhenTheyAreSwitchedOff(): void
    {
        $this->setting('file_is_enabled', 'no');
        $path = CACHE_DIR . '/' . md5(uniqid('', true)) . '.txt';
        file_put_contents($path, 'words');
        $this->made[] = $path;
        $request = $this->request('POST')->withUploadedFiles(['files' => [new UploadedFile($path, 'a.txt', 'text/plain', 5, UPLOAD_ERR_OK)]]);

        $this->assertSame([], $this->action()->with($request)->getUploadedFiles());
        $this->assertSame(0, File::count());
    }
}
