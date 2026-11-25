<?php declare(strict_types=1);

namespace tests\Cup;

use App\Application\Dashboard;
use App\Domain\Casts\Task\Status as TaskStatus;
use App\Domain\Models\CatalogOrder;
use App\Domain\Models\FormData;
use App\Domain\Models\GuestBook;
use App\Domain\Models\Parameter;
use App\Domain\Models\Task;
use App\Domain\Models\User;
use App\Domain\Service\Catalog\CategoryService;
use App\Domain\Service\Catalog\OrderService;
use App\Domain\Service\Catalog\ProductService;
use App\Domain\Service\GuestBook\GuestBookService;
use App\Domain\Service\Parameter\ParameterService;
use App\Domain\Service\Task\TaskService;
use App\Domain\Tasks\SearchIndexTask;
use Psr\Container\ContainerInterface;

/**
 * The tools of the panel: settings, the task list, the log viewer and the dashboard
 *
 * @internal
 *
 * #[CoversNothing]
 */
class CupToolsTest extends CupTestCase
{
    /** @var string[] log files made by a test */
    private array $logs = [];

    public function tearDown(): void
    {
        foreach ($this->logs as $log) {
            @unlink($log);
        }
        $this->logs = [];

        parent::tearDown();
    }

    private function dashboard(): Dashboard
    {
        $this->getService(\Illuminate\Cache\ArrayStore::class)->flush();

        return new Dashboard($this->getService(ContainerInterface::class));
    }

    private function setParameter(string $name, string $value): void
    {
        $this->getService(ParameterService::class)->create(['name' => $name, 'value' => $value]);
        $this->getService(\Illuminate\Cache\ArrayStore::class)->flush();
    }

    // ----------------------------------------------------------------- settings

    public function testTheSettingsPageShowsTheValuesThatAreSet(): void
    {
        $this->admin();
        $this->setParameter('common_title', 'My Corner Shop');

        $this->assertStringContainsString('My Corner Shop', (string) $this->cup('GET', '/cup/parameters')->getBody());
    }

    public function testSavingTheSettingsCreatesAndChangesThem(): void
    {
        $this->admin();
        $this->setParameter('common_title', 'Old title');

        $response = $this->cup('POST', '/cup/parameters', ['form_params' => ['common' => ['title' => 'New title', 'description' => 'About us'], 'shop' => ['currency' => 'UAH']]]);

        $this->assertRedirectsTo($response, '/cup/parameters');
        $this->assertSame('New title', Parameter::firstWhere(['name' => 'common_title'])->value);
        $this->assertSame('About us', Parameter::firstWhere(['name' => 'common_description'])->value);
        $this->assertSame('UAH', Parameter::firstWhere(['name' => 'shop_currency'])->value);
    }

    public function testAListIsStoredAsOneCommaSeparatedValue(): void
    {
        $this->admin();

        $this->cup('POST', '/cup/parameters', ['form_params' => ['user' => ['access' => ['cup:main', 'cup:page:list']]]]);

        $this->assertSame('cup:main,cup:page:list', Parameter::firstWhere(['name' => 'user_access'])->value);
    }

    public function testAnEmptyValueRemovesTheSetting(): void
    {
        $this->admin();
        $this->setParameter('common_title', 'Gone soon');
        $this->setParameter('common_keep', 'Stays');

        $this->cup('POST', '/cup/parameters', ['form_params' => ['common' => ['title' => '', 'unknown' => '  ']]]);

        $this->assertNull(Parameter::firstWhere(['name' => 'common_title']));
        $this->assertNull(Parameter::firstWhere(['name' => 'common_unknown']));
        $this->assertNotNull(Parameter::firstWhere(['name' => 'common_keep']), 'what the form did not send is left alone');
    }

    public function testTheSecurityTokenOfTheFormIsNotASetting(): void
    {
        $this->admin();

        $response = $this->cup('POST', '/cup/parameters', ['csrf' => false, 'form_params' => ['_csrf' => $this->cupToken(), 'common' => ['title' => 'Titled']]]);

        $this->assertRedirectsTo($response, '/cup/parameters');
        $this->assertSame('Titled', Parameter::firstWhere(['name' => 'common_title'])->value);
        $this->assertSame(1, Parameter::count(), 'the token made no setting of its own');
        $this->assertStringNotContainsString('<b>Warning</b>', (string) $response->getBody());
    }

    public function testASettingIsSavedAfterTheSiteWasAlreadyReadingIt(): void
    {
        $this->admin();
        $this->cup('GET', '/cup/parameters');

        $this->cup('POST', '/cup/parameters', ['form_params' => ['common' => ['title' => 'Read after save']]]);

        $this->assertStringContainsString('Read after save', (string) $this->cup('GET', '/cup/parameters')->getBody());
    }

    public function testTheWayBackAfterSavingStaysOnTheSite(): void
    {
        $this->admin();
        $form = ['form_params' => ['common' => ['title' => 'x']]];

        $this->assertRedirectsTo($this->cup('POST', '/cup/parameters?return=/cup/page', $form), '/cup/page');

        foreach (['https://evil.example/', '//evil.example', 'javascript:alert(1)', '/\\evil.example'] as $target) {
            $this->assertRedirectsTo($this->cup('POST', '/cup/parameters?return=' . urlencode($target), $form), '/cup/parameters', $target);
        }
    }

    public function testTheSettingsCannotBeChangedByAGetRequest(): void
    {
        $this->admin();

        $this->cup('GET', '/cup/parameters?common[title]=Planted');

        $this->assertNull(Parameter::firstWhere(['name' => 'common_title']));
    }

    // -------------------------------------------------------------------- tasks

    public function testTheTaskListTellsWhatIsNewChangedAndGone(): void
    {
        $this->admin();
        $tasks = $this->getService(TaskService::class);
        $fresh = $tasks->create(['title' => 'Fresh', 'action' => SearchIndexTask::class]);
        $changed = $tasks->create(['title' => 'Changed', 'action' => SearchIndexTask::class]);
        $same = $tasks->create(['title' => 'Same', 'action' => SearchIndexTask::class]);
        $tasks->update($changed, ['status' => TaskStatus::WORK, 'progress' => 40]);
        $gone = \Ramsey\Uuid\Uuid::uuid4()->toString();

        $response = $this->cup('POST', '/cup/refresh', ['form_params' => ['tasks' => [
            $changed->uuid => ['status' => 'queue', 'progress' => '0'],
            $same->uuid => ['status' => 'queue', 'progress' => '0'],
            $gone => ['status' => 'work', 'progress' => '10'],
        ]]]);

        $out = $this->json($response)['task'];
        $this->assertSame([$fresh->uuid], array_column($out['new'], 'uuid'));
        $this->assertSame([$changed->uuid], array_column($out['update'], 'uuid'));
        $this->assertEquals(40, $out['update'][0]['progress']);
        $this->assertSame([$gone], $out['delete']);
        $this->assertArrayNotHasKey('params', $out['new'][0], 'the parameters of a task are not for the browser');
        $this->assertArrayNotHasKey('output', $out['new'][0]);
    }

    public function testTheTaskListWithNothingKnownYetListsTheRecentTasks(): void
    {
        $this->admin();
        $tasks = $this->getService(TaskService::class);
        foreach (['One', 'Two'] as $title) {
            $tasks->create(['title' => $title, 'action' => SearchIndexTask::class]);
        }

        $out = $this->json($this->cup('POST', '/cup/refresh'))['task'];

        $this->assertCount(2, $out['new']);
        $this->assertSame([], $out['update']);
        $this->assertSame([], $out['delete']);
    }

    public function testTheTaskListShowsFiftyTasksAtMost(): void
    {
        $this->admin();
        $tasks = $this->getService(TaskService::class);
        foreach (range(1, 55) as $i) {
            $tasks->create(['title' => "Task {$i}", 'action' => SearchIndexTask::class]);
        }

        $this->assertCount(50, $this->json($this->cup('POST', '/cup/refresh'))['task']['new']);
    }

    public function testATaskIsQueuedWithItsParameters(): void
    {
        $this->admin();

        $this->cup('POST', '/cup/task/run', ['form_params' => ['task' => \App\Domain\Tasks\SendJSONTask::class, 'params' => ['url' => 'http://127.0.0.1/user/subscriber', 'data' => ['a' => 1]]]]);

        $task = Task::where('action', \App\Domain\Tasks\SendJSONTask::class)->first();
        $this->assertNotNull($task);
        $this->assertSame('http://127.0.0.1/user/subscriber', $task->params['url']);
        $this->assertSame(['a' => '1'], $task->params['data']);
        $this->assertSame('queue', $task->status);
    }

    public function testTheParametersOfATaskThatAreNotAListDoNotBreakIt(): void
    {
        $this->admin();

        $response = $this->cup('POST', '/cup/task/run', ['form_params' => ['task' => SearchIndexTask::class, 'params' => 'just text']]);

        $this->assertSame(302, $response->getStatusCode(), $this->why($response));
        $this->assertSame(1, Task::count());
    }

    public function testATaskIsNotRunByALink(): void
    {
        $this->admin();

        $this->cup('GET', '/cup/task/run?task=' . urlencode(SearchIndexTask::class));

        $this->assertSame(0, Task::count());
    }

    // --------------------------------------------------------------------- logs

    private function log(string $name, array $lines): void
    {
        $this->admin();
        $this->logs[] = $path = LOG_DIR . '/' . $name;
        file_put_contents($path, implode("\n", $lines) . "\n");
    }

    public function testTheScheduledJobsPageRendersAndIgnoresUnknownJobs(): void
    {
        $response = $this->cup('GET', '/cup/schedule');
        $this->assertRenders($response);
        $this->assertStringContainsString('Scheduled jobs', (string) $response->getBody(), 'the link of the menu');

        $response = $this->cup('POST', '/cup/schedule', ['form_params' => ['key' => 'no-such-job', 'do' => 'run'], 'allow_redirects' => false]);
        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/cup/schedule', $response->getHeaderLine('Location'));
    }

    public function testTheLogPageShowsTheFilesNewestFirstWithTheirProblems(): void
    {
        $this->log('app-2001-01-01.log', ['[2001-01-01 10:00:00] WSE.INFO: old line {} []']);
        $this->log('app-2001-01-02.log', [
            '[2001-01-02 10:00:00] WSE.INFO: fine {} []',
            '[2001-01-02 10:00:01] WSE.WARNING: careful {} []',
            '[2001-01-02 10:00:02] WSE.ERROR: broke {} []',
            '[2001-01-02 10:00:03] WSE.CRITICAL: worse {} []',
            'a line that is not a log record',
        ]);

        $response = $this->cup('GET', '/cup/logs');
        $body = (string) $response->getBody();

        $this->assertRenders($response);
        $this->assertLessThan(strpos($body, 'app-2001-01-01.log'), strpos($body, 'app-2001-01-02.log'), 'the newer file is first');
        $this->assertStringContainsString('badge-danger ml-1">2<', $body, 'two errors (error and critical)');
        $this->assertStringContainsString('badge-warning ml-1">1<', $body);
        $this->assertStringContainsString('log-line--error', $body);
        $this->assertStringContainsString('log-line--warning', $body);
        $this->assertStringContainsString('log-line--plain', $body, 'a line that is no record is still shown');
        $this->assertStringContainsString('old line', $body);
    }

    public function testOnlyTheLastThousandLinesOfAFileAreShown(): void
    {
        $lines = [];
        foreach (range(1, 1100) as $i) {
            $lines[] = "[2001-01-03 10:00:00] WSE.INFO: line number {$i} {} []";
        }
        $this->log('app-2001-01-03.log', $lines);

        $body = (string) $this->cup('GET', '/cup/logs')->getBody();

        $this->assertStringNotContainsString('line number 100 ', $body, 'the beginning is cut');
        $this->assertStringContainsString('line number 101 ', $body);
        $this->assertStringContainsString('line number 1100 ', $body);
    }

    public function testWhatIsInTheLogIsShownAsText(): void
    {
        $this->log('app-2001-01-04.log', ['[2001-01-04 10:00:00] WSE.INFO: agent <script>alert("log")</script> {"ua":"<img src=x onerror=alert(1)>"} []']);

        $body = (string) $this->cup('GET', '/cup/logs')->getBody();

        $this->assertStringNotContainsString('<script>alert("log")', $body);
        $this->assertStringNotContainsString('<img src=x', $body);
        $this->assertStringContainsString('&lt;script&gt;', $body);
    }

    public function testFilesThatAreNotApplicationLogsAreNotListed(): void
    {
        $this->log('app-2001-01-05.log', ['[2001-01-05 10:00:00] WSE.INFO: shown {} []']);
        $this->log('worker-2001.log', ['[2001-01-05 10:00:00] WSE.INFO: other file {} []']);

        $body = (string) $this->cup('GET', '/cup/logs')->getBody();

        $this->assertStringContainsString('shown', $body);
        $this->assertStringNotContainsString('other file', $body);
    }

    public function testAnEmptyLogDoesNotBreakThePage(): void
    {
        $this->log('app-2001-01-06.log', []);

        $this->assertRenders($this->cup('GET', '/cup/logs'));
    }

    // ---------------------------------------------------------------- dashboard

    public function testTheSectionsFollowTheSwitchesOfTheModules(): void
    {
        $this->assertSame(
            ['publication' => true, 'page' => true, 'catalog' => true, 'guestbook' => true, 'form' => true, 'file' => true, 'review' => false],
            $this->dashboard()->enabled()
        );

        foreach (['publication', 'page', 'catalog', 'guestbook', 'form'] as $module) {
            $this->setParameter($module . '_is_enabled', 'no');
        }
        $this->setParameter('file_is_enabled', 'no');
        $this->setParameter('review_product_is_enabled', 'yes');

        $this->assertSame(
            ['publication' => false, 'page' => false, 'catalog' => false, 'guestbook' => false, 'form' => false, 'file' => false, 'review' => true],
            $this->dashboard()->enabled()
        );
    }

    public function testTheQueuesThatNeedTheAdministrator(): void
    {
        $this->admin();
        $this->makeUser(['username' => 'waiting', 'email' => 'waiting@example.com', 'status' => 'confirmation']);
        $tasks = $this->getService(TaskService::class);
        $tasks->create(['title' => 'Q', 'action' => SearchIndexTask::class]);
        $tasks->create(['title' => 'W', 'action' => SearchIndexTask::class, 'status' => TaskStatus::WORK]);
        $tasks->create(['title' => 'F', 'action' => SearchIndexTask::class, 'status' => TaskStatus::FAIL]);
        $this->getService(GuestBookService::class)->create(['name' => 'Ann', 'email' => 'ann@example.com', 'message' => 'Hello']);
        $this->getService(OrderService::class)->create(['email' => 'a@example.com', 'phone' => '+380501234567', 'delivery' => ['address' => 'x']]);

        $dashboard = $this->dashboard();
        $attention = $dashboard->attention($dashboard->enabled());

        $this->assertSame(1, $attention['users']);
        $this->assertSame(2, $attention['tasks_active']);
        $this->assertSame(1, $attention['tasks_error']);
        $this->assertSame(1, $attention['guestbook']);
        $this->assertSame(1, $attention['orders'], 'an order that nobody has looked at');
    }

    public function testAnOrderWithAStatusBeyondTheFirstIsNotWaiting(): void
    {
        $this->admin();
        $references = $this->getService(\App\Domain\Service\Reference\ReferenceService::class);
        $first = $references->create(['title' => 'New', 'type' => 'order_status', 'value' => [], 'order' => 1]);
        $second = $references->create(['title' => 'Shipped', 'type' => 'order_status', 'value' => [], 'order' => 2]);
        $orders = $this->getService(OrderService::class);
        $make = fn (?string $status) => $orders->create(['email' => 'a@example.com', 'phone' => '+380501234567', 'delivery' => ['address' => 'x'], 'status_uuid' => $status]);
        $make($first->uuid);
        $make(null);
        $make($second->uuid);

        $dashboard = $this->dashboard();

        $this->assertSame(2, $dashboard->attention($dashboard->enabled())['orders']);
    }

    public function testTheNumbersOfTheTiles(): void
    {
        $this->admin();
        $form = $this->fixtures()['form'];
        FormData::create(['form_uuid' => $form, 'data' => ['a' => 1], 'message' => 'real', 'is_spam' => false]);
        FormData::create(['form_uuid' => $form, 'data' => ['a' => 1], 'message' => 'junk', 'is_spam' => true]);
        $this->makeUser(['username' => 'banned', 'email' => 'banned@example.com', 'status' => 'block']);

        $dashboard = $this->dashboard();
        $kpi = $dashboard->kpi($dashboard->enabled(), datetime()->subDays(7));

        $this->assertSame(2, $kpi['form_data_total'], 'the submission of the fixtures and the real one count, the junk does not');
        $this->assertSame(User::where('status', 'work')->count(), $kpi['users_total'], 'only working accounts');
        $this->assertSame(User::count(), $kpi['users_week']);
        $this->assertSame(1, $kpi['files_total']);
        $this->assertSame((int) \App\Domain\Models\File::sum('size'), $kpi['files_size']);
        $this->assertSame(1, $kpi['guestbook_week']);
    }

    public function testTheRevenueOfTheLastThirtyDays(): void
    {
        $this->admin();
        $category = $this->getService(CategoryService::class)->create(['title' => 'Phones', 'address' => 'phones']);
        $phone = $this->getService(ProductService::class)->create(['title' => 'Phone', 'address' => 'phones/phone', 'category_uuid' => $category->uuid, 'type' => 'product', 'price' => 100]);
        $orders = $this->getService(OrderService::class);
        $make = fn (int $count) => $orders->create(['email' => 'a@example.com', 'phone' => '+380501234567', 'delivery' => ['address' => 'x'], 'products' => [$phone->uuid => ['count' => $count, 'price_type' => 'price']]]);
        $today = $make(2);
        $make(1);
        $old = $make(5);
        CatalogOrder::where('uuid', $old->uuid)->update(['date' => date('Y-m-d 12:00:00', strtotime('-45 days'))]);
        $yesterday = $make(3);
        CatalogOrder::where('uuid', $yesterday->uuid)->update(['date' => date('Y-m-d 12:00:00', strtotime('-1 day'))]);

        $revenue = $this->dashboard()->revenue();

        $this->assertEquals(600.0, $revenue['total'], '(2 + 1 + 3) * 100, the order of 45 days ago is not in it');
        $this->assertSame(3, $revenue['orders']);
        $this->assertSame(2, $revenue['today_orders']);
        $this->assertEqualsWithDelta(200.0, $revenue['avg_check'], 0.01);
        $this->assertCount(30, $revenue['daily']);
        $this->assertSame(date('Y-m-d'), end($revenue['daily'])['date']);
        $this->assertEquals(300.0, end($revenue['daily'])['sum']);
        $this->assertEquals(150.0, end($revenue['daily'])['average_check']);
        $this->assertNotNull($today);
    }

    public function testTheRevenueOfAShopWithoutOrdersIsZeroNotAnError(): void
    {
        $this->admin();

        $revenue = $this->dashboard()->revenue();

        $this->assertEquals(0.0, $revenue['total']);
        $this->assertSame(0, $revenue['orders']);
        $this->assertEquals(0.0, $revenue['avg_check']);
        $this->assertCount(30, $revenue['daily']);
    }

    public function testTheMainPageOpensOnAnEmptyShopAndAFullOne(): void
    {
        $this->assertRenders($this->cup('GET', '/cup'), 'empty');

        $this->fixtures();
        $this->assertRenders($this->cup('GET', '/cup'), 'full');
    }

    public function testTheMainPageLeavesOutTheSectionsThatAreSwitchedOff(): void
    {
        $this->fixtures();
        $this->cup('GET', '/cup');
        foreach (['catalog', 'guestbook', 'form', 'publication'] as $module) {
            $this->setParameter($module . '_is_enabled', 'no');
        }

        $response = $this->cup('GET', '/cup');

        $this->assertRenders($response);
        $this->assertStringNotContainsString('window.orders_revenue', (string) $response->getBody(), 'no revenue chart without the shop');
    }

    public function testTheRecentActivityHidesSpamAndEscapesNames(): void
    {
        $this->admin();
        $hostile = '<script>alert("d")</script>';
        $form = $this->fixtures()['form'];
        FormData::create(['form_uuid' => $form, 'data' => ['a' => 1], 'message' => 'spammy words', 'is_spam' => true, 'spam_reason' => 'x']);
        $this->getService(GuestBookService::class)->create(['name' => $hostile, 'email' => 'x@example.com', 'message' => 'm']);
        $this->makeUser(['username' => 'zz', 'email' => 'zz@example.com', 'firstname' => $hostile]);

        $body = (string) $this->cup('GET', '/cup')->getBody();

        $this->assertStringNotContainsString($hostile, $body);
        $this->assertStringNotContainsString('spammy words', $body);
        $this->assertSame(1, GuestBook::where('name', $hostile)->count());
    }

    public function testTheNotepadBelongsToTheAccount(): void
    {
        $this->admin();
        $this->setParameter('notepad_admin', 'Remember the milk');

        $this->assertStringContainsString('Remember the milk', (string) $this->cup('GET', '/cup')->getBody());

        $group = $this->getService(\App\Domain\Service\User\GroupService::class)->create(['title' => 'Second', 'access' => $this->cupRouteNames()]);
        $other = $this->makeUser(['username' => 'second', 'email' => 'second@example.com', 'group_uuid' => $group->uuid]);
        $cookie = $this->cookie($this->loginAs($other)['access_token']);

        $this->assertStringNotContainsString('Remember the milk', (string) $this->http('GET', '/cup', ['headers' => $cookie])->getBody());
    }
}
