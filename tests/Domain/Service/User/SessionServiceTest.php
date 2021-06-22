<?php declare(strict_types=1);

namespace tests\Domain\Service\User;

use App\Domain\Entities\User;
use App\Domain\Entities\User\Session as UserSession;
use App\Domain\Repository\User\SessionRepository as UserSessionRepository;
use App\Domain\Service\User\SessionService as UserSessionService;
use App\Domain\Service\User\UserService;
use Doctrine\ORM\EntityManager;
use tests\TestCase;

/**
 * @internal
 * @coversNothing
 */
class SessionServiceTest extends TestCase
{
    /**
     * @var EntityManager
     */
    protected $em;

    /**
     * @var UserSessionService
     */
    protected $service;

    /**
     * @var User test user
     */
    private User $user;

    public function setUp(): void
    {
        parent::setUp();
        $this->em = $this->getEntityManager();
        $this->service = UserSessionService::getWithEntityManager($this->em);
        $this->user = UserService::getWithEntityManager($this->em)->create([
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
            'agent' => $this->getFaker()->userAgent,
            'ip' => $this->getFaker()->ipv4,
        ];

        $session = $this->service->create($data);
        $this->assertInstanceOf(UserSession::class, $session);
        $this->assertSame($data['agent'], $session->getAgent());
        $this->assertSame($data['ip'], $session->getIp());

        /** @var UserSessionRepository $userSessionRepo */
        $userSessionRepo = $this->em->getRepository(UserSession::class);
        $s = $userSessionRepo->findOneByUuid($session->getUuid());
        $this->assertInstanceOf(UserSession::class, $s);
        $this->assertSame($data['agent'], $s->getAgent());
        $this->assertSame($data['ip'], $s->getIp());
    }
}
