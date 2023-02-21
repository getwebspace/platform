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

        $session = $this->service->create($data);
        $this->assertInstanceOf(UserToken::class, $session);
        $this->assertSame($data['unique'], $session->getUnique());
        $this->assertSame($data['comment'], $session->getComment());
        $this->assertSame($data['agent'], $session->getAgent());
        $this->assertSame($data['ip'], $session->getIp());

        /** @var UserTokenRepository $userTokenRepo */
        $userTokenRepo = $this->em->getRepository(UserToken::class);
        $s = $userTokenRepo->findOneByUuid($session->getUuid());
        $this->assertInstanceOf(UserToken::class, $s);
        $this->assertSame($data['unique'], $s->getUnique());
        $this->assertSame($data['comment'], $s->getComment());
        $this->assertSame($data['agent'], $s->getAgent());
        $this->assertSame($data['ip'], $s->getIp());
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
