<?php declare(strict_types=1);

namespace tests\Application;

use App\Application\Auth;
use App\Application\Auth\AbstractAuthProvider;
use App\Application\Csrf;
use App\Application\Dashboard;
use App\Application\FormGuard;
use App\Domain\Exceptions\WrongAuthProviderException;
use App\Domain\Models\User;
use App\Domain\Models\UserToken;
use App\Domain\Service\Parameter\ParameterService;
use App\Domain\Service\Review\ReviewService;
use Psr\Container\ContainerInterface;
use tests\TestCase;

/**
 * The small services the rest is built on, in the corners the main scenarios do not reach
 *
 * @internal
 *
 * #[CoversNothing]
 */
class SmallPiecesTest extends TestCase
{
    private string $secret = '';

    public function tearDown(): void
    {
        @unlink($this->secret);

        parent::tearDown();
    }

    // --------------------------------------------------------------------- csrf

    public function testNobodyGetsATokenWithoutBeingSignedIn(): void
    {
        $this->assertSame('', Csrf::token(false));
        $this->assertSame('', Csrf::token(null));
    }

    // --------------------------------------------------------------- form guard

    private function guard(): FormGuard
    {
        $this->secret = sys_get_temp_dir() . '/form-guard-' . bin2hex(random_bytes(4)) . '.secret';

        return new FormGuard($this->secret);
    }

    public function testATokenThatWasNotMadeHereOrIsTooOldIsToldApart(): void
    {
        $guard = $this->guard();

        $this->assertContains('bad token', $guard->inspect([FormGuard::FIELD_TOKEN => 'not-a-token'])['reasons'] ?? [], 'a made-up token');
        $this->assertContains('expired token', $guard->inspect([FormGuard::FIELD_TOKEN => $guard->token(time() - FormGuard::MAX_AGE - 60)])['reasons']);
        $this->assertNotContains('expired token', $guard->inspect([FormGuard::FIELD_TOKEN => $guard->token(time() - 60)])['reasons']);
    }

    public function testTheValuesInsideOtherValuesAreLookedAtToo(): void
    {
        $guard = $this->guard();
        $token = $guard->token(time() - 30);

        $verdict = $guard->inspect([FormGuard::FIELD_TOKEN => $token, 'items' => ['first' => ['name' => 'look: http://spam.example/a']]]);

        $this->assertContains('link in field items[first][name]', $verdict['reasons']);
    }

    public function testAnAddressThatIsNotOneHasNoDomainToCheck(): void
    {
        $guard = $this->guard();

        $verdict = $guard->inspect([FormGuard::FIELD_TOKEN => $guard->token(time() - 30), 'email' => 'not an address', 'mail' => 'user@mailinator.com']);

        $this->assertContains('disposable e-mail', $verdict['reasons'], 'the address that is one is still checked');

        $junk = $guard->inspect([FormGuard::FIELD_TOKEN => $guard->token(time() - 30), 'email' => 'not an address']);
        $this->assertNotContains('disposable e-mail', $junk['reasons']);
    }

    // --------------------------------------------------------------------- auth

    private function provider(?User $user = null): AbstractAuthProvider
    {
        return new class($this->getService(ContainerInterface::class), $user) extends AbstractAuthProvider {
            public function __construct(ContainerInterface $container, private ?User $user)
            {
                parent::__construct($container);
            }

            public function login(array $credentials, array $params): ?User
            {
                return $this->user;
            }

            public function register(array $data): ?User
            {
                return $this->user;
            }

            public function logout(string $token): void {}

            public function refresh(string $token, array $params): ?UserToken
            {
                return null;
            }

            public function revoke(string $token, ?string $uuid): void {}
        };
    }

    public function testAnUnknownWayToSignInIsRefusedEverywhere(): void
    {
        $auth = new Auth($this->getService(ContainerInterface::class));
        $calls = [
            fn () => $auth->login('Nobody', []),
            fn () => $auth->register('Nobody', []),
            fn () => $auth->logout('Nobody', 'token'),
            fn () => $auth->refresh('Nobody', 'token'),
            fn () => $auth->revoke('Nobody', 'token', null),
        ];

        foreach ($calls as $i => $call) {
            try {
                $call();
                $this->fail("call {$i} must refuse an unknown provider");
            } catch (WrongAuthProviderException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testAWayToSignInThatFindsNobodyGivesNothing(): void
    {
        $auth = new Auth($this->getService(ContainerInterface::class));
        $auth->addProvider('Empty', $this->provider(null));

        $this->assertNull($auth->login('Empty', ['identifier' => 'x', 'password' => 'y']));
        $this->assertNull($auth->register('Empty', []));
        $this->assertNull($auth->refresh('Empty', 'token'));
        $auth->logout('Empty', 'token');
        $auth->revoke('Empty', 'token', null);
        $this->assertTrue(true);
    }

    // ---------------------------------------------------------------- dashboard

    public function testTheReviewsWaitingForTheModeratorAreCountedWhenTheyAreSwitchedOn(): void
    {
        $dashboard = new Dashboard($this->getService(ContainerInterface::class));
        $this->assertArrayNotHasKey('review', $dashboard->attention($dashboard->enabled()));

        $this->getService(ParameterService::class)->create(['name' => 'review_publication_is_enabled', 'value' => 'yes']);
        $this->getService(\Illuminate\Cache\ArrayStore::class)->flush();
        $dashboard = new Dashboard($this->getService(ContainerInterface::class));
        $this->getService(ReviewService::class)->create(['type' => 'review', 'entity_type' => 'publication', 'entity_uuid' => \Ramsey\Uuid\Uuid::uuid4()->toString(), 'message' => 'Hi']);

        $this->assertTrue($dashboard->enabled()['review']);
        $this->assertSame(1, $dashboard->attention($dashboard->enabled())['review']);
    }
}
