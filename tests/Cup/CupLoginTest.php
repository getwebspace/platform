<?php declare(strict_types=1);

namespace tests\Cup;

use App\Domain\Casts\User\Status as UserStatus;
use App\Domain\Models\UserToken;
use App\Domain\Service\Parameter\ParameterService;

/**
 * The way into the panel: the login page, the forbidden page and what a login leaves behind
 *
 * @internal
 *
 * #[CoversNothing]
 */
class CupLoginTest extends CupTestCase
{
    private function login(array $form, string $query = ''): \Psr\Http\Message\ResponseInterface
    {
        return $this->http('POST', '/cup/login' . $query, ['form_params' => $form]);
    }

    private function setParameter(string $name, string $value): void
    {
        $this->getService(ParameterService::class)->create(['name' => $name, 'value' => $value]);
        $this->getService(\Illuminate\Cache\ArrayStore::class)->flush();
    }

    public function testTheLoginPageAsksForTheIdentifierAndThePassword(): void
    {
        $response = $this->http('GET', '/cup/login');
        $body = (string) $response->getBody();

        $this->assertRenders($response);
        $this->assertStringContainsString('name="identifier"', $body);
        $this->assertStringContainsString('name="password"', $body);
        $this->assertStringContainsString('type="password"', $body);
        $this->assertStringNotContainsString('has-error', $body, 'a fresh page greets nobody with an error');
    }

    public function testTheForbiddenPageIsOpenToAnyoneAndSays403(): void
    {
        $guest = $this->http('GET', '/cup/forbidden');
        $this->assertEquals(403, $guest->getStatusCode());
        $this->assertStringContainsString('</html>', (string) $guest->getBody());

        $this->assertEquals(403, $this->cup('GET', '/cup/forbidden')->getStatusCode(), 'an administrator may see it too');
    }

    public function testALoginSetsBothCookiesAndOpensThePanel(): void
    {
        $user = $this->makeUser();
        $this->assertSame(0, UserToken::count());

        $response = $this->login(['identifier' => $user->username, 'password' => self::PASSWORD]);

        $this->assertRedirectsTo($response, '/cup');
        $cookies = $this->cookiesOf($response);
        $this->assertNotEmpty($cookies['access_token'] ?? '');
        $this->assertNotEmpty($cookies['refresh_token'] ?? '');
        $this->assertSame(1, UserToken::where('user_uuid', $user->uuid)->count());
    }

    public function testTheCookiesCannotBeReadByAScriptAndTheRefreshOneStaysOnItsPath(): void
    {
        $user = $this->makeUser();

        $headers = $this->login(['identifier' => $user->username, 'password' => self::PASSWORD])->getHeader('Set-Cookie');

        $access = current(array_filter($headers, fn (string $line) => str_starts_with($line, 'access_token=')));
        $refresh = current(array_filter($headers, fn (string $line) => str_starts_with($line, 'refresh_token=')));
        foreach ([$access, $refresh] as $cookie) {
            $this->assertStringContainsStringIgnoringCase('httponly', $cookie);
            $this->assertStringContainsStringIgnoringCase('samesite=lax', $cookie);
        }
        $this->assertStringContainsString('path=/;', str_replace('Path', 'path', $access . ';'));
        $this->assertStringContainsString('path=/auth', str_replace('Path', 'path', $refresh));
    }

    public function testWhoeverLogsInWithAnAdministratorsGroupCanUseThePanelAtOnce(): void
    {
        $this->admin();
        $login = $this->login(['identifier' => 'admin', 'password' => self::PASSWORD]);

        $cookie = $this->cookie($this->cookiesOf($login)['access_token']);

        $this->assertRenders($this->http('GET', '/cup', ['headers' => $cookie]));
    }

    public function testAnyIdentifierWorks(): void
    {
        $user = $this->makeUser(['username' => 'ann', 'email' => 'ann@example.com', 'phone' => '+380501234567']);

        foreach (['ann', 'ann@example.com', 'ANN@EXAMPLE.COM', '+380501234567'] as $identifier) {
            $this->assertRedirectsTo($this->login(['identifier' => $identifier, 'password' => self::PASSWORD]), '/cup', $identifier);
        }
        $this->assertNotNull($user);
    }

    public function testTheWayBackIsKeptButOnlyOnTheSite(): void
    {
        $user = $this->makeUser();
        $form = ['identifier' => $user->username, 'password' => self::PASSWORD];

        $this->assertRedirectsTo($this->login($form, '?redirect=/cup/page'), '/cup/page');

        foreach (['//evil.example/x', 'https://evil.example', '/\\evil.example', 'javascript:alert(1)'] as $target) {
            $this->assertRedirectsTo($this->login($form, '?redirect=' . urlencode($target)), '/cup', $target);
        }
    }

    public function testTheGuestWhoWasSentToTheLoginPageGoesBackAfterwards(): void
    {
        $admin = $this->makeUser(['username' => 'boss', 'email' => 'boss@example.com']);
        $guest = $this->http('GET', '/cup/page');
        $this->assertSame('/cup/login?redirect=/cup/page', $guest->getHeaderLine('Location'));

        $response = $this->login(['identifier' => $admin->username, 'password' => self::PASSWORD], '?redirect=/cup/page');

        $this->assertSame('/cup/page', $response->getHeaderLine('Location'));
    }

    public function testAWrongPasswordShowsAnErrorAndSetsNoCookie(): void
    {
        $user = $this->makeUser();

        $response = $this->login(['identifier' => $user->username, 'password' => 'nope']);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('has-error', (string) $response->getBody());
        $this->assertArrayNotHasKey('access_token', $this->cookiesOf($response));
        $this->assertSame(0, UserToken::count());
    }

    public function testAFailedLoginDoesNotPrintThePasswordBackIntoThePage(): void
    {
        $user = $this->makeUser();

        $body = (string) $this->login(['identifier' => $user->username, 'password' => 'my secret attempt'])->getBody();

        $this->assertStringContainsString('value="' . $user->username . '"', $body, 'the login is kept to save the typing');
        $this->assertStringNotContainsString('my secret attempt', $body);
    }

    public function testUnknownBlockedAndUnconfirmedAccountsAreTurnedBackWithAnError(): void
    {
        $blocked = $this->makeUser(['username' => 'blocked', 'email' => 'b@example.com', 'status' => UserStatus::BLOCK]);
        $waiting = $this->makeUser(['username' => 'waiting', 'email' => 'w@example.com', 'status' => UserStatus::CONFIRMATION]);

        foreach (['nobody', $blocked->username, $waiting->username] as $identifier) {
            $response = $this->login(['identifier' => $identifier, 'password' => self::PASSWORD]);

            $this->assertEquals(200, $response->getStatusCode(), $identifier);
            $this->assertStringContainsString('has-error', (string) $response->getBody(), $identifier);
            $this->assertArrayNotHasKey('access_token', $this->cookiesOf($response), $identifier);
        }
    }

    public function testAnEmptyFormIsRefused(): void
    {
        $response = $this->login(['identifier' => '', 'password' => '']);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertArrayNotHasKey('access_token', $this->cookiesOf($response));
    }

    public function testRepeatedFailuresLockTheAccountOutEvenForTheRightPassword(): void
    {
        $this->setParameter('user_login_attempts', '3');
        $user = $this->makeUser();

        for ($i = 0; $i < 3; ++$i) {
            $this->login(['identifier' => $user->username, 'password' => 'nope']);
        }

        $response = $this->login(['identifier' => $user->username, 'password' => self::PASSWORD]);

        $this->assertStringContainsString('Too many attempts', (string) $response->getBody());
        $this->assertArrayNotHasKey('access_token', $this->cookiesOf($response));
    }

    public function testTheLockOutIsOfOneAccountNotOfEveryone(): void
    {
        $this->setParameter('user_login_attempts', '2');
        $victim = $this->makeUser();
        $other = $this->makeUser();

        for ($i = 0; $i < 2; ++$i) {
            $this->login(['identifier' => $victim->username, 'password' => 'nope']);
        }

        $this->assertRedirectsTo($this->login(['identifier' => $other->username, 'password' => self::PASSWORD]), '/cup');
    }

    public function testASuccessfulLoginForgetsTheFailures(): void
    {
        $this->setParameter('user_login_attempts', '3');
        $user = $this->makeUser();

        for ($round = 0; $round < 3; ++$round) {
            $this->login(['identifier' => $user->username, 'password' => 'nope']);
            $this->login(['identifier' => $user->username, 'password' => 'nope']);

            $this->assertRedirectsTo($this->login(['identifier' => $user->username, 'password' => self::PASSWORD]), '/cup', 'round ' . $round);
        }
    }

    public function testThePanelLockOutIsNotTheSitesLockOut(): void
    {
        $this->setParameter('user_login_attempts', '2');
        $user = $this->makeUser();

        for ($i = 0; $i < 2; ++$i) {
            $this->login(['identifier' => $user->username, 'password' => 'nope']);
        }

        // the customer login page counts on its own
        $site = $this->http('POST', '/user/login', ['form_params' => ['username' => $user->username, 'password' => self::PASSWORD]]);
        $this->assertEquals(302, $site->getStatusCode());
    }

    public function testASessionOfABlockedAdministratorIsNotAPanelSession(): void
    {
        $this->admin();
        $login = $this->login(['identifier' => 'admin', 'password' => self::PASSWORD]);
        $cookie = $this->cookie($this->cookiesOf($login)['access_token']);
        $this->getService(\App\Domain\Service\User\UserService::class)->update(
            \App\Domain\Models\User::where('username', 'admin')->first(),
            ['status' => UserStatus::BLOCK]
        );

        $response = $this->http('GET', '/cup', ['headers' => $cookie]);

        $this->assertEquals(307, $response->getStatusCode());
    }

    public function testTheLogoutEndsTheSessionForTheRefreshToken(): void
    {
        $user = $this->makeUser();
        $login = $this->login(['identifier' => $user->username, 'password' => self::PASSWORD]);
        $refresh = $this->cookiesOf($login)['refresh_token'];

        $this->http('POST', '/auth/logout', ['form_params' => ['token' => $refresh]]);

        $this->assertSame(0, UserToken::where('user_uuid', $user->uuid)->count());
    }
}
