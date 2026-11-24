<?php declare(strict_types=1);

namespace tests\Domain;

use App\Domain\AbstractMcpPrompt;
use App\Domain\AbstractMcpResource;
use App\Domain\AbstractMcpTool;
use App\Domain\Exceptions\HttpBadRequestException;
use App\Domain\Models\User;
use App\Domain\Service\Parameter\ParameterService;
use App\Domain\Traits\HasMcpTools;
use App\Domain\Traits\HasParameters;
use App\Domain\Traits\HasRenderer;
use App\Domain\Traits\HasStorage;
use App\Domain\Traits\UseConfirmation;
use App\Domain\Traits\UseSecurity;
use App\Domain\Traits\UseThrottle;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\FileStore;
use Psr\Container\ContainerInterface;
use tests\TestCase;

/**
 * The small shared behaviours the actions and tasks are built from
 *
 * @internal
 *
 * #[CoversNothing]
 */
class TraitsTest extends TestCase
{
    /**
     * An object that has every trait, wired the way an action is
     */
    private function host(?string $path = null): object
    {
        $container = $this->getService(ContainerInterface::class);

        return new class($container, $path) {
            use HasMcpTools;
            use HasParameters;
            use HasRenderer;
            use HasStorage;
            use UseConfirmation;
            use UseThrottle;

            public ContainerInterface $container;

            public ?bool $debug = null;

            public ArrayStore $arrayCache;

            public FileStore $fileCache;

            public $request;

            public function __construct(ContainerInterface $container, ?string $path)
            {
                $this->container = $container;
                $this->arrayCache = $container->get(ArrayStore::class);
                $this->fileCache = $container->get(FileStore::class);
                $this->renderer = $container->get('view');
                if ($path !== null) {
                    $this->request = (new \Slim\Psr7\Factory\ServerRequestFactory())->createServerRequest('GET', $path);
                }
            }

            protected function renderDebug(): bool
            {
                return $this->debug ?? (bool) ($this->container->get('settings')['displayErrorDetails'] ?? false);
            }

            public function __call(string $name, array $args): mixed
            {
                return $this->{$name}(...$args);
            }

            public static function __callStatic(string $name, array $args): mixed
            {
                return static::{$name}(...$args);
            }

            public function token(string $sub, ?string $uuid = null, array $data = [], ?int $ttl = null): string
            {
                return $this->encodeJWT($sub, $uuid, $data, $ttl);
            }

            public function decode(string $token): array
            {
                return $this->decodeJWT($token);
            }
        };
    }

    private function setting(string $name, mixed $value): void
    {
        $this->parameters ??= $this->getService(ParameterService::class);
        $this->parameters->create(['name' => $name, 'value' => (string) $value]);
        $this->getService(ArrayStore::class)->flush();
    }

    private ?ParameterService $parameters = null;

    // -------------------------------------------------------------- parameters

    public function testAParameterIsReadByNameOrWithADefault(): void
    {
        $this->setting('common_title', 'Shop');

        $host = $this->host();

        $this->assertSame('Shop', $host->parameter('common_title'));
        $this->assertSame('none', $host->parameter('common_missing', 'none'));
        $this->assertNull($host->parameter('common_missing'));
    }

    public function testSeveralParametersAreReadAtOnce(): void
    {
        $this->setting('common_title', 'Shop');
        $this->setting('common_desc', 'Best');

        $this->assertSame(['common_title' => 'Shop', 'common_desc' => 'Best'], $this->host()->parameter(['common_title', 'common_desc', 'common_nothing']));
    }

    public function testWithoutANameAllParametersComeGroupedByTheirPrefix(): void
    {
        $this->setting('common_title', 'Shop');

        $all = $this->host()->parameter();

        $this->assertSame('Shop', $all['common[title]']->value);
    }

    public function testAParameterIsSetOrCreated(): void
    {
        $host = $this->host();

        $this->assertSame(['common_note' => 'one'], $host->parameter_set('common_note', 'one'));
        $this->assertSame('one', $this->getService(ParameterService::class)->read(['name' => 'common_note'])->value);

        $host->parameter_set('common_note', 'two');
        $this->assertSame('two', $this->getService(ParameterService::class)->read(['name' => 'common_note'])->value);
        $this->assertSame(1, $this->getService(ParameterService::class)->read()->where('name', 'common_note')->count(), 'updated, not doubled');
    }

    // ----------------------------------------------------------------- storage

    public function testTheStorageRemembersByNamespace(): void
    {
        $host = $this->host();
        $ns = 'ns' . uniqid();

        $this->assertFalse($host::hasStorage('key', $ns));
        $this->assertSame('default', $host::getStorage('key', 'default', $ns));

        $this->assertSame('value', $host::setStorage('key', 'value', $ns));

        $this->assertTrue($host::hasStorage('key', $ns));
        $this->assertSame('value', $host::getStorage('key', null, $ns));
        $this->assertFalse($host::hasStorage('key', $ns . 'other'), 'another namespace');
        $this->assertFalse($host::hasStorage('value', $ns), 'a value is not a key');
    }

    public function testAnEmptyValueStillCountsAsStored(): void
    {
        $host = $this->host();
        $ns = 'ns' . uniqid();

        $host::setStorage('empty', null, $ns);

        $this->assertTrue($host::hasStorage('empty', $ns));
    }

    // --------------------------------------------------------------------- jwt

    public function testATokenCarriesWhatItWasGiven(): void
    {
        $host = $this->host();

        $payload = $host->decode($host->token('subject', 'uuid-1', ['a' => 1], 60));

        $this->assertSame('subject', $payload['sub']);
        $this->assertSame('uuid-1', $payload['uuid']);
        $this->assertSame(1, $payload['data']->a);
        $this->assertEqualsWithDelta(time() + 60, $payload['exp'], 3);
    }

    public function testATokenLivesTenMinutesUnlessToldOtherwiseAndZeroMeansForever(): void
    {
        $host = $this->host();

        $this->assertEqualsWithDelta(time() + 600, $host->decode($host->token('s'))['exp'], 3);
        $this->assertArrayNotHasKey('exp', $host->decode($host->token('s', null, [], 0)));
    }

    public function testAnExpiredOrTamperedTokenIsRefused(): void
    {
        $host = $this->host();

        $expired = $host->token('s', null, [], -100);
        $this->expectException(\Firebase\JWT\ExpiredException::class);
        try {
            $host->decode($expired);
        } finally {
            // the same trait, a token whose body was changed after signing
            [$h, $p, $s] = explode('.', $host->token('s', 'u'));
            $forged = $h . '.' . rtrim(strtr(base64_encode((string) json_encode(['sub' => 's', 'uuid' => 'someone-else', 'exp' => time() + 600])), '+/', '-_'), '=') . '.' . $s;

            try {
                $host->decode($forged);
                $this->fail('a changed token must not be accepted');
            } catch (\Firebase\JWT\SignatureInvalidException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    // ------------------------------------------------------------ confirmation

    public function testAConfirmationTokenIsBoundToTheAddress(): void
    {
        $host = $this->host();
        $user = new User(['email' => 'Ann@Example.com']);
        $user->uuid = 'u-1';

        $payload = $host->decode($host->issueConfirmationToken($user));

        $this->assertSame('user-confirmation', $payload['sub']);
        $this->assertSame('u-1', $payload['uuid']);
        $this->assertSame($host->confirmationFingerprint($user), $payload['data']->fingerprint);

        $other = new User(['email' => 'ann@elsewhere.com']);
        $other->uuid = 'u-1';
        $this->assertNotSame($host->confirmationFingerprint($user), $host->confirmationFingerprint($other), 'another address, another link');

        $same = new User(['email' => 'ann@example.com']);
        $same->uuid = 'u-1';
        $this->assertSame($host->confirmationFingerprint($user), $host->confirmationFingerprint($same), 'the case of the address does not matter');
    }

    public function testConfirmationIsRequiredOnlyWhenSwitchedOnAndThereIsAnAddress(): void
    {
        $this->assertFalse($this->host()->isConfirmationRequired('a@example.com'), 'off by default');

        $this->setting('user_confirmation', 'on');
        $host = $this->host();

        $this->assertTrue($host->isConfirmationRequired('a@example.com'));
        $this->assertFalse($host->isConfirmationRequired(''));
        $this->assertFalse($host->isConfirmationRequired(null));
    }

    // ---------------------------------------------------------------- throttle

    public function testTheAttemptsAreCountedPerAccountAndAddress(): void
    {
        $this->setting('user_login_attempts', 3);
        $host = $this->host();
        $scope = 'test' . uniqid();

        foreach ([1, 2] as $i) {
            $host->throttleHit($scope, 'Ann', '10.0.0.1');
        }
        $this->assertFalse($host->isThrottled($scope, 'ann', '10.0.0.1'));
        $host->throttleHit($scope, ' ANN ', '10.0.0.1');

        $this->assertSame(3, $host->throttleCount($scope, 'ann', '10.0.0.1'), 'the case and the spaces of the name are not a way around');
        $this->assertTrue($host->isThrottled($scope, 'ann', '10.0.0.1'));
        $this->assertFalse($host->isThrottled($scope, 'ann', '10.0.0.2'), 'the same name from another address');
        $this->assertFalse($host->isThrottled($scope, 'bob', '10.0.0.1'), 'another name from the same address');
        $this->assertFalse($host->isThrottled($scope . 'x', 'ann', '10.0.0.1'), 'another form');

        $host->throttleClear($scope, 'ann', '10.0.0.1');
        $this->assertFalse($host->isThrottled($scope, 'ann', '10.0.0.1'));
    }

    public function testWithTheLimitAtZeroNothingIsCountedOrBlocked(): void
    {
        $this->setting('user_login_attempts', 0);
        $host = $this->host();
        $scope = 'test' . uniqid();

        $host->throttleHit($scope, 'ann', '10.0.0.1');

        $this->assertSame(0, $host->throttleCount($scope, 'ann', '10.0.0.1'));
        $this->assertFalse($host->isThrottled($scope, 'ann', '10.0.0.1'), 'switched off means nobody is blocked');
    }

    // ------------------------------------------------------------------- mcp

    public function testAToolPromptAndResourceAreRegisteredOnceByName(): void
    {
        $host = $this->host();
        $mcp = $this->getService('mcp');

        $tool = new class($this->getService(ContainerInterface::class)) extends AbstractMcpTool {
            public const NAME = 'trait_test_tool';

            public const DESCRIPTION = 'a tool';

            public function execute(array $args = []): mixed
            {
                return 'ok';
            }
        };
        $prompt = new class($this->getService(ContainerInterface::class)) extends AbstractMcpPrompt {
            public const NAME = 'trait_test_prompt';

            public const DESCRIPTION = 'a prompt';

            public function render(array $args = []): string
            {
                return 'text';
            }
        };
        $resource = new class($this->getService(ContainerInterface::class)) extends AbstractMcpResource {
            public const URI = 'wse://trait-test';

            public const NAME = 'trait_test_resource';

            public const DESCRIPTION = 'a resource';

            public function read(): string
            {
                return '{}';
            }
        };

        $this->assertTrue($host->addMcpTool($tool));
        $this->assertTrue($host->addMcpPrompt($prompt));
        $this->assertTrue($host->addMcpResource($resource));
        $this->assertTrue($mcp->has('trait_test_tool'));
        $this->assertSame('ok', $mcp->find('trait_test_tool')->execute());

        $this->assertFalse($host->addMcpTool($tool), 'the name is taken');
        $this->assertFalse($host->addMcpPrompt($prompt));
        $this->assertFalse($host->addMcpResource($resource));
    }

    // ---------------------------------------------------------------- renderer

    private function html(string $body): string
    {
        return '<html><body>' . $body . '</body></html>';
    }

    public function testTheGuardScriptIsAddedBeforeTheEndOfAPageWithAForm(): void
    {
        $html = $this->host('/')->injectFormGuard($this->html('<form action="/form/contact"></form>'));

        $this->assertStringContainsString('data-form-guard', $html);
        $this->assertLessThan(mb_strripos($html, '</body>'), mb_strpos($html, 'data-form-guard'));
    }

    public function testTheGuardScriptIsLeftOutWhereItIsNotNeeded(): void
    {
        $host = $this->host('/');
        $form = $this->html('<form></form>');

        $this->assertSame($this->html('<p>no form</p>'), $host->injectFormGuard($this->html('<p>no form</p>')));
        $this->assertSame('<form></form>', $host->injectFormGuard('<form></form>'), 'not a whole page');
        $this->assertSame($this->html('<form></form><script data-form-guard></script>'), $host->injectFormGuard($this->html('<form></form><script data-form-guard></script>')), 'already there');
        $this->assertSame($form, $this->host('/cup/page')->injectFormGuard($form), 'the panel is not a public form');

        $this->setting('form_guard_mode', 'off');
        $this->assertSame($form, $this->host('/')->injectFormGuard($form), 'switched off');
    }

    public function testATemplateWithAnErrorIsABadRequest(): void
    {
        $this->expectException(HttpBadRequestException::class);

        $this->host('/')->render('no/such/template.twig');
    }

    /**
     * A folder of templates the loader knows, removed afterwards
     *
     * @param array<string, string> $files
     */
    private function templates(array $files): string
    {
        $dir = sys_get_temp_dir() . '/wse-tpl-' . uniqid();
        mkdir($dir);
        foreach ($files as $name => $code) {
            file_put_contents($dir . '/' . $name, $code);
        }
        $this->getService('view')->getLoader()->addPath($dir);
        $this->cleanup[] = $dir;

        return $dir;
    }

    private array $cleanup = [];

    public function tearDown(): void
    {
        foreach ($this->cleanup as $dir) {
            array_map('unlink', glob($dir . '/*'));
            rmdir($dir);
        }
        $this->cleanup = [];

        parent::tearDown();
    }

    public function testAnExtendsCycleIsNamedInDevelopment(): void
    {
        $this->templates([
            'cycle-a.twig' => "{% extends 'cycle-b.twig' %}",
            'cycle-b.twig' => "{% extends 'cycle-c.twig' %}",
            'cycle-c.twig' => "{% extends 'cycle-a.twig' %}",
            'cycle-self.twig' => "{% extends 'cycle-self.twig' %}",
            'fine.twig' => "{% extends 'fine-base.twig' %}{% block b %}ok{% endblock %}",
            'fine-base.twig' => '[{% block b %}{% endblock %}]',
        ]);
        $host = $this->host('/');
        $host->debug = true;

        foreach (['cycle-a.twig', 'cycle-self.twig'] as $template) {
            try {
                $host->render($template);
                $this->fail('a cycle was rendered');
            } catch (HttpBadRequestException $e) {
                $this->assertStringContainsString('extends itself', $e->getMessage());
                $this->assertStringContainsString($template, $e->getMessage());
            }
        }
        $this->assertMatchesRegularExpression(
            '~cycle-a\.twig \([^)]+\) -> cycle-b\.twig \([^)]+\) -> cycle-c\.twig \([^)]+\) -> cycle-a\.twig~',
            $this->catchMessage(fn () => $host->render('cycle-a.twig')),
            'the whole chain, with the files'
        );

        $this->assertSame('[ok]', $host->render('fine.twig'));
    }

    private function catchMessage(callable $call): string
    {
        try {
            $call();
        } catch (\Throwable $e) {
            return $e->getMessage();
        }

        return '';
    }

    public function testAPhpWarningInATemplateIsAnErrorWithTheTemplateInDevelopment(): void
    {
        $this->templates(['warns.twig' => '<a title="{{ thing.boom }}">link</a>']);
        $thing = new class() {
            public function boom(): string
            {
                $list = [];

                return $list['missing']; // Undefined array key: a warning
            }
        };
        $host = $this->host('/');
        $host->debug = true;
        $handler = set_error_handler(fn () => false);
        restore_error_handler();

        // what DEBUG sets at start: the test run itself reports no warnings
        $level = error_reporting(E_ALL & ~E_DEPRECATED);

        try {
            $message = $this->catchMessage(fn () => $host->render('warns.twig', ['thing' => $thing]));
        } finally {
            error_reporting($level);
        }

        $this->assertStringContainsString('warns.twig', $message);
        $this->assertStringContainsString('Undefined array key', $message);
        $this->assertStringContainsString('line 1', $message);

        // the handler is gone afterwards: nothing leaks into the next request
        $after = set_error_handler(fn () => false);
        restore_error_handler();
        $this->assertSame($handler, $after, 'the error handler is as it was before the render');
    }

    public function testATemplateStringIsRenderedAndABrokenOneIsAnError(): void
    {
        $host = $this->host();

        $this->assertSame('Hello Ann', $host->renderFromString('Hello {{ name }}', ['name' => 'Ann']));

        $this->expectException(\RuntimeException::class);
        $host->renderFromString('Hello {{ name ');
    }
}
