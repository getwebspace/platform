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

    public function testCreateSuccess1(): void
    {
        $data = [
            'username' => $this->getFaker()->userName,
            'password' => $this->getFaker()->password,
            'phone' => $this->getFaker()->e164PhoneNumber,
            'firstname' => $this->getFaker()->firstName,
            'lastname' => $this->getFaker()->lastName,
        ];

        // проверяем, что сервис создает пользователя
        $user = $this->service->create($data);
        $this->assertInstanceOf(User::class, $user);
        $this->assertSame($data['username'], $user->getUsername());

        // проверяем, что пользователь добавлен в базу
        /** @var UserRepository $userRepo */
        $userRepo = $this->em->getRepository(User::class);
        $u = $userRepo->findOneByUsername($data['username']);
        $this->assertInstanceOf(User::class, $u);
        $this->assertSame($data['username'], $u->getUsername());
    }

    public function testCreateSuccess2(): void
    {
        $data = [
            'email' => $this->getFaker()->email,
            'password' => $this->getFaker()->password,
            'phone' => $this->getFaker()->e164PhoneNumber,
            'firstname' => $this->getFaker()->firstName,
            'lastname' => $this->getFaker()->lastName,
        ];

        // проверяем, что сервис создает пользователя
        $user = $this->service->create($data);
        $this->assertInstanceOf(User::class, $user);
        $this->assertSame($data['email'], $user->getEmail());

        // проверяем, что пользователь добавлен в базу
        /** @var UserRepository $userRepo */
        $userRepo = $this->em->getRepository(User::class);
        $u = $userRepo->findOneByEmail($data['email']);
        $this->assertInstanceOf(User::class, $u);
        $this->assertSame($data['email'], $u->getEmail());
    }

    public function testCreateWithMissingUniqueValue(): void
    {
        // считаем тест успешным, если сервис выкинет исключение
        $this->expectException(MissingUniqueValueException::class);

        // проверяем
        $this->service->create();
    }

    public function testCreateWithWrongPassword(): void
    {
        // считаем тест успешным, если сервис выкинет исключение
        $this->expectException(WrongPasswordException::class);

        // проверяем
        $this->service->create([
            'username' => $this->getFaker()->userName,
            'email' => $this->getFaker()->email,
        ]);
    }

    public function testCreateWithUsernameExistent(): void
    {
        // считаем тест успешным, если сервис выкинет исключение
        $this->expectException(UsernameAlreadyExistsException::class);

        $data = [
            'username' => $this->getFaker()->userName,
            'password' => $this->getFaker()->password,
        ];

        // добавим в базу пользователя с логином, который окажется занят
        $user = (new User)
            ->setUsername($data['username'])
            ->setPassword($data['password'])
            ->setRegister('now')->setChange('now')
            ->setSession($session = (new UserSession)->setDate('now'));

        $this->em->persist($user);
        $this->em->persist($session);
        $this->em->flush();

        // проверяем
        $this->service->create($data);
    }

    public function testCreateWithEmailExistent(): void
    {
        // считаем тест успешным, если сервис выкинет исключение
        $this->expectException(EmailAlreadyExistsException::class);

        $data = [
            'email' => $this->getFaker()->email,
            'password' => $this->getFaker()->password,
        ];

        // добавим в базу пользователя с логином, который окажется занят
        $user = (new User)
            ->setEmail($data['email'])
            ->setPassword($data['password'])
            ->setRegister('now')->setChange('now')
            ->setSession($session = (new UserSession)->setDate('now'));

        $this->em->persist($user);
        $this->em->persist($session);
        $this->em->flush();

        // проверяем
        $this->service->create($data);
    }

    public function testReadSuccess1(): void
    {
        $data = [
            'username' => $this->getFaker()->userName,
            'password' => $this->getFaker()->password,
        ];

        // создаем пользователя, которого будем тестировать
        $this->service->create($data);

        // проверяем, что пользователь найден
        $user = $this->service->read(array_merge($data, ['agent' => $this->getFaker()->userAgent, 'ip' => $this->getFaker()->ipv4]));
        $this->assertInstanceOf(User::class, $user);
        $this->assertSame($data['username'], $user->getUsername());
    }

    public function testReadSuccess2(): void
    {
        $data = [
            'email' => $this->getFaker()->email,
            'password' => $this->getFaker()->password,
        ];

        // создаем пользователя, которого будем тестировать
        $this->service->create($data);

        // проверяем, что пользователь найден
        $user = $this->service->read(array_merge($data, ['agent' => $this->getFaker()->userAgent, 'ip' => $this->getFaker()->ipv4]));
        $this->assertInstanceOf(User::class, $user);
        $this->assertSame($data['email'], $user->getEmail());
    }

    public function testReadWithUserNotFound1(): void
    {
        // считаем тест успешным, если сервис выкинет исключение
        $this->expectException(UserNotFoundException::class);

        // проверяем
        $this->service->read([
            'identifier' => $this->getFaker()->userName,
        ]);
    }

    public function testReadWithUserNotFound2(): void
    {
        // считаем тест успешным, если сервис выкинет исключение
        $this->expectException(UserNotFoundException::class);

        // проверяем
        $this->service->read([
            'identifier' => $this->getFaker()->email,
        ]);
    }

    public function testReadWithWrongPassword(): void
    {
        // считаем тест успешным, если сервис выкинет исключение
        $this->expectException(WrongPasswordException::class);

        $data = [
            'username' => $this->getFaker()->userName,
            'password' => $this->getFaker()->password,
        ];

        // создаем пользователя, которого будем тестировать
        $this->service->create($data);

        // проверяем
        $this->service->read([
            'username' => $data['username'],
            'password' => $data['password'] . '-wrong',
        ]);
    }

    public function testUpdate(): void
    {
        // создаем пользователя, которого будем тестировать
        $user = $this->service->create([
            'username' => $this->getFaker()->userName,
            'email' => $this->getFaker()->email,
            'password' => $this->getFaker()->password,
        ]);

        $data = [
            'firstname' => $this->getFaker()->firstName,
            'lastname' => $this->getFaker()->lastName,
            'email' => $this->getFaker()->email,
        ];

        $user = $this->service->update($user, $data);
        $this->assertSame($data['firstname'], $user->getFirstname());
        $this->assertSame($data['lastname'], $user->getLastname());
        $this->assertSame($data['email'], $user->getEmail());
    }

    public function testUpdatePhone(): void
    {
        // создаем пользователя, которого будем тестировать
        $user = $this->service->create([
            'username' => $this->getFaker()->userName,
            'password' => $this->getFaker()->password,
        ]);

        // флаг простой проверки телефона
        $_ENV['SIMPLE_PHONE_CHECK'] = 1;

        // 1
        $phone = '89991112233';
        $this->service->update($user, ['phone' => $phone]);
        $this->assertSame($phone, $user->getPhone());

        // 2
        $this->service->update($user, ['phone' => '8 (999) 111-22-33']);
        $this->assertSame('89991112233', $user->getPhone());

        // убираем флаг
        unset($_ENV['SIMPLE_PHONE_CHECK']);

        // 3
        $phone = '+79991112233';
        $this->service->update($user, ['phone' => $phone]);
        $this->assertSame($phone, $user->getPhone());

        // 4
        $this->service->update($user, ['phone' => '+7 (999) 111-22-33']);
        $this->assertSame('+79991112233', $user->getPhone());
    }

    public function testDelete(): void
    {
        // создаем пользователя, которого будем тестировать
        $user = $this->service->create([
            'username' => $this->getFaker()->userName,
            'email' => $this->getFaker()->email,
            'password' => $this->getFaker()->password,
        ]);

        $user = $this->service->delete($user);
        $this->assertSame(UserStatusType::STATUS_DELETE, $user->getStatus());
    }

    public function testDeleteWithNotFound(): void
    {
        // считаем тест успешным, если сервис выкинет исключение
        $this->expectException(UserNotFoundException::class);

        $this->service->block(null);
    }

    public function testBlock(): void
    {
        // создаем пользователя, которого будем тестировать
        $user = $this->service->create([
            'username' => $this->getFaker()->userName,
            'email' => $this->getFaker()->email,
            'password' => $this->getFaker()->password,
        ]);

        $user = $this->service->block($user);
        $this->assertSame(UserStatusType::STATUS_BLOCK, $user->getStatus());
    }

    public function testBlockWithNotFound(): void
    {
        // считаем тест успешным, если сервис выкинет исключение
        $this->expectException(UserNotFoundException::class);

        $this->service->block(null);
    }
}
