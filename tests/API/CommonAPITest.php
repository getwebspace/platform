<?php declare(strict_types=1);

namespace tests\API;

use App\Domain\Service\Parameter\ParameterService;
use PHPUnit\Framework\Attributes\DataProvider;
use tests\TestCase;

/**
 * @internal
 *
 * #[CoversNothing]
 */
class CommonAPITest extends TestCase
{
    public function testAPIModeAll(): void
    {
        $parameters = $this->getService(ParameterService::class);
        $parameters->create(['name' => 'entity_access', 'value' => 'all']);

        $response = $this->createRequest()->get('/api/v1/user');
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testAPIModeUser(): void
    {
        $parameters = $this->getService(ParameterService::class);
        $parameters->create(['name' => 'entity_access', 'value' => 'user']);

        $response = $this->createRequest()->get('/api/v1/user');
        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testAPIModeKeyFailed(): void
    {
        $parameters = $this->getService(ParameterService::class);
        $parameters->create(['name' => 'entity_access', 'value' => 'key']);

        $response = $this->createRequest()->get('/api/v1/user');
        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testAPIModeKeySuccess(): void
    {
        $parameters = $this->getService(ParameterService::class);
        $parameters->create(['name' => 'entity_access', 'value' => 'key']);

        $apikey = $this->createApiKeyToken();

        $response = $this->createRequest()->get('/api/v1/user', ['headers' => ['key' => $apikey]]);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testAPIKeyScopeIsEnforced(): void
    {
        $parameters = $this->getService(ParameterService::class);
        $parameters->create(['name' => 'entity_access', 'value' => 'key']);

        $apikey = $this->createApiKeyToken(
            ['read' => ['catalog/product'], 'write' => []],
            false
        );

        // in scope
        $response = $this->createRequest()->get('/api/v1/catalog/product', ['headers' => ['key' => $apikey]]);
        $this->assertEquals(200, $response->getStatusCode());

        // entity not covered by the key's scopes
        $response = $this->createRequest()->get('/api/v1/user', ['headers' => ['key' => $apikey]]);
        $this->assertEquals(403, $response->getStatusCode());

        // read is scoped, write is not
        $response = $this->createRequest()->put('/api/v1/catalog/product', [
            'headers' => ['key' => $apikey],
            'form_params' => ['title' => 'x'],
        ]);
        $this->assertEquals(405, $response->getStatusCode());

        // never reachable from the public API regardless of scope
        $response = $this->createRequest()->get('/api/v1/parameter', ['headers' => ['key' => $apikey]]);
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testPatchTellsWhyValueIsRejected(): void
    {
        $this->getService(ParameterService::class)->create(['name' => 'entity_access', 'value' => 'key']);
        $token = $this->createApiKeyToken();

        $users = $this->getService(\App\Domain\Service\User\UserService::class);
        $users->create(['username' => 'first', 'email' => 'taken@example.com']);
        $second = $users->create(['username' => 'second', 'email' => 'free@example.com']);

        $response = $this->createRequest()->patch('/api/v1/user?uuid=' . $second->uuid, [
            'headers' => ['key' => $token],
            'json' => ['email' => 'taken@example.com'],
        ]);
        $this->assertEquals(422, $response->getStatusCode());
        $this->assertEquals('EXCEPTION_EMAIL_ALREADY_EXISTS', json_decode((string) $response->getBody(), true)['data']);

        // nothing found is still 404
        $response = $this->createRequest()->patch('/api/v1/user?uuid=' . \Ramsey\Uuid\Uuid::uuid4(), [
            'headers' => ['key' => $token],
            'json' => ['email' => 'other@example.com'],
        ]);
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testAPIKeyRevocationTakesEffectImmediately(): void
    {
        $parameters = $this->getService(ParameterService::class);
        $parameters->create(['name' => 'entity_access', 'value' => 'key']);

        /** @var \App\Domain\Service\ApiKey\ApiKeyService $service */
        $service = $this->getService(\App\Domain\Service\ApiKey\ApiKeyService::class);
        $apiKey = $service->create(['title' => 'test key', 'is_full_access' => true]);
        $token = $service->issueToken($apiKey);

        $response = $this->createRequest()->get('/api/v1/user', ['headers' => ['key' => $token]]);
        $this->assertEquals(200, $response->getStatusCode());

        $service->update($apiKey, ['status' => \App\Domain\Casts\ApiKey\Status::REVOKE]);

        // same token, no re-issue - the row's status is checked on every request
        $response = $this->createRequest()->get('/api/v1/user', ['headers' => ['key' => $token]]);
        $this->assertEquals(401, $response->getStatusCode());
    }

    #[DataProvider('pageFormatProvider')]
    public function testPageResponseFormat(array $headers, string $query, string $contentType): void
    {
        $response = $this->createRequest()->get('/' . $query, ['headers' => $headers]);

        $this->assertStringStartsWith($contentType, $response->getHeaderLine('Content-Type'));
        $this->assertStringContainsString('Accept', $response->getHeaderLine('Vary'));
    }

    public static function pageFormatProvider(): array
    {
        return [
            'any type is html' => [['Accept' => '*/*'], '', 'text/html'],
            'no accept is html' => [['Accept' => ''], '', 'text/html'],
            'browser is html' => [['Accept' => 'text/html,application/xhtml+xml,application/json;q=0.9,*/*;q=0.8'], '', 'text/html'],
            'explicit json' => [['Accept' => 'application/json'], '', 'application/json'],
            'xhr with any type is json' => [['Accept' => '*/*', 'X-Requested-With' => 'XMLHttpRequest'], '', 'application/json'],
        ];
    }

    public function testPageFormatQueryIsDebugOnly(): void
    {
        $debug = (bool) ($_ENV['DEBUG'] ?? getenv('DEBUG'));

        $response = $this->createRequest()->get('/?format=json', ['headers' => ['Accept' => '*/*']]);

        $this->assertStringStartsWith($debug ? 'application/json' : 'text/html', $response->getHeaderLine('Content-Type'));
    }

    public function testPublicPageIsCacheableAndVaries(): void
    {
        $response = $this->createRequest()->get('/', ['headers' => ['Accept' => 'text/html']]);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('public, max-age=3600', $response->getHeaderLine('Cache-Control'));
        $this->assertStringContainsString('Accept-Language', $response->getHeaderLine('Vary'));
        $this->assertStringContainsString('Cookie', $response->getHeaderLine('Vary'));
    }

    public function testPlainPageViewSetsNoCookie(): void
    {
        $response = $this->createRequest()->get('/', ['headers' => ['Accept' => 'text/html']]);

        $this->assertFalse($response->hasHeader('Set-Cookie'));
        $this->assertFalse($response->hasHeader('Pragma'));
    }

    public function testNotFoundPageIsNotCached(): void
    {
        $response = $this->createRequest()->get('/no-such-page-' . uniqid(), ['headers' => ['Accept' => 'text/html']]);

        $this->assertEquals(404, $response->getStatusCode());
        $this->assertStringContainsString('no-store', $response->getHeaderLine('Cache-Control'));
        $this->assertStringNotContainsString('public', $response->getHeaderLine('Cache-Control'));
    }

    public function testLanguageSwitchIsNotCached(): void
    {
        $response = $this->createRequest()->get('/?lang=ru', ['headers' => ['Accept' => 'text/html']]);

        $this->assertStringContainsString('no-store', $response->getHeaderLine('Cache-Control'));
        $this->assertStringNotContainsString('public', $response->getHeaderLine('Cache-Control'));
    }

    public function testGuestBookPageNumberIsRouted(): void
    {
        $first = $this->createRequest()->get('/guestbook', ['headers' => ['Accept' => 'text/html']]);
        $second = $this->createRequest()->get('/guestbook/2', ['headers' => ['Accept' => 'text/html']]);

        // the pager used to fall through to the catch-all page route and answer 404
        $this->assertEquals($first->getStatusCode(), $second->getStatusCode());
    }

    public function testXmlNameCannotLeaveTheDirectory(): void
    {
        $response = $this->createRequest()->get('/xml/..%2fsecret', ['headers' => ['Accept' => 'text/html']]);

        $this->assertNotEquals(200, $response->getStatusCode());
        $this->assertStringNotContainsString('xml', $response->getHeaderLine('Content-Type'));
    }
}
