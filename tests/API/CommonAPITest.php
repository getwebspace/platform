<?php declare(strict_types=1);

namespace tests\API;

use App\Domain\Service\Parameter\ParameterService;
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
}
