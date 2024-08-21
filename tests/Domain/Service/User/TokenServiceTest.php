<?php declare(strict_types=1);

namespace Domain\Service\User;

use App\Domain\Models\User;
use App\Domain\Models\UserToken;
use App\Domain\Service\User\Exception\TokenNotFoundException;
use App\Domain\Service\User\TokenService as UserTokenService;
use App\Domain\Service\User\UserService;
use tests\TestCase;

/**
 * @internal
 *
 * #[CoversNothing]
 */
class TokenServiceTest extends TestCase
{
    protected UserTokenService $service;

    /**
     * @var User test user
     */
    private User $user;

    public function setUp(): void
    {
        parent::setUp();

        $this->service = $this->getService(UserTokenService::class);

        $this->user = $this->getService(UserService::class)->create([
            'username' => $this->getFaker()->userName,
            'email' => $this->getFaker()->email,
            'phone' => $this->getFaker()->e164PhoneNumber,
            'password' => $this->getFaker()->password,
            'firstname' => $this->getFaker()->firstName,
            'lastname' => $this->getFaker()->lastName,
            'address' => $this->getFaker()->address,
            'additional' => $this->getFaker()->company,
        ]);
    }

    public function testCreateSuccess(): void
    {
        $data = [
            'user_uuid' => $this->user->uuid,
            'unique' => $this->getFaker()->word,
            'comment' => $this->getFaker()->word,
            'agent' => $this->getFaker()->userAgent,
            'ip' => $this->getFaker()->ipv4,
            'date' => 'now',
        ];

        $token = $this->service->create($data);
        $this->assertInstanceOf(UserToken::class, $token);
        $this->assertEquals($data['unique'], $token->unique);
        $this->assertEquals($data['comment'], $token->comment);
        $this->assertEquals($data['agent'], $token->agent);
        $this->assertEquals($data['ip'], $token->ip);
    }

    public function testReadSuccess(): void
    {
        $data = [
            'user_uuid' => $this->user->uuid,
            'unique' => $this->getFaker()->word,
            'comment' => $this->getFaker()->word,
            'agent' => $this->getFaker()->userAgent,
            'ip' => $this->getFaker()->ipv4,
            'date' => 'now',
        ];

        $this->service->create($data);

        $token = $this->service->read([
            'unique' => $data['unique'],
        ]);
        $this->assertInstanceOf(UserToken::class, $token);
        $this->assertEquals($this->user->attributesToArray(), $token->user->attributesToArray());
    }

    public function testDelete(): void
    {
        $this->expectException(TokenNotFoundException::class);

        $data = [
            'user_uuid' => $this->user->uuid,
            'unique' => $this->getFaker()->word,
            'comment' => $this->getFaker()->word,
            'agent' => $this->getFaker()->userAgent,
            'ip' => $this->getFaker()->ipv4,
            'date' => 'now',
        ];

        $token = $this->service->create($data);
        $this->service->delete($token);
        $this->service->read([
            'unique' => $data['unique'],
        ]);
    }
}
