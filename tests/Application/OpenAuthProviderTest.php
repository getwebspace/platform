<?php declare(strict_types=1);

namespace tests\Application;

use App\Application\Auth;
use App\Application\Auth\OpenAuthProvider;
use App\Domain\Casts\User\Status as UserStatus;
use App\Domain\Exceptions\HttpRedirectException;
use App\Domain\Exceptions\WrongAuthProviderException;
use App\Domain\Models\User;
use App\Domain\Models\UserToken;
use App\Domain\Plugin\AbstractOAuthPlugin;
use App\Domain\Service\Parameter\ParameterService;
use App\Domain\Service\User\Exception\EmailAlreadyExistsException;
use App\Domain\Service\User\Exception\UserNotFoundException;
use App\Domain\Service\User\UserService;
use Psr\Container\ContainerInterface;
use tests\Concerns\MakesAuthRequests;
use tests\TestCase;

/**
 * Signing in through another service (Google, Facebook...): the provider behind a plugin, driven here by a fake one
 *
 * @internal
 *
 * #[CoversNothing]
 */
class OpenAuthProviderTest extends TestCase
{
    use MakesAuthRequests;

    private FakeOAuthPlugin $plugin;

    private OpenAuthProvider $provider;

    public function setUp(): void
    {
        parent::setUp();

        $_SESSION = [];
        $container = $this->getService(ContainerInterface::class);
        $this->plugin = new FakeOAuthPlugin($container);
        $this->provider = new OpenAuthProvider($container, $this->plugin);
    }

    public function tearDown(): void
    {
        $_SESSION = [];

        parent::tearDown();
    }

    private function params(string $redirect = '/auth/login'): array
    {
        return ['redirect' => $redirect, 'agent' => 'test', 'ip' => '127.0.0.1', 'comment' => ''];
    }

    /**
     * The visit to the provider and the way back with a code
     */
    private function comeBack(array $info = ['unique' => 'g-100', 'username' => 'ann', 'email' => 'ann@gmail.example', 'firstname' => 'Ann']): User
    {
        $this->plugin->info = $info;

        try {
            $this->provider->login([], $this->params());
            $this->fail('the first visit sends to the provider');
        } catch (HttpRedirectException) {
            // to the provider
        }

        return $this->provider->login(['state' => $_SESSION['auth_state'], 'code' => 'the-code'], $this->params());
    }

    // ------------------------------------------------------------ there and back

    public function testTheFirstVisitSendsToTheProviderWithAStateToCheckLater(): void
    {
        $this->getService(ParameterService::class)->create(['name' => 'common_homepage', 'value' => 'https://shop.example/']);
        $this->getService(\Illuminate\Cache\ArrayStore::class)->flush();

        try {
            $this->provider->login([], $this->params('/user/login'));
            $this->fail('no redirect');
        } catch (HttpRedirectException $e) {
            $state = $_SESSION['auth_state'];

            $this->assertSame("https://provider.example/auth?redirect=https%3A%2F%2Fshop.example%2Fuser%2Flogin&state={$state}", $e->getUrl());
            $this->assertMatchesRegularExpression('/^[0-9a-f]{16}$/', $state);
            $this->assertSame('FakeOAuthPlugin', $_SESSION['auth_provider']);
        }
    }

    public function testEveryVisitGetsAStateOfItsOwn(): void
    {
        $states = [];
        for ($i = 0; $i < 3; ++$i) {
            try {
                $this->provider->login([], $this->params());
            } catch (HttpRedirectException) {
                $states[] = $_SESSION['auth_state'];
            }
        }

        $this->assertCount(3, array_unique($states));
    }

    public function testComingBackWithTheRightStateMakesAnAccountOfTheProvidersIdentity(): void
    {
        $user = $this->comeBack();

        $this->assertSame('ann', $user->username);
        $this->assertSame('ann@gmail.example', $user->email);
        $this->assertSame('Ann', $user->firstname);
        $this->assertSame(UserStatus::WORK, $user->status);
        $this->assertSame(1, User::count());
        $this->assertSame(1, $user->integrations()->where('provider', 'FakeOAuthPlugin')->where('unique', 'g-100')->count());
    }

    public function testTheStateWorksOnce(): void
    {
        $this->comeBack();
        $this->assertArrayNotHasKey('auth_state', $_SESSION);
        $this->assertArrayNotHasKey('auth_provider', $_SESSION);

        // the same code and state again: a new visit, not a login
        $this->expectException(HttpRedirectException::class);
        $this->provider->login(['state' => 'whatever-it-was', 'code' => 'the-code'], $this->params());
    }

    public function testAWrongStateIsNotALogin(): void
    {
        try {
            $this->provider->login([], $this->params());
        } catch (HttpRedirectException) {
            // to the provider
        }
        $right = $_SESSION['auth_state'];

        foreach (['wrong', strtoupper($right), $right . ' ', '0', ''] as $state) {
            try {
                $this->provider->login(['state' => $state, 'code' => 'the-code'], $this->params());
                $this->fail("'{$state}' was accepted");
            } catch (HttpRedirectException) {
                // sent to the provider again, nobody is signed in
            }
        }

        $this->assertSame(0, User::count());
        $this->assertSame(0, $this->plugin->tokenAsked, 'the code is not even exchanged');
    }

    public function testAVisitorWithoutASessionCannotComeBackWithAState(): void
    {
        $_SESSION = [];

        $this->expectException(HttpRedirectException::class);
        $this->provider->login(['state' => 'forged', 'code' => 'the-code'], $this->params());
    }

    public function testTheSameIdentityIsTheSameAccountEveryTime(): void
    {
        $first = $this->comeBack();
        $second = $this->comeBack(['unique' => 'g-100', 'username' => 'ann-renamed', 'email' => 'other@gmail.example']);

        $this->assertSame($first->uuid, $second->uuid);
        $this->assertSame(1, User::count());
        $this->assertSame('ann', $second->username, 'the account is not rewritten by the provider');
    }

    public function testTwoIdentitiesAreTwoAccounts(): void
    {
        $one = $this->comeBack(['unique' => 'g-1', 'username' => 'one', 'email' => 'one@gmail.example']);
        $two = $this->comeBack(['unique' => 'g-2', 'username' => 'two', 'email' => 'two@gmail.example']);

        $this->assertNotSame($one->uuid, $two->uuid);
    }

    // ------------------------------------------------------------------ refusals

    public function testAnAddressThatBelongsToAnotherAccountDoesNotMeanTheSamePerson(): void
    {
        $this->makeUser(['username' => 'owner', 'email' => 'ann@gmail.example']);

        try {
            $this->comeBack(['unique' => 'g-attacker', 'username' => 'ann2', 'email' => 'ann@gmail.example']);
            $this->fail('the account of the owner was taken over');
        } catch (EmailAlreadyExistsException) {
            $this->assertSame(1, User::count());
            $this->assertSame(0, \App\Domain\Models\UserIntegration::count(), 'nothing is linked to the owner');
        }
    }

    public function testABlockedOrRemovedAccountStaysOutThroughTheProviderToo(): void
    {
        $user = $this->comeBack();

        foreach ([UserStatus::BLOCK, UserStatus::DELETE, UserStatus::CONFIRMATION] as $status) {
            $this->getService(UserService::class)->update($user, ['status' => $status]);

            try {
                $this->comeBack();
                $this->fail("a {$status} account got in");
            } catch (UserNotFoundException) {
                $this->assertSame(1, User::count(), 'and no second account is made for it');
            }
        }
    }

    public function testAProviderThatDoesNotSayWhoItIsAboutLogsNobodyIn(): void
    {
        foreach ([[], ['unique' => ''], ['unique' => null, 'email' => 'x@gmail.example'], ['username' => 'nameless']] as $info) {
            try {
                $this->comeBack($info);
                $this->fail('somebody got in on ' . json_encode($info));
            } catch (\Exception $e) {
                $this->assertNotInstanceOf(HttpRedirectException::class, $e);
            }
        }

        $this->assertSame(0, User::count());
    }

    public function testAProviderThatAnswersNothingAboutTheUserLogsNobodyIn(): void
    {
        $this->plugin->noInfo = true;

        try {
            $this->comeBack();
            $this->fail('somebody got in');
        } catch (\Exception $e) {
            $this->assertNotInstanceOf(HttpRedirectException::class, $e);
        }
        $this->assertSame(0, User::count());
    }

    public function testNoTokenFromTheProviderIsNoLogin(): void
    {
        $this->plugin->token = null;

        try {
            $this->comeBack();
            $this->fail('somebody got in without a token');
        } catch (\Exception $e) {
            $this->assertNotInstanceOf(HttpRedirectException::class, $e);
            $this->assertSame(0, User::count());
        }
    }

    public function testAnAccountMadeThroughTheProviderHasNoPasswordToGuess(): void
    {
        $user = $this->comeBack();

        foreach (['', 'password', '0'] as $password) {
            $response = $this->http('POST', '/auth/login', [
                'headers' => ['Accept' => 'application/json'],
                'form_params' => ['identifier' => $user->username, 'password' => $password],
            ]);

            $this->assertNotSame(200, $response->getStatusCode(), "'{$password}'");
        }
    }

    // ---------------------------------------------------- what it does not do

    public function testThereIsNoRegistrationLogoutRefreshOrRevokeOfItsOwn(): void
    {
        $this->assertNull($this->provider->register(['username' => 'x']));
        $this->assertNull($this->provider->refresh('token', ['agent' => 'test']));
        $this->provider->logout('token');
        $this->provider->revoke('token', null);
        $this->assertSame(0, User::count());
    }

    // ---------------------------------------------------------------- the Auth

    public function testAuthSignsTheAccountInAndIssuesTheSessions(): void
    {
        $auth = $this->getService(Auth::class);
        $auth->addProvider('FakeOAuthPlugin', $this->provider);
        $this->plugin->info = ['unique' => 'g-200', 'username' => 'bob', 'email' => 'bob@gmail.example'];

        try {
            $auth->login('FakeOAuthPlugin', [], $this->params());
        } catch (HttpRedirectException) {
            // to the provider
        }

        $result = $auth->login('FakeOAuthPlugin', ['state' => $_SESSION['auth_state'], 'code' => 'c'], $this->params());

        $this->assertSame('bob', $result['user']->username);
        $this->assertNotEmpty($result['access_token']);
        $this->assertSame(1, UserToken::where('user_uuid', $result['user']->uuid)->count());
        $this->assertSame($result['refresh_token'], UserToken::first()->unique);
    }

    public function testAnUnknownProviderIsRefusedByAuth(): void
    {
        $this->expectException(WrongAuthProviderException::class);

        $this->getService(Auth::class)->login('NoSuchProvider', [], $this->params());
    }

    public function testAuthRefreshThroughTheProviderGivesNothing(): void
    {
        $auth = $this->getService(Auth::class);
        $auth->addProvider('FakeOAuthPlugin', $this->provider);

        $this->assertNull($auth->refresh('FakeOAuthPlugin', 'any-token', ['agent' => 'test']));
    }

    public function testRegisteringThroughTheProviderIsAnAnswerNotACrash(): void
    {
        $response = $this->http('POST', '/auth/register', [
            'headers' => ['Accept' => 'application/json'],
            'form_params' => ['provider' => 'GoogleOAuthPlugin', 'username' => 'x', 'password' => 'y', 'password_again' => 'y'],
        ]);

        $this->assertGreaterThanOrEqual(400, $response->getStatusCode());
        $this->assertLessThan(500, $response->getStatusCode(), $this->why($response));
    }
}

/**
 * A plugin of a service that signs people in, with the answers the test chooses
 */
class FakeOAuthPlugin extends AbstractOAuthPlugin
{
    public const NAME = 'FakeOAuthPlugin';

    public const TITLE = 'Fake sign in';

    public const AUTHOR = 'Tests';

    public mixed $token = 'the-token';

    public ?array $info = null;

    public bool $noInfo = false;

    public int $tokenAsked = 0;

    public function getAuthUrl(string $redirect, ?string $state = null): ?string
    {
        return 'https://provider.example/auth?' . http_build_query(['redirect' => $redirect, 'state' => $state]);
    }

    public function getToken(array $data, string $redirect): mixed
    {
        ++$this->tokenAsked;

        return $this->token;
    }

    public function getInfo(array $data): ?array
    {
        return $this->noInfo ? null : $this->info;
    }

    public function getButton(): ?string
    {
        return '<a>Sign in</a>';
    }
}
