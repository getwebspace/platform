<?php declare(strict_types=1);

namespace tests\Domain\Service\User;

use App\Domain\Entities\User;
use App\Domain\Entities\User\Session as UserSession;
use App\Domain\Repository\UserRepository;
use App\Domain\Service\User\Exception\EmailAlreadyExistsException;
use App\Domain\Service\User\Exception\MissingUniqueValueException;
use App\Domain\Service\User\Exception\UsernameAlreadyExistsException;
use App\Domain\Service\User\Exception\UserNotFoundException;
use App\Domain\Service\User\Exception\WrongPasswordException;
use App\Domain\Service\User\UserService;
use App\Domain\Types\UserStatusType;
use Doctrine\ORM\EntityManager;
use tests\TestCase;

class UserServiceTest extends TestCase
{
    /**
     * @var EntityManager
     */
    protected $em;

    /**
     * @var UserService
     */
    protected $service;

    public function setUp(): void
    {
        parent::setUp();
        $this->em = $this->getEntityManager();
        $this->service = new UserService($this->em);
    }

    public function testCreateByRegisterSuccess1(): void
    {
        $data = [
            'username' => 'case1',
            'password' => '123456',
        ];

        // проверяем, что сервис создает пользователя
        $user = $this->service->createByRegister($data);
        $this->assertInstanceOf(User::class, $user);
        $this->assertSame($data['username'], $user->getUsername());

        // проверяем, что пользователь добавлен в базу
        /** @var UserRepository $userRepo */
        $userRepo = $this->em->getRepository(User::class);
        $u = $userRepo->findOneByUsername($data['username']);
        $this->assertInstanceOf(User::class, $u);
        $this->assertSame($data['username'], $u->getUsername());
    }

    public function testCreateByRegisterSuccess2(): void
    {
        $data = [
            'email' => 'case2@local.host',
            'password' => '123456',
        ];

        // проверяем, что сервис создает пользователя
        $user = $this->service->createByRegister($data);
        $this->assertInstanceOf(User::class, $user);
        $this->assertSame($data['email'], $user->getEmail());

        // проверяем, что пользователь добавлен в базу
        /** @var UserRepository $userRepo */
        $userRepo = $this->em->getRepository(User::class);
        $u = $userRepo->findOneByEmail($data['email']);
        $this->assertInstanceOf(User::class, $u);
        $this->assertSame($data['email'], $u->getEmail());
    }

    public function testCreateByRegisterWithMissingUniqueValue(): void
    {
        // считаем тест успешным, если сервис выкинет исключение
        $this->expectException(MissingUniqueValueException::class);

        // проверяем
        $this->service->createByRegister();
    }

    public function testCreateByRegisterWithUsernameExistent(): void
    {
        // считаем тест успешным, если сервис выкинет исключение
        $this->expectException(UsernameAlreadyExistsException::class);

        // добавим в базу пользователя с логином, который окажется занят
        $user = (new User)
            ->setUsername('case3')->setPassword('123456')
            ->setRegister('now')->setChange('now')
            ->setSession($session = (new UserSession)->setDate('now'));

        $this->em->persist($user);
        $this->em->persist($session);
        $this->em->flush();

        // проверяем
        $this->service->createByRegister([
            'username' => 'case3',
            'password' => '123456',
        ]);
    }

    public function testCreateByRegisterWithEmailExistent(): void
    {
        // считаем тест успешным, если сервис выкинет исключение
        $this->expectException(EmailAlreadyExistsException::class);

        // добавим в базу пользователя с логином, который окажется занят
        $user = (new User)
            ->setEmail('case4@local.host')->setPassword('123456')
            ->setRegister('now')->setChange('now')
            ->setSession($session = (new UserSession)->setDate('now'));

        $this->em->persist($user);
        $this->em->persist($session);
        $this->em->flush();

        // проверяем
        $this->service->createByRegister([
            'email' => 'case4@local.host',
            'password' => '123456',
        ]);
    }

    public function testGetByLoginSuccess1(): void
    {
        // создаем пользователя, которого будем тестировать
        $this->service->createByRegister([
            'username' => 'case5',
            'password' => '123456',
        ]);

        $data = [
            'identifier' => 'case5',
            'password' => '123456',
            'agent' => 'PHPUNIT',
            'ip' => '0.0.0.0',
        ];

        // проверяем, что пользователь найден
        $user = $this->service->getByLogin($data);
        $this->assertInstanceOf(User::class, $user);
        $this->assertSame($data['identifier'], $user->getUsername());
    }

    public function testGetByLoginSuccess2(): void
    {
        // создаем пользователя, которого будем тестировать
        $this->service->createByRegister([
            'email' => 'case6@local.host',
            'password' => '123456',
        ]);

        $data = [
            'identifier' => 'case6@local.host',
            'password' => '123456',
            'agent' => 'PHPUNIT',
            'ip' => '0.0.0.0',
        ];

        // проверяем, что пользователь найден
        $user = $this->service->getByLogin($data);
        $this->assertInstanceOf(User::class, $user);
        $this->assertSame($data['identifier'], $user->getEmail());
    }

    public function testGetByLoginWithUserNotFound1(): void
    {
        // считаем тест успешным, если сервис выкинет исключение
        $this->expectException(UserNotFoundException::class);

        $data = [
            'identifier' => 'case7',
            'password' => '123456',
            'agent' => 'PHPUNIT',
            'ip' => '0.0.0.0',
        ];

        // проверяем
        $this->service->getByLogin($data);
    }

    public function testGetByLoginWithUserNotFound2(): void
    {
        // считаем тест успешным, если сервис выкинет исключение
        $this->expectException(UserNotFoundException::class);

        $data = [
            'identifier' => 'case8@local.host',
            'password' => '123456',
            'agent' => 'PHPUNIT',
            'ip' => '0.0.0.0',
        ];

        // проверяем
        $this->service->getByLogin($data);
    }

    public function testGetByLoginWithWrongPassword(): void
    {
        // считаем тест успешным, если сервис выкинет исключение
        $this->expectException(WrongPasswordException::class);

        // создаем пользователя, которого будем тестировать
        $this->service->createByRegister([
            'username' => 'case9',
            'password' => '123456',
        ]);

        $data = [
            'identifier' => 'case9',
            'password' => '123456-wrong',
            'agent' => 'PHPUNIT',
            'ip' => '0.0.0.0',
        ];

        // проверяем
        $this->service->getByLogin($data);
    }

    public function testChange(): void
    {
        // создаем пользователя, которого будем тестировать
        $user = $this->service->createByCup([
            'username' => 'case10',
            'email' => 'case10@local.host',
            'password' => '123456',
        ]);

        $data = [
            'firstname' => 'Sofiya',
            'lastname' => 'Ilyina',
            'email' => 's.ilyina@local.host',
        ];

        $user = $this->service->change($user, $data);
        $this->assertSame($data['firstname'], $user->getFirstname());
        $this->assertSame($data['lastname'], $user->getLastname());
        $this->assertSame($data['email'], $user->getEmail());
    }

    public function testDelete(): void
    {
        // создаем пользователя, которого будем тестировать
        $user = $this->service->createByCup([
            'username' => 'case11',
            'email' => 'case11@local.host',
            'password' => '123456',
        ]);

        $user = $this->service->delete($user);
        $this->assertSame(UserStatusType::STATUS_DELETE, $user->getStatus());
    }

    public function testBlock(): void
    {
        // создаем пользователя, которого будем тестировать
        $user = $this->service->createByCup([
            'username' => 'case12',
            'email' => 'case12@local.host',
            'password' => '123456',
        ]);

        $user = $this->service->block($user);
        $this->assertSame(UserStatusType::STATUS_BLOCK, $user->getStatus());
    }
}
