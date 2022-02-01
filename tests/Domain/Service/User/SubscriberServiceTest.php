<?php declare(strict_types=1);

namespace tests\Domain\Service\User;

use App\Domain\Entities\User\Subscriber as UserSubscriber;
use App\Domain\Repository\User\SubscriberRepository as UserSubscriberRepository;
use App\Domain\Service\User\Exception\EmailAlreadyExistsException;
use App\Domain\Service\User\Exception\MissingUniqueValueException;
use App\Domain\Service\User\Exception\UserNotFoundException;
use App\Domain\Service\User\SubscriberService as UserSubscriberService;
use tests\TestCase;

/**
 * @internal
 * @coversNothing
 */
class SubscriberServiceTest extends TestCase
{
    protected UserSubscriberService $service;

    public function setUp(): void
    {
        parent::setUp();

        $this->service = $this->getService(UserSubscriberService::class);
    }

    public function testCreateSuccess(): void
    {
        $data = [
            'email' => $this->getFaker()->email,
            'date' => $this->getFaker()->dateTime,
        ];

        $userSubscriber = $this->service->create($data);
        $this->assertInstanceOf(UserSubscriber::class, $userSubscriber);
        $this->assertSame($data['email'], $userSubscriber->getEmail());

        /** @var UserSubscriberRepository $userSubscriberRepo */
        $userSubscriberRepo = $this->em->getRepository(UserSubscriber::class);
        $us = $userSubscriberRepo->findOneByEmail($data['email']);
        $this->assertInstanceOf(UserSubscriber::class, $us);
        $this->assertSame($data['email'], $us->getEmail());
    }

    public function testCreateWithMissingUniqueValue(): void
    {
        $this->expectException(MissingUniqueValueException::class);

        $this->service->create();
    }

    public function testCreateWithUsernameExistent(): void
    {
        $this->expectException(EmailAlreadyExistsException::class);

        $data = [
            'email' => $this->getFaker()->email,
            'date' => $this->getFaker()->dateTime,
        ];

        $userSubscriber = (new UserSubscriber())
            ->setEmail($data['email'])
            ->setDate($data['date']);

        $this->em->persist($userSubscriber);
        $this->em->flush();

        $this->service->create($data);
    }

    public function testReadSuccess(): void
    {
        $data = [
            'email' => $this->getFaker()->email,
            'date' => $this->getFaker()->dateTime,
        ];

        $this->service->create($data);

        $userSubscriber = $this->service->read(['email' => $data['email']]);
        $this->assertInstanceOf(UserSubscriber::class, $userSubscriber);
        $this->assertSame($data['email'], $userSubscriber->getEmail());
    }

    public function testReadWithUserNotFound1(): void
    {
        $this->expectException(UserNotFoundException::class);

        $this->service->read(['uuid' => $this->getFaker()->uuid]);
    }

    public function testReadWithUserNotFound2(): void
    {
        $this->expectException(UserNotFoundException::class);

        $this->service->read(['email' => $this->getFaker()->email]);
    }

    public function testUpdate(): void
    {
        $userSubscriber = $this->service->create([
            'email' => $this->getFaker()->email,
            'date' => $this->getFaker()->dateTime,
        ]);

        $data = [
            'email' => $this->getFaker()->email,
        ];

        $user = $this->service->update($userSubscriber, $data);
        $this->assertSame($data['email'], $user->getEmail());
    }

    public function testUpdateWithUserNotFound(): void
    {
        $this->expectException(UserNotFoundException::class);

        $this->service->update(null);
    }

    public function testDelete(): void
    {
        $page = $this->service->create([
            'email' => $this->getFaker()->email,
            'date' => $this->getFaker()->dateTime,
        ]);

        $result = $this->service->delete($page);

        $this->assertTrue($result);
    }

    public function testDeleteWithNotFound(): void
    {
        $this->expectException(UserNotFoundException::class);

        $this->service->delete(null);
    }
}
