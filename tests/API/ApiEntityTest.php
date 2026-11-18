<?php declare(strict_types=1);

namespace tests\API;

use App\Domain\Models\UserToken;
use App\Domain\References\ApiEntity;
use App\Domain\Service\Page\PageService;
use App\Domain\Service\Parameter\ParameterService;
use App\Domain\Service\User\GroupService;
use PHPUnit\Framework\Attributes\DataProvider;
use tests\Concerns\MakesAuthRequests;
use tests\TestCase;

/**
 * /api/v1/{entity} and /cup/api/v1/{entity}: reading, the write rules,
 * the lifecycle of a record, and what must never be serialized
 *
 * @internal
 *
 * #[CoversNothing]
 */
class ApiEntityTest extends TestCase
{
    use MakesAuthRequests;

    private string $key;

    public function setUp(): void
    {
        parent::setUp();

        $this->getService(ParameterService::class)->create(['name' => 'entity_access', 'value' => 'key']);
        $this->key = $this->createApiKeyToken();
    }

    private function api(string $method, string $path, array $options = []): \Psr\Http\Message\ResponseInterface
    {
        $options['headers'] = ($options['headers'] ?? []) + ['key' => $this->key];

        return $this->http($method, '/api/v1/' . $path, $options);
    }

    // -------------------------------------------------------------------- reading

    public static function entityProvider(): array
    {
        return array_map(static fn(string $entity): array => [$entity], array_keys(ApiEntity::MAP));
    }

    #[DataProvider('entityProvider')]
    public function testEveryEntityCanBeListed(string $entity): void
    {
        $response = $this->api('GET', $entity);

        $this->assertContains($response->getStatusCode(), [200, 404], $entity . ': ' . $response->getBody());
        $this->assertSame($response->getStatusCode(), $this->json($response)['status']);
    }

    public function testUnknownEntityIsNotFound(): void
    {
        $this->assertEquals(404, $this->api('GET', 'no-such-entity')->getStatusCode());
        $this->assertEquals(404, $this->api('GET', 'page/extra')->getStatusCode());
        $this->assertEquals(404, $this->api('GET', '../parameter')->getStatusCode());
    }

    public function testListSupportsLimitOffsetAndSearch(): void
    {
        $pages = $this->getService(PageService::class);
        foreach (['Alpha', 'Beta', 'Gamma'] as $i => $title) {
            $pages->create(['title' => $title, 'address' => 'p' . $i, 'content' => '']);
        }

        $this->assertCount(3, $this->json($this->api('GET', 'page'))['data']);
        $this->assertCount(1, $this->json($this->api('GET', 'page?limit=1'))['data']);
        $this->assertCount(2, $this->json($this->api('GET', 'page?limit=5&offset=1'))['data']);

        $found = $this->json($this->api('GET', 'page?search=amm'))['data'];
        $this->assertCount(1, $found);
        $this->assertEquals('Gamma', $found[0]['title']);
    }

    public function testSearchIsCaseInsensitiveOnNonLatinText(): void
    {
        $this->getService(PageService::class)->create(['title' => 'Привет Мир', 'address' => 'privet', 'content' => '']);
        $this->getService(PageService::class)->create(['title' => 'Other', 'address' => 'other', 'content' => '']);

        foreach (['мир', 'МИР', 'Привет'] as $needle) {
            $found = $this->json($this->api('GET', 'page?search=' . urlencode($needle)))['data'];

            $this->assertCount(1, $found, $needle);
            $this->assertEquals('Привет Мир', $found[0]['title']);
        }
    }

    public function testReadByUuidReturnsThatRecord(): void
    {
        $page = $this->getService(PageService::class)->create(['title' => 'One', 'address' => 'one', 'content' => '']);
        $this->getService(PageService::class)->create(['title' => 'Two', 'address' => 'two', 'content' => '']);

        $response = $this->api('GET', 'page?uuid=' . $page->uuid);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('One', $this->json($response)['data']['title']);
        $this->assertEquals(404, $this->api('GET', 'page?uuid=' . \Ramsey\Uuid\Uuid::uuid4())->getStatusCode());
    }

    // ------------------------------------------------------------------ never leaks

    public function testUserRecordsNeverCarryPasswords(): void
    {
        $this->makeUser(['username' => 'visible']);

        $list = (string) $this->api('GET', 'user')->getBody();
        $this->assertStringContainsString('visible', $list);
        $this->assertStringNotContainsString('"password"', $list);
        $this->assertStringNotContainsString('$2y$', $list, 'no bcrypt hash anywhere in the response');
        $this->assertStringNotContainsString('$argon', $list);
    }

    public function testTheWithParameterCannotAttachAnotherRelation(): void
    {
        $user = $this->makeUser();
        $this->loginAs($user);
        $this->assertSame(1, UserToken::where('user_uuid', $user->uuid)->count());

        $body = (string) $this->api('GET', 'user?with[]=tokens&with[]=integrations')->getBody();

        $this->assertStringNotContainsString('"tokens"', $body);
        $this->assertStringNotContainsString(UserToken::first()->unique, $body, 'refresh tokens must never be serialized');
    }

    // ---------------------------------------------------------- write permissions

    public function testWritingNeedsAKeyEvenWhenReadingIsOpen(): void
    {
        $this->getService(ParameterService::class)->update(
            $this->getService(ParameterService::class)->read(['name' => 'entity_access']),
            ['value' => 'all']
        );

        $read = $this->http('GET', '/api/v1/page');
        $this->assertEquals(200, $read->getStatusCode());

        foreach (['POST', 'PUT'] as $method) {
            $write = $this->http($method, '/api/v1/page', ['json' => ['title' => 'sneaky', 'address' => 'sneaky']]);
            $this->assertEquals(405, $write->getStatusCode(), $method);
        }
        $this->assertEquals(405, $this->http('PATCH', '/api/v1/page', ['json' => ['content' => 'x']])->getStatusCode());
        $this->assertEquals(405, $this->http('DELETE', '/api/v1/page')->getStatusCode());
        $this->assertSame(0, \App\Domain\Models\Page::count());
    }

    public function testALoggedInCustomerCannotWrite(): void
    {
        $login = $this->loginAs($this->makeUser());

        $response = $this->http('POST', '/api/v1/page', [
            'headers' => $this->cookie($login['access_token']),
            'json' => ['title' => 'customer page', 'address' => 'customer'],
        ]);

        $this->assertEquals(405, $response->getStatusCode());
        $this->assertSame(0, \App\Domain\Models\Page::count());
    }

    public function testAScopedKeyWritesOnlyWhereItWasGivenWriteScope(): void
    {
        $scoped = $this->createApiKeyToken(['read' => ['page', 'reference'], 'write' => ['reference']], false);

        $denied = $this->http('POST', '/api/v1/page', ['headers' => ['key' => $scoped], 'json' => ['title' => 'x', 'address' => 'x']]);
        $this->assertEquals(405, $denied->getStatusCode());

        $allowed = $this->http('POST', '/api/v1/reference', ['headers' => ['key' => $scoped], 'json' => ['title' => 'EUR', 'type' => 'currency']]);
        $this->assertEquals(201, $allowed->getStatusCode(), (string) $allowed->getBody());
    }

    // ------------------------------------------------------------------ lifecycle

    /**
     * entity, create body, patch body, patched field, its new value, extra
     * read query (a record whose status is not "work" is hidden by the default
     * filter), whether the delete is soft
     */
    public static function lifecycleProvider(): array
    {
        return [
            'page' => ['page', ['title' => 'Lifecycle', 'address' => 'lifecycle', 'content' => 'a'], ['content' => 'b'], 'content', 'b', '', false],
            'user group' => ['user/group', ['title' => 'Staff', 'access' => ['cup:main']], ['title' => 'Staff 2'], 'title', 'Staff 2', '', false],
            'task' => ['task', ['title' => 'Job', 'action' => 'App\\Domain\\Tasks\\SearchIndexTask'], ['title' => 'Job 2'], 'title', 'Job 2', '&status=queue', false],
            'reference' => ['reference', ['title' => 'USD', 'type' => 'currency'], ['title' => 'US Dollar'], 'title', 'US Dollar', '', false],
            'guestbook' => ['guestbook', ['name' => 'Ann', 'email' => 'ann@example.com', 'message' => 'Hi'], ['message' => 'Hello'], 'message', 'Hello', '&status=moderate', false],
            'user' => ['user', ['username' => 'lifecycle', 'email' => 'life@example.com', 'password' => 'secret-pass'], ['firstname' => 'Lee'], 'firstname', 'Lee', '', true],
        ];
    }

    #[DataProvider('lifecycleProvider')]
    public function testARecordCanBeCreatedReadChangedAndDeleted(string $entity, array $create, array $patch, string $field, string $expected, string $query, bool $softDelete): void
    {
        $created = $this->api('POST', $entity, ['json' => $create]);
        $this->assertEquals(201, $created->getStatusCode(), (string) $created->getBody());
        $uuid = $this->json($created)['data']['uuid'];
        $this->assertNotEmpty($uuid);

        $read = $this->api('GET', $entity . '?uuid=' . $uuid . $query);
        $this->assertEquals(200, $read->getStatusCode(), (string) $read->getBody());
        $this->assertEquals($uuid, $this->json($read)['data']['uuid']);

        $patched = $this->api('PATCH', $entity . '?uuid=' . $uuid . $query, ['json' => $patch]);
        $this->assertEquals(202, $patched->getStatusCode(), (string) $patched->getBody());
        $this->assertEquals($expected, $this->json($this->api('GET', $entity . '?uuid=' . $uuid . $query))['data'][$field]);

        $deleted = $this->api('DELETE', $entity . '?uuid=' . $uuid . $query);
        $this->assertEquals(410, $deleted->getStatusCode(), (string) $deleted->getBody());

        if (!$softDelete) {
            $this->assertEquals(404, $this->api('GET', $entity . '?uuid=' . $uuid . $query)->getStatusCode(), 'gone after the delete');
        }
    }

    public function testADeletedUserIsNoLongerServedByUuid(): void
    {
        $user = $this->makeUser();
        $this->assertEquals(410, $this->api('DELETE', 'user?uuid=' . $user->uuid)->getStatusCode());

        $this->assertEquals(404, $this->api('GET', 'user?uuid=' . $user->uuid)->getStatusCode());
    }

    #[DataProvider('lifecycleProvider')]
    public function testChangingOrDeletingAMissingRecordIs404(string $entity, array $create, array $patch, mixed ...$ignored): void
    {
        $missing = \Ramsey\Uuid\Uuid::uuid4()->toString();

        $this->assertEquals(404, $this->api('PATCH', $entity . '?uuid=' . $missing, ['json' => $patch])->getStatusCode());
        $this->assertEquals(404, $this->api('DELETE', $entity . '?uuid=' . $missing)->getStatusCode());
    }

    public function testFormEncodedAndJsonBodiesAreEquivalent(): void
    {
        $json = $this->api('POST', 'page', ['json' => ['title' => 'Json', 'address' => 'json', 'content' => 'x']]);
        $form = $this->api('POST', 'page', ['form_params' => ['title' => 'Form', 'address' => 'form', 'content' => 'x']]);

        $this->assertEquals(201, $json->getStatusCode());
        $this->assertEquals(201, $form->getStatusCode());
    }

    // ----------------------------------------------------------------- validation

    public function testInvalidRecordsAreRejectedAndNothingIsStored(): void
    {
        $missingTitle = $this->api('POST', 'page', ['json' => ['address' => 'no-title', 'content' => 'x']]);
        $this->assertGreaterThanOrEqual(400, $missingTitle->getStatusCode());

        $this->assertEquals(201, $this->api('POST', 'page', ['json' => ['title' => 'Dup', 'address' => 'dup', 'content' => 'x']])->getStatusCode());
        $duplicate = $this->api('POST', 'page', ['json' => ['title' => 'Dup', 'address' => 'dup-2', 'content' => 'x']]);
        $this->assertGreaterThanOrEqual(400, $duplicate->getStatusCode());

        $this->assertSame(1, \App\Domain\Models\Page::count());
    }

    public function testPatchExplainsAnUnacceptableValue(): void
    {
        $this->makeUser(['username' => 'first', 'email' => 'taken@example.com']);
        $second = $this->makeUser(['username' => 'second', 'email' => 'free@example.com']);

        $response = $this->api('PATCH', 'user?uuid=' . $second->uuid, ['json' => ['email' => 'taken@example.com']]);

        $this->assertEquals(422, $response->getStatusCode());
        $this->assertEquals('EXCEPTION_EMAIL_ALREADY_EXISTS', $this->json($response)['data']);
    }

    public function testAnApiCreatedUserCannotSetAProtectedStatusByAccident(): void
    {
        $created = $this->api('POST', 'user', ['json' => ['username' => 'api-user', 'email' => 'api@example.com', 'password' => 'secret-pass']]);

        $this->assertEquals(201, $created->getStatusCode());
        $this->assertArrayNotHasKey('password', $this->json($created)['data']);
    }

    // -------------------------------------------------------------------- cup api

    public function testParameterIsNotReachableThroughThePublicApi(): void
    {
        $this->getService(ParameterService::class)->create(['name' => 'smtp_pass', 'value' => 'hunter2']);

        foreach (['GET', 'POST', 'PATCH', 'DELETE'] as $method) {
            $response = $this->api($method, 'parameter');

            $this->assertEquals(404, $response->getStatusCode(), $method);
            $this->assertStringNotContainsString('hunter2', (string) $response->getBody());
        }
    }

    public function testParameterIsServedToTheCupOnly(): void
    {
        $this->getService(ParameterService::class)->create(['name' => 'smtp_pass', 'value' => 'hunter2']);

        // a guest is sent to the cup login
        $guest = $this->http('GET', '/cup/api/v1/parameter');
        $this->assertEquals(307, $guest->getStatusCode());
        $this->assertStringStartsWith('/cup/login', $guest->getHeaderLine('Location'));

        // a user without access to that route is turned away
        $outsider = $this->loginAs($this->makeUser());
        $denied = $this->http('GET', '/cup/api/v1/parameter', ['headers' => $this->cookie($outsider['access_token'])]);
        $this->assertEquals(307, $denied->getStatusCode());
        $this->assertStringNotContainsString('hunter2', (string) $denied->getBody());

        // staff with the route in their group sees it
        $group = $this->getService(GroupService::class)->create(['title' => 'admins', 'access' => ['cup:api:v1:entity']]);
        $staff = $this->loginAs($this->makeUser(['group_uuid' => $group->uuid]));
        $allowed = $this->http('GET', '/cup/api/v1/parameter', ['headers' => $this->cookie($staff['access_token'])]);

        $this->assertEquals(200, $allowed->getStatusCode(), (string) $allowed->getBody());
        $this->assertStringContainsString('hunter2', (string) $allowed->getBody());
    }

    public function testCupApiAlsoIgnoresTheWithParameter(): void
    {
        $group = $this->getService(GroupService::class)->create(['title' => 'admins', 'access' => ['cup:api:v1:entity']]);
        $staff = $this->loginAs($this->makeUser(['group_uuid' => $group->uuid]));
        $unique = UserToken::first()->unique;

        $response = $this->http('GET', '/cup/api/v1/user?with[]=tokens', ['headers' => $this->cookie($staff['access_token'])]);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringNotContainsString($unique, (string) $response->getBody());
    }
}
