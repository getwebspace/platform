<?php declare(strict_types=1);

namespace Domain\Service\User;

use App\Domain\Entities\User;
use App\Domain\Entities\User\Token as UserToken;
use App\Domain\Repository\User\TokenRepository as UserTokenRepository;
use App\Domain\Service\User\Exception\TokenNotFoundException;
use App\Domain\Service\User\TokenService as UserTokenService;
use App\Domain\Service\User\UserService;
use tests\TestCase;

/**
 * @internal
 *
 * @coversNothing
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
            'username' => $this->getFaker()->word,
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
            'user' => $this->user,
            'unique' => $this->getFaker()->word,
            'comment' => $this->getFaker()->word,
            'agent' => $this->getFaker()->userAgent,
            'ip' => $this->getFaker()->ipv4,
            'date' => 'now',
        ];

        $token = $this->service->create($data);
        $this->assertInstanceOf(UserToken::class, $token);
        $this->assertSame($data['unique'], $token->getUnique());
        $this->assertSame($data['comment'], $token->getComment());
        $this->assertSame($data['agent'], $token->getAgent());
        $this->assertSame($data['ip'], $token->getIp());

        /** @var UserTokenRepository $userTokenRepo */
        $userTokenRepo = $this->em->getRepository(UserToken::class);
        $t = $userTokenRepo->findOneByUuid($token->getUuid());
        $this->assertInstanceOf(UserToken::class, $t);
        $this->assertSame($data['unique'], $t->getUnique());
        $this->assertSame($data['comment'], $t->getComment());
        $this->assertSame($data['agent'], $t->getAgent());
        $this->assertSame($data['ip'], $t->getIp());
    }

    public function testReadSuccess(): void
    {
        $data = [
            'user' => $this->user,
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
        $this->assertSame($data['user'], $token->getUser());
    }

    public function testDelete(): void
    {
        $this->expectException(TokenNotFoundException::class);

        $data = [
            'user' => $this->user,
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
