<?php declare(strict_types=1);

namespace tests\Cup;

use App\Domain\Models\User;
use App\Domain\Models\UserGroup;
use App\Domain\Service\User\GroupService;

/**
 * The first start: /cup/system, open to anyone until the lock file exists
 *
 * Moves the lock file of the instance out of the way and puts it back, so it
 * must not run alongside anything else that uses the application
 *
 * @internal
 *
 * #[CoversNothing]
 */
class CupInstallerTest extends CupTestCase
{
    private string $backup;

    public function setUp(): void
    {
        parent::setUp();

        $this->backup = LOCK_FILE . '.tests';

        // a run that was killed halfway leaves the instance "not installed": heal it first
        if (is_file($this->backup)) {
            @unlink(LOCK_FILE);
            rename($this->backup, LOCK_FILE);
        }
    }

    public function tearDown(): void
    {
        if (is_file($this->backup)) {
            @unlink(LOCK_FILE);
            rename($this->backup, LOCK_FILE);
        }

        parent::tearDown();
    }

    private function uninstall(): void
    {
        $this->assertFileExists(LOCK_FILE, 'the test instance is installed');
        rename(LOCK_FILE, $this->backup);
    }

    private function install(array $user, string $path = '/cup/system/3'): \Psr\Http\Message\ResponseInterface
    {
        return $this->http('POST', $path, ['form_params' => ['user' => $user]]);
    }

    private function owner(array $extra = []): array
    {
        return $extra + ['username' => 'owner', 'email' => 'owner@example.com', 'password' => 'a long first password'];
    }

    public function testAnInstalledSiteClosesThePageToEveryone(): void
    {
        $this->assertFileExists(LOCK_FILE);

        $this->assertRedirectsTo($this->http('GET', '/cup/system'), '/cup');

        $post = $this->install($this->owner());
        $this->assertRedirectsTo($post, '/cup');
        $this->assertSame(0, User::count(), 'nobody was added');
        $this->assertSame(0, UserGroup::count());
    }

    public function testAnInstalledSiteShowsTheLoginPage(): void
    {
        $this->assertRenders($this->http('GET', '/cup/login'));
    }

    public function testBeforeTheInstallTheLoginPageSendsToTheInstaller(): void
    {
        $this->uninstall();

        $this->assertRedirectsTo($this->http('GET', '/cup/login'), '/cup/system');
    }

    public function testBeforeTheInstallAnyoneSeesTheSteps(): void
    {
        $this->uninstall();

        foreach (['/cup/system', '/cup/system/1', '/cup/system/2', '/cup/system/3'] as $path) {
            $this->assertRenders($this->http('GET', $path), $path);
        }

        $this->assertStringContainsString('name="user[username]"', (string) $this->http('GET', '/cup/system/3')->getBody());
    }

    public function testThePostInstallsTheFirstAdministrator(): void
    {
        $this->uninstall();

        $response = $this->install($this->owner());

        $this->assertRedirectsTo($response, '/cup');
        $this->assertFileExists(LOCK_FILE, 'the lock is written, the installer closes behind itself');
        $this->assertGreaterThan(time() - 60, (int) file_get_contents(LOCK_FILE));

        $group = UserGroup::firstWhere(['title' => 'Administrators']);
        $this->assertNotNull($group);
        $owner = User::firstWhere(['username' => 'owner']);
        $this->assertSame($group->uuid, $owner->group_uuid);
        $this->assertTrue(password_verify('a long first password', $owner->getAttributes()['password']));
    }

    public function testTheAdministratorsGroupMayUseEveryRouteOfThePanel(): void
    {
        $this->uninstall();
        $this->install($this->owner());

        $access = UserGroup::firstWhere(['title' => 'Administrators'])->access;

        $this->assertEqualsCanonicalizing($this->cupRouteNames(), array_values(array_filter($access, fn (string $name) => str_starts_with($name, 'cup:'))));
        $this->assertNotContains('cup:login', $access);
        $this->assertNotContains('cup:system', $access);
    }

    public function testTheNewAdministratorCanLogInAndOpenThePanel(): void
    {
        $this->uninstall();
        $this->install($this->owner());

        $login = $this->http('POST', '/cup/login', ['form_params' => ['identifier' => 'owner', 'password' => 'a long first password']]);
        $this->assertRedirectsTo($login, '/cup');

        $panel = $this->http('GET', '/cup', ['headers' => $this->cookie($this->cookiesOf($login)['access_token'])]);
        $this->assertRenders($panel);
    }

    public function testTheInstallerClosesBehindItself(): void
    {
        $this->uninstall();
        $this->install($this->owner());

        $second = $this->install($this->owner(['username' => 'intruder', 'email' => 'intruder@example.com']));

        $this->assertRedirectsTo($second, '/cup');
        $this->assertNull(User::firstWhere(['username' => 'intruder']));
    }

    public function testTheKeysOfTheInstanceAreNotReplaced(): void
    {
        $before = [sha1_file(VAR_DIR . '/private.secret.key'), sha1_file(VAR_DIR . '/public.secret.key')];
        $this->uninstall();

        $this->install($this->owner());

        $this->assertSame($before, [sha1_file(VAR_DIR . '/private.secret.key'), sha1_file(VAR_DIR . '/public.secret.key')], 'every issued token would stop working');
    }

    public function testAnEmptyFormIsReportedAndDoesNotCloseTheInstaller(): void
    {
        $this->uninstall();

        $response = $this->install([]);

        $this->assertEquals(200, $response->getStatusCode(), $this->why($response));
        $this->assertStringContainsString('has-error', (string) $response->getBody());
        $this->assertFileDoesNotExist(LOCK_FILE, 'the installer stays open until it succeeds');
        $this->assertSame(0, User::count());
    }

    public function testATakenUsernameOrAddressIsReportedPerField(): void
    {
        $this->uninstall();
        $this->makeUser(['username' => 'owner', 'email' => 'owner@example.com']);

        $same = $this->install($this->owner());
        $this->assertEquals(200, $same->getStatusCode());
        $this->assertStringContainsString('has-error', (string) $same->getBody());

        $email = $this->install($this->owner(['username' => 'other']));
        $this->assertStringContainsString('has-error', (string) $email->getBody());

        $this->assertFileDoesNotExist(LOCK_FILE);
        $this->assertSame(1, User::count());
    }

    public function testAnExistingAdministratorsGroupIsUsedAgain(): void
    {
        $this->uninstall();
        $existing = $this->getService(GroupService::class)->create(['title' => 'Administrators', 'access' => ['cup:main']]);

        $this->install($this->owner());

        $this->assertSame(1, UserGroup::where('title', 'Administrators')->count());
        $this->assertSame($existing->uuid, User::firstWhere(['username' => 'owner'])->group_uuid);
    }

    public function testAFailedTryAndASecondOneInstallOnce(): void
    {
        $this->uninstall();

        $this->install(['username' => '', 'email' => '', 'password' => 'x']);
        $this->assertFileDoesNotExist(LOCK_FILE);

        $this->assertRedirectsTo($this->install($this->owner()), '/cup');
        $this->assertSame(1, UserGroup::where('title', 'Administrators')->count(), 'the group of the first try is the group of the second');
        $this->assertSame(1, User::count());
    }
}
