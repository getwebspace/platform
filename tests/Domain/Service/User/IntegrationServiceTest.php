<?php declare(strict_types=1);

namespace tests\Domain\Service\User;

use App\Domain\Entities\User;
use App\Domain\Entities\User\Integration as UserIntegration;
use App\Domain\Repository\User\IntegrationRepository as UserIntegrationRepository;
use App\Domain\Service\User\Exception\IntegrationNotFoundException;
use App\Domain\Service\User\IntegrationService;
use App\Domain\Service\User\UserService;
use tests\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
class IntegrationServiceTest extends TestCase
{
    protected IntegrationService $service;

    private User $user;

    public function setUp(): void
    {
        parent::setUp();

        $this->service = $this->getService(IntegrationService::class);

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
            'provider' => $this->getFaker()->word,
            'unique' => $this->getFaker()->password(),
        ];

        $integration = $this->service->create($data);
        $this->assertInstanceOf(UserIntegration::class, $integration);
        $this->assertEquals($data['user'], $integration->getUser());
        $this->assertEquals($data['provider'], $integration->getProvider());
        $this->assertEquals($data['unique'], $integration->getUnique());

        /** @var UserIntegrationRepository $userIntegrationRepo */
        $userIntegrationRepo = $this->em->getRepository(UserIntegration::class);
        $i = $userIntegrationRepo->findOneByUuid($integration->getUuid());
        $this->assertInstanceOf(UserIntegration::class, $i);
        $this->assertEquals($data['user'], $i->getUser());
    }

    public function testReadSuccess(): void
    {
        $data = [
            'user' => $this->user,
            'provider' => $this->getFaker()->word,
            'unique' => $this->getFaker()->password(),
        ];

        $this->service->create($data);

        $integration = $this->service->read([
            'provider' => $data['provider'],
            'unique' => $data['unique'],
        ]);
        $this->assertInstanceOf(UserIntegration::class, $integration);
        $this->assertEquals($data['user'], $integration->getUser());
    }

    public function testDelete(): void
    {
        $this->expectException(IntegrationNotFoundException::class);

        $data = [
            'user' => $this->user,
            'provider' => $this->getFaker()->word,
            'unique' => $this->getFaker()->password(),
        ];

        $integration = $this->service->create($data);
        $this->service->delete($integration);
        $this->service->read([
            'provider' => $data['provider'],
            'unique' => $data['unique'],
        ]);
    }
}
