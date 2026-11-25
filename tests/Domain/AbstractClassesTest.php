<?php declare(strict_types=1);

namespace tests\Domain;

use App\Application\PubSub;
use App\Domain\AbstractException;
use App\Domain\AbstractExtension;
use App\Domain\AbstractMiddleware;
use App\Domain\AbstractPlugin;
use App\Domain\AbstractSchedule;
use App\Domain\Casts\User\Status as UserStatus;
use App\Domain\Exceptions\HttpRedirectException;
use App\Domain\Models\Task;
use App\Domain\Service\Catalog\OrderService;
use App\Domain\Service\Parameter\ParameterService;
use App\Domain\Service\User\UserService;
use App\Domain\Tasks\SearchIndexTask;
use App\Domain\Tasks\SendMailTask;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\App;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Response;
use tests\TestCase;

/**
 * What every exception, extension, middleware, schedule, plugin and event of the application is built on
 *
 * @internal
 *
 * #[CoversNothing]
 */
class AbstractClassesTest extends TestCase
{
    private ?string $theme = null;

    public function tearDown(): void
    {
        if ($this->theme !== null) {
            foreach (glob($this->theme . '/*') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($this->theme);
            $this->theme = null;
        }

        parent::tearDown();
    }

    private function setting(string $name, string $value): void
    {
        $this->getService(ParameterService::class)->create(['name' => $name, 'value' => $value]);
        $this->getService(\Illuminate\Cache\ArrayStore::class)->flush();
    }

    // -------------------------------------------------------------- exceptions

    public function testAnExceptionNamesItselfAndExplainsItself(): void
    {
        $exception = new class('Something is wrong') extends AbstractException {};

        $this->assertSame('Something is wrong', $exception->getMessage());
        $this->assertSame($exception->getDescription(), 'Something is wrong', 'the message is the description until there is one');
        $this->assertStringStartsWith('AbstractException@anonymous', $exception->getTitle());

        $this->assertSame($exception, $exception->setTitle('Oops')->setDescription('A longer story'));
        $this->assertSame('Oops', $exception->getTitle());
        $this->assertSame('A longer story', $exception->getDescription());
    }

    public function testAnExceptionKeepsItsOwnMessageWhenNoneIsGiven(): void
    {
        $exception = new class() extends AbstractException {
            protected $message = 'The default';
        };

        $this->assertSame('The default', $exception->getMessage());
    }

    public function testARedirectKnowsWhereItGoes(): void
    {
        $redirect = new HttpRedirectException('/somewhere');

        $this->assertSame(302, $redirect->getCode());
        $this->assertSame('/somewhere', $redirect->getUrl());
        $this->assertSame('/elsewhere', $redirect->setUrl('/elsewhere')->getUrl());
    }

    // -------------------------------------------------------------- extension

    public function testAnExtensionOffersNothingUntilItIsAskedTo(): void
    {
        $extension = new class($this->getService(ContainerInterface::class)) extends AbstractExtension {
            public function getName(): string
            {
                return 'test';
            }
        };

        $this->assertSame([], $extension->getTokenParsers());
        $this->assertSame([], $extension->getNodeVisitors());
        $this->assertSame([], $extension->getFilters());
        $this->assertSame([], $extension->getTests());
        $this->assertSame([], $extension->getFunctions());
        $this->assertSame([[], []], $extension->getOperators(), 'the shape twig asks for');
    }

    // --------------------------------------------------------------- middleware

    public static function addresses(): array
    {
        return [
            'the real address first' => [['HTTP_X_REAL_IP' => '1.1.1.1', 'HTTP_X_FORWARDED_FOR' => '2.2.2.2', 'REMOTE_ADDR' => '3.3.3.3'], '1.1.1.1'],
            'then the forwarded one' => [['HTTP_X_FORWARDED_FOR' => '2.2.2.2', 'REMOTE_ADDR' => '3.3.3.3'], '2.2.2.2'],
            'then the one of the connection' => [['REMOTE_ADDR' => '3.3.3.3'], '3.3.3.3'],
            'and nothing' => [[], ''],
        ];
    }

    #[DataProvider('addresses')]
    public function testTheAddressOfTheCallerIsTakenFromTheMostTrustedPlace(array $server, string $expected): void
    {
        $middleware = new class($this->getService(ContainerInterface::class)) extends AbstractMiddleware {
            public function __invoke(\Slim\Psr7\Request $request, RequestHandlerInterface $handler): ResponseInterface
            {
                return new Response();
            }

            public function ip(\Slim\Psr7\Request $request): string
            {
                return $this->getRequestRemoteIP($request);
            }
        };

        $this->assertSame($expected, $middleware->ip((new ServerRequestFactory())->createServerRequest('GET', '/', $server)));
    }

    // ----------------------------------------------------------------- schedule

    private function schedule(): AbstractSchedule
    {
        return new class($this->getService(ContainerInterface::class)) extends AbstractSchedule {
            public function run(): void {}
        };
    }

    #[DataProvider('cronLines')]
    public function testACronLineIsDueOrNot(string $line, string $moment, bool $expected): void
    {
        $this->assertSame($expected, $this->schedule()->isShouldRun($line, new \DateTime($moment)), "{$line} at {$moment}");
    }

    public static function cronLines(): array
    {
        // 2026-01-05 is a monday, 03:10; 2026-01-11 is a sunday
        return [
            'every minute' => ['* * * * *', '2026-01-05 03:10', true],
            'a number' => ['10 3 * * *', '2026-01-05 03:10', true],
            'another number' => ['11 3 * * *', '2026-01-05 03:10', false],
            'a list' => ['1,10,20 * * * *', '2026-01-05 03:10', true],
            'a range' => ['5-15 * * * *', '2026-01-05 03:10', true],
            'out of a range' => ['11-15 * * * *', '2026-01-05 03:10', false],
            'every fifth' => ['*/5 * * * *', '2026-01-05 03:10', true],
            'not every fifth' => ['*/5 * * * *', '2026-01-05 03:11', false],
            'a step inside a range' => ['10-30/5 * * * *', '2026-01-05 03:15', true],
            'out of a step inside a range' => ['10-30/5 * * * *', '2026-01-05 03:16', false],
            'a step from a number is not a cron line' => ['20/10 * * * *', '2026-01-05 03:30', false],
            'monday by name' => ['10 3 * * MON', '2026-01-05 03:10', true],
            'sunday as 0' => ['0 0 * * 0', '2026-01-11 00:00', true],
            'sunday as 7' => ['0 0 * * 7', '2026-01-11 00:00', true],
            'daily' => ['@daily', '2026-01-05 00:00', true],
            'daily, not at noon' => ['@daily', '2026-01-05 12:00', false],
            'double space' => ['10  3 * * *', '2026-01-05 03:10', true],
            'the day of the month or the day of the week' => ['10 3 15 * MON', '2026-01-05 03:10', true],
            'there is no month 13' => ['* * * 13 *', '2026-01-05 03:10', false],
            'nor minute 61' => ['61 * * * *', '2026-01-05 03:10', false],
            'a zero step' => ['*/0 * * * *', '2026-01-05 03:10', false],
            'garbage' => ['x * * * *', '2026-01-05 03:10', false],
            'four fields' => ['* * * *', '2026-01-05 03:10', false],
        ];
    }

    public function testTheLastAndTheNextMomentOfALine(): void
    {
        $now = new \DateTime('2026-01-05 03:10:30');

        $this->assertSame('2026-01-05 03:10', AbstractSchedule::lastDue('*/5 * * * *', $now)->format('Y-m-d H:i'), 'the current minute counts');
        $this->assertSame('2026-01-05 03:05', AbstractSchedule::lastDue('*/5 * * * *', new \DateTime('2026-01-05 03:09'))->format('Y-m-d H:i'));
        $this->assertSame('2026-01-05 03:15', AbstractSchedule::nextDue('*/5 * * * *', $now)->format('Y-m-d H:i'));
    }

    public function testAScheduleKnowsWhetherItIsTimeToRun(): void
    {
        $schedule = $this->schedule();

        $this->assertTrue($schedule->isShouldRun('* * * * *'));
        $this->assertFalse($schedule->isShouldRun('* * * 13 *'), 'there is no month 13');
        $this->assertFalse($schedule->isShouldRun('61 * * * *'), 'nor minute 61');
        $this->assertSame((int) datetime()->format('N') === 3, $schedule->isShouldRun('* * * * 3'), 'a day of the week');
    }

    // ------------------------------------------------------------------- plugin

    /**
     * A plugin gets a container of its own over the real one, with an application of its own for the routes it adds
     */
    private function plugin(string $name = 'ZzTestPlugin'): object
    {
        $real = $this->getService(ContainerInterface::class);
        $container = new class($real) implements ContainerInterface {
            public array $set = [];

            public function __construct(private ContainerInterface $real, private ?App $app = null)
            {
                $this->app = \Slim\Factory\AppFactory::create();
            }

            public function get(string $id): mixed
            {
                return $id === App::class ? $this->app : ($this->set[$id] ?? $this->real->get($id));
            }

            public function has(string $id): bool
            {
                return $id === App::class || isset($this->set[$id]) || $this->real->has($id);
            }

            public function set(string $id, mixed $value): void
            {
                $this->set[$id] = $value;
            }
        };

        return new class($container, $name) extends AbstractPlugin {
            public const NAME = 'ZzTestPlugin';
            public const TITLE = 'Test plugin';
            public const AUTHOR = 'Tester';
            public const AUTHOR_EMAIL = 'tester@example.com';
            public const VERSION = '2.1.0';

            public ContainerInterface $own;

            public function __construct(ContainerInterface $container, string $name)
            {
                parent::__construct($container);
                $this->own = $container;
            }

            public function __call(string $method, array $args): mixed
            {
                return $this->{$method}(...$args);
            }
        };
    }

    public function testAPluginDescribesItself(): void
    {
        $plugin = $this->plugin();

        $this->assertSame('Test plugin', $plugin->getCredentials('title'));
        $this->assertSame('2.1.0', $plugin->getCredentials('version'));
        $this->assertSame('ZzTestPlugin', $plugin->getCredentials('name'));
        $this->assertSame('tester@example.com', $plugin->getCredentials()['author_email']);
        $this->assertSame($plugin->getCredentials(), $plugin->getCredentials('nothing-like-it'), 'an unknown field is everything');
        $this->assertSame($plugin, $plugin->own->get('ZzTestPlugin'), 'it registers itself under its name');
    }

    public function testAPluginWithoutAnAuthorIsRefused(): void
    {
        $this->expectException(\RuntimeException::class);

        new class($this->getService(ContainerInterface::class)) extends AbstractPlugin {
            public const AUTHOR = '';
        };
    }

    public function testAPluginListensAndTalksOverTheChannels(): void
    {
        $plugin = $this->plugin();
        $heard = [];

        $this->assertSame($plugin, $plugin->subscribe(['zz:one', 'zz:two'], function ($data) use (&$heard): void {
            $heard[] = $data;
        }));
        $plugin->publish('zz:one', 'first')->publish('zz:two', 'second')->publish('zz:other', 'nobody');

        $this->assertSame(['first', 'second'], $heard);
    }

    public function testAPluginAddsItsFieldsScriptsButtonsAndTabs(): void
    {
        $plugin = $this->plugin();

        $plugin->addSettingsField(['label' => 'Key', 'name' => 'key']);
        $field = $plugin->getSettingsFields()['ZzTestPlugin[key]'];
        $this->assertSame('Key', $field['label']);
        $this->assertSame('text', $field['type'], 'the rest is by default');
        $this->assertArrayHasKey('placeholder', $field['args']);

        $this->assertFalse($plugin->script || $plugin->toolbar || $plugin->sidebar || $plugin->navigation);
        $plugin->addScript('/a.js');
        $plugin->addScript(['src' => '/b.js']);
        $plugin->addToolbarItem('<b>x</b>');
        $plugin->addSidebarTab(['tab']);
        $this->assertSame(['/a.js', '/b.js'], $plugin->getScripts());
        $this->assertSame(['<b>x</b>'], $plugin->getToolbarItems());
        $this->assertSame(['tab'], $plugin->getSidebarTabs());
        $this->assertTrue($plugin->script && $plugin->toolbar && $plugin->sidebar);
    }

    public function testAPluginAddsATemplateFolderOnlyWhenItIsThere(): void
    {
        $plugin = $this->plugin();
        $loader = $plugin->own->get('view')->getLoader();

        $plugin->setTemplateFolder(SRC_DIR . '/Template/mixin');
        $plugin->setTemplateFolder('/no/such/folder');

        $this->assertContains(realpath(SRC_DIR . '/Template/mixin'), array_map('realpath', $loader->getPaths()));
        $this->assertNotContains('/no/such/folder', $loader->getPaths());
    }

    public function testAPluginAddsItsTwigExtension(): void
    {
        $plugin = $this->plugin();
        $extension = new class($this->getService(ContainerInterface::class)) extends AbstractExtension {
            public function getName(): string
            {
                return 'zz_test_extension';
            }

            public function getFunctions()
            {
                return [new \Twig\TwigFunction('zz_test_function', fn () => 'from the plugin')];
            }
        };

        $plugin->addTwigExtension($extension::class);

        $this->assertSame('from the plugin', $plugin->renderFromString('{{ zz_test_function() }}'));
    }

    public function testAPluginsNavigationPointAndRoutesAnswerEvenWithoutAHandler(): void
    {
        $plugin = $this->plugin();

        $navigation = $plugin->enableNavigationItem();
        $this->assertTrue($plugin->navigation);
        $this->assertSame('cup:zztestplugin', $navigation->getName());
        $this->assertSame('/cup/plugin/ZzTestPlugin', $navigation->getPattern());

        $route = $plugin->map(['methods' => 'post', 'pattern' => '/zz-plugin/hello']);
        $this->assertSame(['POST'], $route->getMethods());
        $this->assertSame('/zz-plugin/hello', $route->getPattern());

        $both = $plugin->map(['pattern' => '/zz-plugin/both', 'handler' => fn ($request, Response $response) => $response]);
        $this->assertSame(['GET', 'POST'], $both->getMethods());

        $request = (new ServerRequestFactory())->createServerRequest('GET', '/');
        $answer = ($navigation->getCallable())($request, new Response());
        $this->assertStringContainsString('empty route for plugin: ZzTestPlugin', (string) $answer->getBody());
        $answer = ($route->getCallable())($request, new Response());
        $this->assertStringContainsString('empty route for plugin: ZzTestPlugin', (string) $answer->getBody());
        $this->assertSame('text/plain', $answer->getHeaderLine('Content-Type'));
    }

    // ------------------------------------------------------------------- events

    /**
     * Letters are made from the templates of the theme: a small one of their own
     */
    private function mailTheme(): void
    {
        $this->theme = THEME_DIR . '/zz-test-mail';
        @mkdir($this->theme, 0o777);
        chmod($this->theme, 0o777);
        file_put_contents($this->theme . '/letter.twig', 'Hello {{ user.username ?? order.serial }} {{ link ?? "" }}');
        chmod($this->theme . '/letter.twig', 0o666);

        $this->setting('common_theme', 'zz-test-mail');
    }

    private function letters(): array
    {
        return Task::where('action', SendMailTask::class)->orderBy('date')->get()->map(fn (Task $task) => $task->params)->all();
    }

    private function user(array $extra = [], string $status = UserStatus::WORK): \App\Domain\Models\User
    {
        return $this->getService(UserService::class)->create($extra + ['username' => 'ann', 'email' => 'ann@example.com', 'status' => $status]);
    }

    public function testAChangeOfTheCatalogOrTheSiteStartsTheIndexingAgain(): void
    {
        $this->getService(PubSub::class)->publish('cup:page:edit', null);

        $this->assertSame(1, Task::where('action', SearchIndexTask::class)->count());
    }

    public function testNothingHearsAChangeThatIsRolledBack(): void
    {
        $pubsub = $this->getService(PubSub::class);

        $result = $pubsub->silently(function () use ($pubsub) {
            $pubsub->publish('cup:page:edit', null);

            return 'done';
        });
        $pubsub->publish('zz:nothing');

        $this->assertSame('done', $result);
        $this->assertSame(0, Task::where('action', SearchIndexTask::class)->count());
    }

    public function testTheLettersOfAUserAreSentOnlyWhenTheyAreSwitchedOnAndHaveATemplate(): void
    {
        $this->mailTheme();
        $pubsub = $this->getService(PubSub::class);
        $user = $this->user();

        $pubsub->publish('auth:user:register', $user);
        $this->assertSame([], $this->letters(), 'off by default');

        $this->setting('user_register_mail', 'on');
        $pubsub->publish('auth:user:register', $user);
        $this->assertSame([], $this->letters(), 'and no template');

        $this->setting('user_register_mail_template', 'letter.twig');
        $pubsub->publish('auth:user:register', $user);
        $pubsub->publish('common:user:confirmed', $user);
        $pubsub->publish('auth:user:register', $this->user(['username' => 'waiting', 'email' => 'w@example.com'], UserStatus::CONFIRMATION));
        $pubsub->publish('common:user:confirmed', null);

        $letters = $this->letters();
        $this->assertCount(2, $letters, 'the one waiting for the confirmation gets the confirmation, not this one');
        $this->assertSame('ann@example.com', $letters[0]['to']);
        $this->assertSame('Hello ann ', $letters[0]['template']);
        $this->assertTrue($letters[0]['isHtml']);
    }

    public function testTheConfirmationAndRecoveryLettersCarryALinkWithTheToken(): void
    {
        $this->mailTheme();
        $this->setting('common_homepage', 'https://shop.example');
        foreach (['user_confirmation_mail', 'user_recovery_mail', 'user_recovery_done_mail'] as $name) {
            $this->setting($name, 'on');
            $this->setting($name . '_template', 'letter.twig');
        }
        $pubsub = $this->getService(PubSub::class);
        $user = $this->user();

        $pubsub->publish('common:user:confirmation', ['user' => $user, 'token' => 'abc']);
        $pubsub->publish('common:user:recovery', ['user' => $user, 'token' => 'def']);
        $pubsub->publish('common:user:recovery-done', $user);

        $letters = array_column($this->letters(), 'template');
        $this->assertSame('Hello ann https://shop.example/user/confirm?token=abc', $letters[0]);
        $this->assertSame('Hello ann https://shop.example/user/recovery?token=def', $letters[1]);
        $this->assertSame('Hello ann ', $letters[2]);
    }

    public function testAnOrderIsMailedToTheOwnerAndToTheCustomerWhenThatIsSwitchedOn(): void
    {
        $this->mailTheme();
        $this->setting('catalog_mail_admin', 'on');
        $this->setting('mail_from', 'shop@example.com');
        $this->setting('catalog_mail_admin_template', 'letter.twig');
        $this->setting('catalog_mail_client', 'on');
        $this->setting('catalog_mail_client_template', 'letter.twig');
        $order = $this->getService(OrderService::class)->create(['email' => 'buyer@example.com', 'phone' => '+380501234567', 'delivery' => ['address' => 'x']]);

        $this->getService(PubSub::class)->publish('cup:catalog:order:create', $order);

        $this->assertSame(['shop@example.com', 'buyer@example.com'], array_column($this->letters(), 'to'));
    }

    public function testAnOrderPaidThroughAPluginChangesItsStatusWhenTheSettingSaysSo(): void
    {
        $pubsub = $this->getService(PubSub::class);
        $order = $this->getService(OrderService::class)->create(['email' => 'buyer@example.com', 'phone' => '+380501234567', 'delivery' => ['address' => 'x']]);
        $status = $this->getService(\App\Domain\Service\Reference\ReferenceService::class)->create(['type' => 'order_status', 'title' => 'Paid', 'order' => 1]);

        $pubsub->publish('plugin:order:payment', $order);
        $this->assertNull(\App\Domain\Models\CatalogOrder::find($order->uuid)->status_uuid, 'nothing is set');

        $this->setting('catalog_order_status_payed', $status->uuid);
        $pubsub->publish('plugin:order:payment', $order);
        $this->assertSame($status->uuid, \App\Domain\Models\CatalogOrder::find($order->uuid)->status_uuid);
    }
}
