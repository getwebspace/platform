<?php declare(strict_types=1);

namespace tests\API;

use App\Domain\Service\Parameter\ParameterService;
use tests\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
class CommonAPITest extends TestCase
{
    public function testAPIModeAll(): void
    {
        $parameters = $this->getService(ParameterService::class);
        $parameters->create(['key' => 'entity_access', 'value' => 'all']);

        $response = $this->createRequest()->get('/api/v1/user');
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testAPIModeUser(): void
    {
        $parameters = $this->getService(ParameterService::class);
        $parameters->create(['key' => 'entity_access', 'value' => 'user']);

        $response = $this->createRequest()->get('/api/v1/user');
        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testAPIModeKeyFailed(): void
    {
        $parameters = $this->getService(ParameterService::class);
        $parameters->create(['key' => 'entity_access', 'value' => 'key']);

        $response = $this->createRequest()->get('/api/v1/user');
        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testAPIModeKeySuccess(): void
    {
        $apikey = $this->getFaker()->word;

        $parameters = $this->getService(ParameterService::class);
        $parameters->create(['key' => 'entity_access', 'value' => 'key']);
        $parameters->create(['key' => 'entity_keys', 'value' => $apikey]);

        $response = $this->createRequest()->get('/api/v1/user', ['headers' => ['key' => $apikey]]);
        $this->assertEquals(200, $response->getStatusCode());
    }
}
