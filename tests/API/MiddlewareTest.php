<?php declare(strict_types=1);

namespace tests\API;

use App\Domain\Service\Parameter\ParameterService;
use App\Domain\Service\User\GroupService;
use tests\Concerns\MakesAuthRequests;
use tests\TestCase;

/**
 * What the middleware stack lets through and what it turns away:
 * access rights, switched off sites and routes, API authorization, CORS
 *
 * @internal
 *
 * #[CoversNothing]
 */
class MiddlewareTest extends TestCase
{
    use MakesAuthRequests;

    private function setParameter(string $name, string $value): void
    {
        $this->getService(ParameterService::class)->create(['name' => $name, 'value' => $value]);
    }

    private function userWithAccess(array $access): array
    {
        $group = $this->getService(GroupService::class)->create(['title' => 'staff ' . uniqid(), 'access' => $access]);

        return $this->loginAs($this->makeUser(['group_uuid' => $group->uuid]));
    }

    // ------------------------------------------------------ AccessChecker (cup)

    public function testGuestIsSentToTheCupLoginWithTheWayBack(): void
    {
        $response = $this->http('GET', '/cup/page');

        $this->assertEquals(307, $response->getStatusCode());
        $this->assertEquals('/cup/login?redirect=/cup/page', $response->getHeaderLine('Location'));
    }

    public function testUserWithoutAGroupIsSentToTheCupForbiddenPage(): void
    {
        $login = $this->loginAs($this->makeUser());

        $response = $this->http('GET', '/cup', ['headers' => $this->cookie($login['access_token'])]);

        $this->assertEquals(307, $response->getStatusCode());
        $this->assertEquals('/cup/forbidden', $response->getHeaderLine('Location'));
    }

    public function testGroupAccessIsPerRoute(): void
    {
        $login = $this->userWithAccess(['cup:main']);

        $allowed = $this->http('GET', '/cup', ['headers' => $this->cookie($login['access_token'])]);
        $this->assertEquals(200, $allowed->getStatusCode());

        foreach (['/cup/page', '/cup/user', '/cup/parameters', '/cup/api-key'] as $path) {
            $denied = $this->http('GET', $path, ['headers' => $this->cookie($login['access_token'])]);

            $this->assertEquals(307, $denied->getStatusCode(), $path);
            $this->assertEquals('/cup/forbidden', $denied->getHeaderLine('Location'), $path);
        }
    }

    public function testAccessIsNotGrantedByAPartialRouteName(): void
    {
        // 'cup:page' is a prefix of every cup:page:* route but not a route itself
        $login = $this->userWithAccess(['cup:page']);

        $response = $this->http('GET', '/cup/page', ['headers' => $this->cookie($login['access_token'])]);

        $this->assertEquals(307, $response->getStatusCode());
        $this->assertEquals('/cup/forbidden', $response->getHeaderLine('Location'));
    }

    public function testAccessGrantedByTheSiteWideParameterAppliesToEveryone(): void
    {
        $this->setParameter('user_access', 'cup:main');
        $login = $this->loginAs($this->makeUser());

        $response = $this->http('GET', '/cup', ['headers' => $this->cookie($login['access_token'])]);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testTheCupLoginPageIsOpenToGuests(): void
    {
        $response = $this->http('GET', '/cup/login');

        $this->assertNotEquals(307, $response->getStatusCode());
        $this->assertLessThan(500, $response->getStatusCode());
    }

    // ---------------------------------------------------- AccessChecker (common)

    public function testCommonRoutesAreOpenWhileNoAccessListIsConfigured(): void
    {
        $this->assertEquals(200, $this->http('GET', '/guestbook?format=json')->getStatusCode());
    }

    public function testCommonRoutesCanBeLimitedToAListedSet(): void
    {
        $this->setParameter('user_access', 'common:main');

        $response = $this->http('GET', '/guestbook?format=json');

        $this->assertEquals(307, $response->getStatusCode());
        $this->assertEquals('/forbidden', $response->getHeaderLine('Location'));
    }

    public function testApiRoutesAreNotSubjectToTheRouteAccessList(): void
    {
        $this->setParameter('user_access', 'common:main');
        $this->setParameter('entity_access', 'all');

        $this->assertEquals(200, $this->http('GET', '/api/v1/page')->getStatusCode());
    }

    // ----------------------------------------------------------- site / routes

    public function testSwitchedOffSiteAnswersWith503ForPublicPages(): void
    {
        $this->setParameter('common_site_enabled', 'no');

        $this->assertEquals(503, $this->http('GET', '/guestbook')->getStatusCode());
        $this->assertEquals(503, $this->http('GET', '/some/page')->getStatusCode());
    }

    public function testSwitchedOffSiteStillServesTheCupAndTheApi(): void
    {
        $this->setParameter('common_site_enabled', 'no');
        $this->setParameter('entity_access', 'all');

        $this->assertEquals(200, $this->http('GET', '/api/v1/page')->getStatusCode());
        $this->assertEquals(307, $this->http('GET', '/cup')->getStatusCode(), 'the cup answers (here: asks to log in), not 503');
    }

    public function testSwitchedOffPublicRouteRedirectsToForbidden(): void
    {
        $this->setParameter('user_is_enabled', 'no');

        $response = $this->http('GET', '/user/login');

        $this->assertEquals(307, $response->getStatusCode());
        $this->assertEquals('/forbidden', $response->getHeaderLine('Location'));

        // an unrelated route is unaffected
        $this->assertEquals(200, $this->http('GET', '/guestbook?format=json')->getStatusCode());
    }

    public function testRouteSwitchIsOnlyTheLiteralNo(): void
    {
        $this->setParameter('user_is_enabled', 'off');

        $this->assertNotEquals(307, $this->http('GET', '/user/login?format=json')->getStatusCode());
    }

    public function testSwitchedOffApiEntityAnswersWith423(): void
    {
        $this->setParameter('entity_access', 'all');
        $this->setParameter('entity_is_enabled', 'no');

        $response = $this->http('GET', '/api/v1/page');

        $this->assertEquals(423, $response->getStatusCode());
        $this->assertStringContainsString('Access denied', (string) $response->getBody());
    }

    public function testSwitchedOffMcpAnswersWith423(): void
    {
        $this->setParameter('entity_access', 'all');
        $this->setParameter('mcp_is_enabled', 'no');

        $this->assertEquals(423, $this->http('GET', '/api/v1/mcp')->getStatusCode());
    }

    // ------------------------------------------------- AuthorizationAPIMiddleware

    public function testApiRejectsAnAnonymousCallerUnlessOpenToAll(): void
    {
        foreach (['user', 'key'] as $mode) {
            $this->getService(ParameterService::class)->create(['name' => 'entity_access', 'value' => $mode]);

            $response = $this->http('GET', '/api/v1/page');
            $this->assertEquals(401, $response->getStatusCode(), $mode);
            $this->assertStringContainsString('must authenticate', (string) $response->getBody());

            \App\Domain\Models\Parameter::where('name', 'entity_access')->delete();
        }
    }

    public function testApiKeyCanTravelInEveryDocumentedPlace(): void
    {
        $this->setParameter('entity_access', 'key');
        $token = $this->createApiKeyToken();

        $this->assertEquals(200, $this->http('GET', '/api/v1/page?apikey=' . $token)->getStatusCode(), 'query');
        $this->assertEquals(200, $this->http('GET', '/api/v1/page', ['headers' => ['key' => $token]])->getStatusCode(), 'key header');
        $this->assertEquals(200, $this->http('GET', '/api/v1/page', ['headers' => ['apikey' => $token]])->getStatusCode(), 'apikey header');
        $this->assertEquals(200, $this->http('GET', '/api/v1/page', ['headers' => ['Authorization' => 'Bearer ' . $token]])->getStatusCode(), 'bearer');
    }

    public function testApiRejectsTokensThatAreNotApiKeys(): void
    {
        $this->setParameter('entity_access', 'key');
        $user = $this->makeUser();
        $login = $this->loginAs($user);

        $notKeys = [
            'garbage' => 'definitely-not-a-jwt',
            'a user access token' => $login['access_token'],
            'forged' => $this->forgeToken('api-key', $user->uuid),
            'api-key subject but no such row' => $this->signToken('api-key', \Ramsey\Uuid\Uuid::uuid4()->toString()),
            'api-key subject without a uuid' => $this->signToken('api-key', null),
        ];

        foreach ($notKeys as $case => $token) {
            $response = $this->http('GET', '/api/v1/page', ['headers' => ['Authorization' => 'Bearer ' . $token]]);

            $this->assertEquals(401, $response->getStatusCode(), $case);
        }
    }

    public function testApiRejectsAnExpiredApiToken(): void
    {
        $this->setParameter('entity_access', 'key');
        $key = $this->getService(\App\Domain\Service\ApiKey\ApiKeyService::class)->create(['title' => 'old', 'is_full_access' => true]);

        $expired = $this->signToken('api-key', $key->uuid, [], time() - 60);

        $this->assertEquals(401, $this->http('GET', '/api/v1/page?apikey=' . $expired)->getStatusCode());
    }

    public function testApiAcceptsALoggedInUserInUserMode(): void
    {
        $this->setParameter('entity_access', 'user');
        $login = $this->loginAs($this->makeUser());

        $this->assertEquals(200, $this->http('GET', '/api/v1/page?access_token=' . $login['access_token'])->getStatusCode(), 'query');
        $this->assertEquals(200, $this->http('GET', '/api/v1/page', ['headers' => $this->cookie($login['access_token'])])->getStatusCode(), 'cookie');
    }

    public function testApiRejectsAnExpiredUserToken(): void
    {
        $this->setParameter('entity_access', 'user');
        $user = $this->makeUser();
        $expired = $this->signToken('user', $user->uuid, ['uuid' => $user->uuid], time() - 60);

        $this->assertEquals(401, $this->http('GET', '/api/v1/page?access_token=' . $expired)->getStatusCode());
    }

    public function testApiKeyModeAlsoAcceptsAUser(): void
    {
        // the modes are cumulative: key => key or user, all => anyone
        $this->setParameter('entity_access', 'key');
        $login = $this->loginAs($this->makeUser());

        $this->assertEquals(200, $this->http('GET', '/api/v1/page?access_token=' . $login['access_token'])->getStatusCode());
    }

    public function testApiPreflightNeedsNoCredentials(): void
    {
        $this->setParameter('entity_access', 'user');

        $this->assertEquals(200, $this->http('OPTIONS', '/api/v1/page')->getStatusCode());
        $this->assertEquals(200, $this->http('OPTIONS', '/api/v1/mcp')->getStatusCode());
    }

    // --------------------------------------------------------------------- CORS

    public function testCorsHeadersAreAbsentUntilConfigured(): void
    {
        $this->setParameter('entity_access', 'all');

        $response = $this->http('GET', '/api/v1/page', ['headers' => ['Origin' => 'https://app.example']]);

        $this->assertFalse($response->hasHeader('Access-Control-Allow-Origin'));
        $this->assertFalse($response->hasHeader('Access-Control-Allow-Methods'));
    }

    public function testCorsWildcardEchoesTheOrigin(): void
    {
        $this->setParameter('entity_access', 'all');
        $this->setParameter('entity_cors_origin', '*');

        $withOrigin = $this->http('GET', '/api/v1/page', ['headers' => ['Origin' => 'https://app.example']]);
        $this->assertEquals('https://app.example', $withOrigin->getHeaderLine('Access-Control-Allow-Origin'));

        $without = $this->http('GET', '/api/v1/page');
        $this->assertEquals('*', $without->getHeaderLine('Access-Control-Allow-Origin'));
    }

    public function testCorsAllowListOnlyAdmitsListedOrigins(): void
    {
        $this->setParameter('entity_access', 'all');
        $this->setParameter('entity_cors_origin', "https://a.example\nhttps://b.example");

        $listed = $this->http('GET', '/api/v1/page', ['headers' => ['Origin' => 'https://b.example']]);
        $this->assertEquals('https://b.example', $listed->getHeaderLine('Access-Control-Allow-Origin'));

        foreach (['https://evil.example', 'https://b.example.evil.example', 'https://a.example/'] as $origin) {
            $other = $this->http('GET', '/api/v1/page', ['headers' => ['Origin' => $origin]]);

            $this->assertFalse($other->hasHeader('Access-Control-Allow-Origin'), $origin);
        }
    }

    public function testCorsMethodsAndHeadersAreSentAsConfigured(): void
    {
        $this->setParameter('entity_access', 'all');
        $this->setParameter('entity_cors_methods', 'get, post');
        $this->setParameter('entity_cors_headers', 'Content-Type, Key');

        $response = $this->http('OPTIONS', '/api/v1/page');

        $this->assertEquals('GET, POST', $response->getHeaderLine('Access-Control-Allow-Methods'));
        $this->assertEquals('Content-Type, Key', $response->getHeaderLine('Access-Control-Allow-Headers'));
    }

    // ---------------------------------------------------- redirects, www, locale

    public function testTrailingSlashIsRemovedByARedirect(): void
    {
        $response = $this->http('GET', '/guestbook/?page=2');

        $this->assertEquals(301, $response->getStatusCode());
        $this->assertEquals('/guestbook?page=2', $response->getHeaderLine('Location'));
    }

    public function testNonWwwRedirectOnlyWhenSwitchedOn(): void
    {
        $plain = $this->http('GET', '/guestbook?format=json', ['headers' => ['Host' => 'www.example.com']]);
        $this->assertEquals(200, $plain->getStatusCode());

        $this->setParameter('common_non_www', 'yes');

        $www = $this->http('GET', '/guestbook?format=json&page=2', ['headers' => ['Host' => 'www.example.com']]);
        $this->assertEquals(308, $www->getStatusCode());
        $this->assertEquals('http://example.com/guestbook?format=json&page=2', $www->getHeaderLine('Location'), 'path and query survive');

        // only the "www." label is dropped, a host that merely starts with those letters is left alone
        $lookalike = $this->http('GET', '/guestbook?format=json', ['headers' => ['Host' => 'wwwshop.example']]);
        $this->assertEquals(200, $lookalike->getStatusCode());

        $bare = $this->http('GET', '/guestbook?format=json', ['headers' => ['Host' => 'example.com']]);
        $this->assertEquals(200, $bare->getStatusCode());
    }

    public function testLanguageQuerySetsTheLanguageCookie(): void
    {
        $response = $this->http('GET', '/guestbook?format=json&lang=ru-RU');

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('language=ru-RU', implode(';', $response->getHeader('Set-Cookie')));
    }

    // ------------------------------------------------------------- multipart body

    public function testMultipartBodyIsReadOnPutAndPatch(): void
    {
        $this->setParameter('entity_access', 'key');
        $token = $this->createApiKeyToken();

        $created = $this->http('POST', '/api/v1/page', [
            'headers' => ['key' => $token],
            'json' => ['title' => 'before', 'address' => 'before', 'content' => 'x'],
        ]);
        $this->assertContains($created->getStatusCode(), [200, 201], (string) $created->getBody());
        $uuid = $this->json($created)['data']['uuid'] ?? $this->json($created)['uuid'] ?? null;
        $this->assertNotNull($uuid, (string) $created->getBody());

        $response = $this->http('PATCH', '/api/v1/page?uuid=' . $uuid, [
            'headers' => ['key' => $token],
            'multipart' => [['name' => 'title', 'contents' => 'after']],
        ]);
        $this->assertEquals(202, $response->getStatusCode(), (string) $response->getBody());

        $page = $this->getService(\App\Domain\Service\Page\PageService::class)->read(['uuid' => $uuid]);
        $this->assertEquals('after', $page->title);
    }
}
