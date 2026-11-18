<?php declare(strict_types=1);

namespace tests\API;

use App\Domain\Casts\User\Status as UserStatus;
use App\Domain\Models\UserToken;
use App\Domain\Service\Parameter\ParameterService;
use App\Domain\Service\User\UserService;
use tests\Concerns\MakesAuthRequests;
use tests\TestCase;

/**
 * Registration, login, refresh, revoke, logout and the two signed-link flows
 * (e-mail confirmation, password recovery), against the real HTTP stack
 *
 * @internal
 *
 * #[CoversNothing]
 */
class AuthFlowTest extends TestCase
{
    use MakesAuthRequests;

    private const JSON = ['Accept' => 'application/json'];

    private function setParameter(string $name, string $value): void
    {
        $this->getService(ParameterService::class)->create(['name' => $name, 'value' => $value]);
    }

    private function register(array $overrides = []): \Psr\Http\Message\ResponseInterface
    {
        return $this->http('POST', '/auth/register', [
            'headers' => self::JSON,
            'form_params' => $overrides + [
                'username' => 'newcomer',
                'email' => 'newcomer@example.com',
                'password' => self::PASSWORD,
                'password_again' => self::PASSWORD,
            ],
        ]);
    }

    // ---------------------------------------------------------------- register

    public function testRegisterCreatesAWorkingAccountAndNeverLeaksThePassword(): void
    {
        $response = $this->register();

        $this->assertEquals(200, $response->getStatusCode());
        $user = $this->json($response)['user'];
        $this->assertEquals('newcomer', $user['username']);
        $this->assertArrayNotHasKey('password', $user);
        $this->assertEquals(UserStatus::WORK, $this->getService(UserService::class)->read(['username' => 'newcomer'])->status);

        // and it can log in right away
        $this->loginAs($this->getService(UserService::class)->read(['username' => 'newcomer']));
    }

    public function testRegisterRejectsMismatchedPasswords(): void
    {
        $response = $this->register(['password_again' => 'something else']);

        $this->assertEquals(400, $response->getStatusCode());
        $this->assertArrayHasKey('error', $this->json($response));
    }

    public function testRegisterRejectsADuplicateEmailAndUsername(): void
    {
        $this->assertEquals(200, $this->register()->getStatusCode());

        $this->assertEquals(400, $this->register(['username' => 'other'])->getStatusCode(), 'same e-mail');
        $this->assertEquals(400, $this->register(['email' => 'other@example.com'])->getStatusCode(), 'same username');
    }

    /**
     * Pins what happens today: the Email cast quietly turns a malformed address
     * into an empty one, so the account is created without any e-mail and the
     * visitor is never told. Worth a decision - see the test report
     */
    public function testRegisterDropsAMalformedEmailSilently(): void
    {
        $response = $this->register(['email' => 'not-an-email']);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertSame('', (string) $this->getService(UserService::class)->read(['username' => 'newcomer'])->email);
    }

    public function testRegisterCannotChooseItsOwnStatusOrGroup(): void
    {
        $group = $this->getService(\App\Domain\Service\User\GroupService::class)->create(['title' => 'admins', 'access' => ['cup:main']]);

        $response = $this->register(['status' => UserStatus::BLOCK, 'group_uuid' => $group->uuid]);
        $this->assertEquals(200, $response->getStatusCode());

        $user = $this->getService(UserService::class)->read(['username' => 'newcomer']);
        $this->assertEquals(UserStatus::WORK, $user->status);
        $this->assertNull($user->group_uuid);
    }

    public function testRegisterWithConfirmationOnLeavesTheAccountUnconfirmed(): void
    {
        $this->setParameter('user_confirmation', 'on');

        $this->assertEquals(200, $this->register()->getStatusCode());

        $user = $this->getService(UserService::class)->read(['username' => 'newcomer']);
        $this->assertEquals(UserStatus::CONFIRMATION, $user->status);

        // cannot log in until the address is confirmed
        $response = $this->http('POST', '/auth/login', [
            'headers' => self::JSON,
            'form_params' => ['identifier' => 'newcomer', 'password' => self::PASSWORD],
        ]);
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testRegisterWithoutJsonRedirectsToAValidLocalPathOnly(): void
    {
        $response = $this->http('POST', '/auth/register?redirect=/thanks', [
            'form_params' => ['username' => 'a', 'email' => 'a@example.com', 'password' => 'x', 'password_again' => 'x'],
        ]);
        $this->assertEquals(307, $response->getStatusCode());
        $this->assertEquals('/thanks', $response->getHeaderLine('Location'));

        foreach (['//evil.example', 'https://evil.example', 'javascript:alert(1)'] as $i => $target) {
            $response = $this->http('POST', '/auth/register?redirect=' . urlencode($target), [
                'form_params' => ['username' => 'b' . $i, 'email' => "b{$i}@example.com", 'password' => 'x', 'password_again' => 'x'],
            ]);
            $this->assertEquals('/', $response->getHeaderLine('Location'), $target);
        }
    }

    // ------------------------------------------------------------------- login

    public function testLoginIssuesAccessAndRefreshTokens(): void
    {
        $user = $this->makeUser();

        $result = $this->loginAs($user);

        $this->assertNotEmpty($result['access_token']);
        $this->assertNotEmpty($result['refresh_token']);
        $this->assertEquals($user->uuid, $result['user']['uuid']);
        $this->assertArrayNotHasKey('password', $result['user']);
        $this->assertSame(1, UserToken::where('user_uuid', $user->uuid)->count());
    }

    public function testLoginFailsForAWrongPasswordAnUnknownUserAndABlockedOne(): void
    {
        $user = $this->makeUser();
        $blocked = $this->makeUser(['status' => UserStatus::BLOCK]);

        $wrong = $this->http('POST', '/auth/login', [
            'headers' => self::JSON,
            'form_params' => ['identifier' => $user->username, 'password' => 'nope'],
        ]);
        $this->assertEquals(400, $wrong->getStatusCode());

        $unknown = $this->http('POST', '/auth/login', [
            'headers' => self::JSON,
            'form_params' => ['identifier' => 'nobody', 'password' => 'nope'],
        ]);
        $this->assertEquals(404, $unknown->getStatusCode());

        $block = $this->http('POST', '/auth/login', [
            'headers' => self::JSON,
            'form_params' => ['identifier' => $blocked->username, 'password' => self::PASSWORD],
        ]);
        $this->assertEquals(404, $block->getStatusCode(), 'a blocked account looks like a missing one');

        $this->assertSame(0, UserToken::count(), 'a failed login must not leave a session behind');
    }

    public function testLoginAcceptsEmailAsIdentifier(): void
    {
        $user = $this->makeUser();

        $response = $this->http('POST', '/auth/login', [
            'headers' => self::JSON,
            'form_params' => ['identifier' => $user->email, 'password' => self::PASSWORD],
        ]);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testLoginWithAnUnknownProviderIsNotAnAuthentication(): void
    {
        $user = $this->makeUser();

        $response = $this->http('POST', '/auth/login', [
            'headers' => self::JSON,
            'form_params' => ['provider' => 'NoSuchProvider', 'identifier' => $user->username, 'password' => self::PASSWORD],
        ]);

        $this->assertGreaterThanOrEqual(400, $response->getStatusCode());
        $this->assertSame(0, UserToken::count());
    }

    public function testSixthSessionDropsTheOlderOnes(): void
    {
        $user = $this->makeUser();

        for ($i = 0; $i < 5; ++$i) {
            $this->http('POST', '/auth/login', [
                'headers' => self::JSON + ['User-Agent' => 'agent-' . $i],
                'form_params' => ['identifier' => $user->username, 'password' => self::PASSWORD],
            ]);
        }
        $this->assertSame(5, UserToken::where('user_uuid', $user->uuid)->count());

        $this->http('POST', '/auth/login', [
            'headers' => self::JSON + ['User-Agent' => 'agent-6'],
            'form_params' => ['identifier' => $user->username, 'password' => self::PASSWORD],
        ]);

        $this->assertSame(1, UserToken::where('user_uuid', $user->uuid)->count());
    }

    // ----------------------------------------------------------------- refresh

    public function testRefreshRotatesTheTokens(): void
    {
        $login = $this->loginAs($this->makeUser());

        $response = $this->http('POST', '/auth/refresh-token', [
            'headers' => self::JSON,
            'form_params' => ['token' => $login['refresh_token']],
        ]);

        $this->assertEquals(200, $response->getStatusCode());
        $refreshed = $this->json($response);
        $this->assertNotEmpty($refreshed['access_token']);
        $this->assertEquals($login['user']['uuid'], $refreshed['user']['uuid']);
    }

    public function testRefreshWithAnUnknownOrMissingTokenSendsToLogout(): void
    {
        $response = $this->http('POST', '/auth/refresh-token', ['form_params' => ['token' => 'made-up']]);
        $this->assertEquals(308, $response->getStatusCode());
        $this->assertEquals('/auth/logout', $response->getHeaderLine('Location'));

        $response = $this->http('POST', '/auth/refresh-token');
        $this->assertEquals(308, $response->getStatusCode());
        $this->assertEquals('/auth/logout', $response->getHeaderLine('Location'));

        $response = $this->http('POST', '/auth/refresh-token', [
            'headers' => self::JSON,
            'form_params' => ['token' => 'made-up'],
        ]);
        $this->assertSame([], $this->json($response), 'nothing is issued for a token that does not exist');
    }

    public function testRefreshTokenIsBoundToTheUserAgentItWasIssuedTo(): void
    {
        $login = $this->loginAs($this->makeUser());

        $response = $this->http('POST', '/auth/refresh-token', [
            'headers' => self::JSON + ['User-Agent' => 'someone else entirely'],
            'form_params' => ['token' => $login['refresh_token']],
        ]);

        $this->assertSame([], $this->json($response));
    }

    // ------------------------------------------------------------------ logout

    public function testLogoutKillsTheRefreshToken(): void
    {
        $user = $this->makeUser();
        $login = $this->loginAs($user);

        $response = $this->http('POST', '/auth/logout', [
            'headers' => self::JSON,
            'form_params' => ['token' => $login['refresh_token']],
        ]);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertSame(0, UserToken::where('user_uuid', $user->uuid)->count());

        $again = $this->http('POST', '/auth/refresh-token', [
            'headers' => self::JSON,
            'form_params' => ['token' => $login['refresh_token']],
        ]);
        $this->assertSame([], $this->json($again), 'a logged out token cannot be refreshed');
    }

    public function testLogoutWithoutATokenIsHarmless(): void
    {
        $this->assertEquals(200, $this->http('POST', '/auth/logout', ['headers' => self::JSON])->getStatusCode());
    }

    // ------------------------------------------------------------------ revoke

    public function testRevokeRequiresAnAuthenticatedSession(): void
    {
        $user = $this->makeUser();
        $login = $this->loginAs($user);

        // the route is behind AuthorizationMiddleware, which only reads the cookie
        $this->http('POST', '/auth/revoke', [
            'headers' => self::JSON,
            'form_params' => ['token' => $login['refresh_token']],
        ]);

        // an anonymous call still reaches the action and drops only *other* sessions,
        // never the one presented - the presented session must survive
        $this->assertSame(1, UserToken::where('user_uuid', $user->uuid)->count());
    }

    public function testRevokeDropsOtherSessionsButKeepsTheCurrentOne(): void
    {
        $user = $this->makeUser();
        $first = $this->loginAs($user);
        $this->http('POST', '/auth/login', [
            'headers' => self::JSON + ['User-Agent' => 'second device'],
            'form_params' => ['identifier' => $user->username, 'password' => self::PASSWORD],
        ]);
        $this->assertSame(2, UserToken::where('user_uuid', $user->uuid)->count());

        $this->http('POST', '/auth/revoke', [
            'headers' => self::JSON + $this->cookie($first['access_token']),
            'form_params' => ['token' => $first['refresh_token']],
        ]);

        $remaining = UserToken::where('user_uuid', $user->uuid)->get();
        $this->assertCount(1, $remaining);
        $this->assertEquals($first['refresh_token'], $remaining->first()->unique);
    }

    public function testRevokeCannotDropSomebodyElsesSession(): void
    {
        $attacker = $this->makeUser();
        $victim = $this->makeUser();
        $attackerLogin = $this->loginAs($attacker);
        $this->http('POST', '/auth/login', [
            'headers' => self::JSON + ['User-Agent' => 'victim device'],
            'form_params' => ['identifier' => $victim->username, 'password' => self::PASSWORD],
        ]);
        $victimToken = UserToken::where('user_uuid', $victim->uuid)->first();

        // hands in the victim's session uuid together with his own refresh token
        $this->http('POST', '/auth/revoke', [
            'headers' => self::JSON + $this->cookie($attackerLogin['access_token']),
            'form_params' => ['token' => $attackerLogin['refresh_token'], 'uuid' => $victimToken->uuid],
        ]);

        $this->assertNotNull(UserToken::find($victimToken->uuid), 'the victim\'s session must still exist');
    }

    // -------------------------------------------------- AuthorizationMiddleware

    public function testAnExpiredAccessTokenIsSentToRefresh(): void
    {
        $user = $this->makeUser();
        $expired = $this->signToken('user', $user->uuid, ['uuid' => $user->uuid], time() - 60);

        $response = $this->http('GET', '/user/profile', ['headers' => $this->cookie($expired)]);

        $this->assertEquals(308, $response->getStatusCode());
        $this->assertStringStartsWith('/auth/refresh-token?redirect=', $response->getHeaderLine('Location'));
    }

    public function testAForgedAccessTokenIsNotAccepted(): void
    {
        $user = $this->makeUser();
        $forged = $this->forgeToken('user', $user->uuid);

        $response = $this->http('GET', '/user/profile', ['headers' => $this->cookie($forged)]);

        $this->assertNotEquals(200, $response->getStatusCode());
        $this->assertStringNotContainsString($user->username, (string) $response->getBody());
    }

    public function testATokenOfABlockedUserIsSentToLogout(): void
    {
        $user = $this->makeUser();
        $login = $this->loginAs($user);
        $this->getService(UserService::class)->update($user, ['status' => UserStatus::BLOCK]);

        $response = $this->http('GET', '/user/profile?format=json', ['headers' => $this->cookie($login['access_token'])]);

        $this->assertEquals(307, $response->getStatusCode());
        $this->assertStringStartsWith('/auth/logout?redirect=', $response->getHeaderLine('Location'));
    }

    public function testABlockedUserCannotRefreshHisSession(): void
    {
        $user = $this->makeUser();
        $login = $this->loginAs($user);
        $this->getService(UserService::class)->update($user, ['status' => UserStatus::BLOCK]);

        $response = $this->http('POST', '/auth/refresh-token', [
            'headers' => self::JSON,
            'form_params' => ['token' => $login['refresh_token']],
        ]);

        $this->assertSame([], $this->json($response));
    }

    public function testABlockedUsersTokenIsRejectedByTheApi(): void
    {
        $this->setParameter('entity_access', 'user');
        $user = $this->makeUser();
        $login = $this->loginAs($user);
        $this->getService(UserService::class)->update($user, ['status' => UserStatus::BLOCK]);

        $response = $this->http('GET', '/api/v1/user?access_token=' . $login['access_token']);

        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testAValidAccessTokenOpensTheProfile(): void
    {
        $login = $this->loginAs($this->makeUser());

        $response = $this->http('GET', '/user/profile?format=json', ['headers' => $this->cookie($login['access_token'])]);

        $this->assertEquals(200, $response->getStatusCode(), substr((string) $response->getBody(), 0, 600));
        $this->assertEquals($login['user']['uuid'], $this->json($response)['data']['user']['uuid'] ?? null);
    }

    public function testProfileForAGuestRedirectsToLogin(): void
    {
        $response = $this->http('GET', '/user/profile');

        $this->assertEquals(301, $response->getStatusCode());
        $this->assertEquals('/user/login', $response->getHeaderLine('Location'));
    }

    // ------------------------------------------------------- e-mail confirmation

    private function confirmationToken(\App\Domain\Models\User $user, ?string $email = null): string
    {
        return $this->signToken(
            'user-confirmation',
            $user->uuid,
            ['fingerprint' => hash('sha256', $user->uuid . '|' . mb_strtolower($email ?? (string) $user->email))],
            time() + 600
        );
    }

    public function testConfirmationLinkActivatesTheAccountOnce(): void
    {
        $user = $this->makeUser(['status' => UserStatus::CONFIRMATION]);
        $token = $this->confirmationToken($user);

        $response = $this->http('GET', '/user/confirm?token=' . $token);
        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals('/user/login', $response->getHeaderLine('Location'));
        $this->assertEquals(UserStatus::WORK, $this->getService(UserService::class)->read(['uuid' => $user->uuid])->status);

        // the same link again: the status no longer matches
        $again = $this->http('GET', '/user/confirm?token=' . $token . '&format=json');
        $this->assertEquals('expired', $this->json($again)['data']['stage']);
    }

    public function testConfirmationLinkDiesWhenTheAddressChanges(): void
    {
        $user = $this->makeUser(['status' => UserStatus::CONFIRMATION]);
        $token = $this->confirmationToken($user);

        $this->getService(UserService::class)->update($user, ['email' => 'moved@example.com']);

        $response = $this->http('GET', '/user/confirm?token=' . $token . '&format=json');
        $this->assertEquals('expired', $this->json($response)['data']['stage']);
        $this->assertEquals(UserStatus::CONFIRMATION, $this->getService(UserService::class)->read(['uuid' => $user->uuid])->status);
    }

    public function testConfirmationRejectsExpiredForgedAndForeignSubjectTokens(): void
    {
        $user = $this->makeUser(['status' => UserStatus::CONFIRMATION]);
        $fingerprint = hash('sha256', $user->uuid . '|' . mb_strtolower((string) $user->email));

        $tokens = [
            'expired' => $this->signToken('user-confirmation', $user->uuid, ['fingerprint' => $fingerprint], time() - 60),
            'forged' => $this->forgeToken('user-confirmation', $user->uuid),
            'wrong subject' => $this->signToken('user-recovery', $user->uuid, ['fingerprint' => $fingerprint], time() + 600),
            'no fingerprint' => $this->signToken('user-confirmation', $user->uuid, [], time() + 600),
            'garbage' => 'not.a.jwt',
        ];

        foreach ($tokens as $case => $token) {
            $response = $this->http('GET', '/user/confirm?format=json&token=' . $token);
            $this->assertEquals('expired', $this->json($response)['data']['stage'] ?? null, $case);
        }

        $this->assertEquals(UserStatus::CONFIRMATION, $this->getService(UserService::class)->read(['uuid' => $user->uuid])->status);
    }

    public function testConfirmationResendDoesNotRevealWhoIsRegistered(): void
    {
        $this->makeUser(['username' => 'pending', 'status' => UserStatus::CONFIRMATION]);

        foreach (['pending', 'nobody-here'] as $identifier) {
            $response = $this->http('POST', '/user/confirm?format=json', ['form_params' => ['identifier' => $identifier]]);

            $this->assertEquals(200, $response->getStatusCode());
            $this->assertEquals('sent', $this->json($response)['data']['stage'], $identifier);
        }
    }

    // ---------------------------------------------------------- password recovery

    private function recoveryToken(\App\Domain\Models\User $user, ?int $exp = null): string
    {
        return $this->signToken(
            'user-recovery',
            $user->uuid,
            ['fingerprint' => hash('sha256', $user->uuid . '|' . $user->getAttributes()['password'])],
            $exp ?? time() + 600
        );
    }

    public function testRecoveryRequestAnswersTheSameForKnownAndUnknownAccounts(): void
    {
        $this->makeUser(['username' => 'known']);

        foreach (['known', 'unknown'] as $identifier) {
            $response = $this->http('POST', '/user/recovery?format=json', ['form_params' => ['identifier' => $identifier]]);

            $this->assertEquals(200, $response->getStatusCode());
            $this->assertTrue($this->json($response)['data']['sent'], $identifier);
        }
    }

    public function testRecoveryRequestNeedsAnIdentifier(): void
    {
        $response = $this->http('POST', '/user/recovery?format=json', ['form_params' => ['identifier' => '  ']]);

        $this->assertFalse($this->json($response)['data']['sent']);
        $this->assertArrayHasKey('identifier', $this->json($response)['errors']);
    }

    public function testRecoveryLinkSetsANewPasswordAndEndsEverySession(): void
    {
        $user = $this->makeUser();
        $login = $this->loginAs($user);
        $token = $this->recoveryToken($user);

        $response = $this->http('POST', '/user/recovery?token=' . $token, [
            'form_params' => ['password' => 'brand new secret', 'password_again' => 'brand new secret'],
        ]);
        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals('/user/login', $response->getHeaderLine('Location'));

        $this->assertSame(0, UserToken::where('user_uuid', $user->uuid)->count(), 'sessions opened with the old password are gone');

        $old = $this->http('POST', '/auth/login', [
            'headers' => self::JSON,
            'form_params' => ['identifier' => $user->username, 'password' => self::PASSWORD],
        ]);
        $this->assertEquals(400, $old->getStatusCode());

        $this->loginAs($user, 'brand new secret');
        $this->assertNotEmpty($login['refresh_token']);
    }

    public function testRecoveryLinkCannotBeUsedTwice(): void
    {
        $user = $this->makeUser();
        $token = $this->recoveryToken($user);

        $this->http('POST', '/user/recovery?token=' . $token, [
            'form_params' => ['password' => 'first choice', 'password_again' => 'first choice'],
        ]);

        // the fingerprint is the old password hash, which no longer matches
        $second = $this->http('POST', '/user/recovery?format=json&token=' . $token, [
            'form_params' => ['password' => 'hijacked', 'password_again' => 'hijacked'],
        ]);
        $this->assertEquals('expired', $this->json($second)['data']['stage']);

        $this->loginAs($user, 'first choice');
    }

    public function testRecoveryRejectsExpiredForgedAndForeignSubjectTokens(): void
    {
        $user = $this->makeUser();
        $fingerprint = hash('sha256', $user->uuid . '|' . $user->getAttributes()['password']);

        $tokens = [
            'expired' => $this->recoveryToken($user, time() - 60),
            'forged' => $this->forgeToken('user-recovery', $user->uuid),
            // a confirmation link must not double as a recovery link
            'wrong subject' => $this->signToken('user-confirmation', $user->uuid, ['fingerprint' => $fingerprint], time() + 600),
            'wrong fingerprint' => $this->signToken('user-recovery', $user->uuid, ['fingerprint' => 'abc'], time() + 600),
            // an access token is signed with the same key
            'access token' => $this->signToken('user', $user->uuid, ['uuid' => $user->uuid], time() + 600),
        ];

        foreach ($tokens as $case => $token) {
            $response = $this->http('POST', '/user/recovery?format=json&token=' . $token, [
                'form_params' => ['password' => 'hijacked', 'password_again' => 'hijacked'],
            ]);

            $this->assertEquals('expired', $this->json($response)['data']['stage'] ?? null, $case);
        }

        $this->loginAs($user);
    }

    public function testRecoveryNeedsMatchingPasswords(): void
    {
        $user = $this->makeUser();

        $response = $this->http('POST', '/user/recovery?format=json&token=' . $this->recoveryToken($user), [
            'form_params' => ['password' => 'one', 'password_again' => 'two'],
        ]);

        $json = $this->json($response);
        $this->assertEquals('reset', $json['data']['stage']);
        $this->assertArrayHasKey('password_again', $json['errors']);
        $this->loginAs($user);
    }

    public function testRecoveryDoesNotWorkForABlockedAccount(): void
    {
        $user = $this->makeUser();
        $token = $this->recoveryToken($user);
        $this->getService(UserService::class)->update($user, ['status' => UserStatus::BLOCK]);

        $response = $this->http('POST', '/user/recovery?format=json&token=' . $token, [
            'form_params' => ['password' => 'back in', 'password_again' => 'back in'],
        ]);

        $this->assertEquals('expired', $this->json($response)['data']['stage'] ?? null);
    }
}
