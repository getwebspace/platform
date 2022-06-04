<?php declare(strict_types=1);

namespace tests\Domain\Service\User;

use App\Domain\Entities\User;
use App\Domain\Repository\UserRepository;
use App\Domain\Service\User\Exception\EmailAlreadyExistsException;
use App\Domain\Service\User\Exception\MissingUniqueValueException;
use App\Domain\Service\User\Exception\PhoneAlreadyExistsException;
use App\Domain\Service\User\Exception\UsernameAlreadyExistsException;
use App\Domain\Service\User\Exception\UserNotFoundException;
use App\Domain\Service\User\Exception\WrongPasswordException;
use App\Domain\Service\User\UserService;
use App\Domain\Types\UserStatusType;
use tests\TestCase;

/**
 * @internal
 * @coversNothing
 */
class UserServiceTest extends TestCase
{
    protected UserService $service;

    public function setUp(): void
    {
        parent::setUp();

        $this->service = $this->getService(UserService::class);
    }

    public function testCreateSuccess1(): void
    {
        $data = [
            'username' => $this->getFaker()->userName,
            'password' => $this->getFaker()->password,
            'phone' => $this->getFaker()->e164PhoneNumber,
            'firstname' => $this->getFaker()->firstName,
            'lastname' => $this->getFaker()->lastName,
            'patronymic' => $this->getFaker()->word,
            'gender' => $this->getFaker()->word,
            'company' => [
                'title' => $this->getFaker()->word,
                'position' => $this->getFaker()->word,
            ],
            'legal' => [
                'code' => $this->getFaker()->word,
                'number' => $this->getFaker()->word,
            ],
            'website' => $this->getFaker()->url,
            'source' => $this->getFaker()->word,
            'address' => $this->getFaker()->address,
            'additional' => $this->getFaker()->text,
            'auth_code' => (string) $this->getFaker()->numberBetween(0, 10000),
            'language' => $this->getFaker()->languageCode,
            'external_id' => $this->getFaker()->uuid,
        ];

        $user = $this->service->create($data);
        $this->assertInstanceOf(User::class, $user);
        $this->assertSame($data['username'], $user->getUsername());
        $this->assertSame($data['phone'], $user->getPhone());
        $this->assertSame($data['firstname'], $user->getFirstname());
        $this->assertSame($data['lastname'], $user->getLastname());
        $this->assertSame($data['patronymic'], $user->getPatronymic());
        $this->assertSame($data['gender'], $user->getGender());
        $this->assertSame($data['company'], $user->getCompany());
        $this->assertSame($data['legal'], $user->getLegal());
        $this->assertSame($data['website'], $user->getWebsite());
        $this->assertSame($data['source'], $user->getSource());
        $this->assertSame($data['address'], $user->getAddress());
        $this->assertSame($data['additional'], $user->getAdditional());
        $this->assertSame($data['auth_code'], $user->getAuthCode());
        $this->assertSame($data['language'], $user->getLanguage());
        $this->assertSame($data['external_id'], $user->getExternalId());

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

        $user = $this->service->create($data);
        $this->assertInstanceOf(User::class, $user);
        $this->assertSame($data['email'], $user->getEmail());
        $this->assertSame($data['phone'], $user->getPhone());
        $this->assertSame($data['firstname'], $user->getFirstname());
        $this->assertSame($data['lastname'], $user->getLastname());

        /** @var UserRepository $userRepo */
        $userRepo = $this->em->getRepository(User::class);
        $u = $userRepo->findOneByEmail($data['email']);
        $this->assertInstanceOf(User::class, $u);
        $this->assertSame($data['email'], $u->getEmail());
    }

    public function testCreateSuccess3(): void
    {
        $data = [
            'email' => $this->getFaker()->email,
            'password' => $this->getFaker()->password,
            'phone' => $this->getFaker()->e164PhoneNumber,
            'firstname' => $this->getFaker()->firstName,
            'lastname' => $this->getFaker()->lastName,
        ];

        $user = $this->service->create($data);
        $this->assertInstanceOf(User::class, $user);
        $this->assertSame($data['email'], $user->getEmail());
        $this->assertSame($data['phone'], $user->getPhone());
        $this->assertSame($data['firstname'], $user->getFirstname());
        $this->assertSame($data['lastname'], $user->getLastname());

        /** @var UserRepository $userRepo */
        $userRepo = $this->em->getRepository(User::class);
        $u = $userRepo->findOneByPhone($data['phone']);
        $this->assertInstanceOf(User::class, $u);
        $this->assertSame($data['phone'], $u->getPhone());
    }

    public function testCreateWithMissingUniqueValue(): void
    {
        $this->expectException(MissingUniqueValueException::class);

        $this->service->create();
    }

    public function testCreateWithWrongPassword(): void
    {
        $this->expectException(WrongPasswordException::class);

        $this->service->create([
            'username' => $this->getFaker()->userName,
            'email' => $this->getFaker()->email,
        ]);
    }

    public function testCreateWithUsernameExistent(): void
    {
        $this->expectException(UsernameAlreadyExistsException::class);

        $data = [
            'username' => $this->getFaker()->userName,
            'password' => $this->getFaker()->password,
        ];

        $user = (new User())
            ->setUsername($data['username'])
            ->setPassword($data['password'])
            ->setRegister('now')->setChange('now');

        $this->em->persist($user);
        $this->em->flush();

        $this->service->create($data);
    }

    public function testCreateWithEmailExistent(): void
    {
        $this->expectException(EmailAlreadyExistsException::class);

        $data = [
            'email' => $this->getFaker()->email,
            'password' => $this->getFaker()->password,
        ];

        $user = (new User())
            ->setEmail($data['email'])
            ->setPassword($data['password'])
            ->setRegister('now')->setChange('now');

        $this->em->persist($user);
        $this->em->flush();

        $this->service->create($data);
    }

    public function testCreateWithPhoneExistent(): void
    {
        $this->expectException(PhoneAlreadyExistsException::class);

        $data = [
            'phone' => $this->getFaker()->e164PhoneNumber,
            'password' => $this->getFaker()->password,
        ];

        $user = (new User())
            ->setPhone($data['phone'])
            ->setPassword($data['password'])
            ->setRegister('now')->setChange('now');

        $this->em->persist($user);
        $this->em->flush();

        $this->service->create($data);
    }

    public function testReadSuccess1(): void
    {
        $data = [
            'username' => $this->getFaker()->userName,
            'password' => $this->getFaker()->password,
        ];

        $this->service->create($data);

        $user = $this->service->read(['identifier' => $data['username']]);
        $this->assertInstanceOf(User::class, $user);
        $this->assertSame($data['username'], $user->getUsername());
    }

    public function testReadSuccess2(): void
    {
        $data = [
            'email' => $this->getFaker()->email,
            'password' => $this->getFaker()->password,
        ];

        $this->service->create($data);

        $user = $this->service->read(['identifier' => $data['email']]);
        $this->assertInstanceOf(User::class, $user);
        $this->assertSame($data['email'], $user->getEmail());
    }

    public function testReadSuccess3(): void
    {
        $data = [
            'phone' => $this->getFaker()->e164PhoneNumber,
            'password' => $this->getFaker()->password,
        ];

        $this->service->create($data);

        $user = $this->service->read(['identifier' => $data['phone']]);
        $this->assertInstanceOf(User::class, $user);
        $this->assertSame($data['phone'], $user->getPhone());
    }

    public function testReadWithUserNotFound1(): void
    {
        $this->expectException(UserNotFoundException::class);

        $this->service->read([
            'identifier' => $this->getFaker()->userName,
        ]);
    }

    public function testReadWithUserNotFound2(): void
    {
        $this->expectException(UserNotFoundException::class);

        $this->service->read([
            'identifier' => $this->getFaker()->email,
        ]);
    }

    public function testReadWithUserNotFound3(): void
    {
        $this->expectException(UserNotFoundException::class);

        $this->service->read([
            'identifier' => $this->getFaker()->e164PhoneNumber,
        ]);
    }

    public function testReadWithWrongPassword(): void
    {
        $this->expectException(WrongPasswordException::class);

        $data = [
            'username' => $this->getFaker()->userName,
            'password' => $this->getFaker()->password,
        ];

        $this->service->create($data);

        $this->service->read([
            'username' => $data['username'],
            'password' => $data['password'] . '-wrong',
        ]);
    }

    public function testUpdate(): void
    {
        $user = $this->service->create([
            'username' => $this->getFaker()->userName,
            'email' => $this->getFaker()->email,
            'password' => $this->getFaker()->password,
            'address' => $this->getFaker()->address,
            'additional' => $this->getFaker()->text,
            'auth_code' => (string) $this->getFaker()->numberBetween(0, 10000),
            'language' => $this->getFaker()->languageCode,
            'external_id' => $this->getFaker()->uuid,
        ]);

        $data = [
            'username' => $this->getFaker()->userName,
            'password' => $this->getFaker()->password,
            'phone' => $this->getFaker()->e164PhoneNumber,
            'firstname' => $this->getFaker()->firstName,
            'lastname' => $this->getFaker()->lastName,
            'patronymic' => $this->getFaker()->word,
            'gender' => $this->getFaker()->word,
            'company' => [
                'title' => $this->getFaker()->word,
                'position' => $this->getFaker()->word,
            ],
            'legal' => [
                'code' => $this->getFaker()->word,
                'number' => $this->getFaker()->word,
            ],
            'website' => $this->getFaker()->url,
            'source' => $this->getFaker()->word,
            'address' => $this->getFaker()->address,
            'additional' => $this->getFaker()->text,
            'email' => $this->getFaker()->email,
            'auth_code' => (string) $this->getFaker()->numberBetween(0, 10000),
            'language' => $this->getFaker()->languageCode,
            'external_id' => $this->getFaker()->uuid,
        ];

        $user = $this->service->update($user, $data);
        $this->assertSame($data['username'], $user->getUsername());
        $this->assertSame($data['firstname'], $user->getFirstname());
        $this->assertSame($data['lastname'], $user->getLastname());
        $this->assertSame($data['patronymic'], $user->getPatronymic());
        $this->assertSame($data['gender'], $user->getGender());
        $this->assertSame($data['company'], $user->getCompany());
        $this->assertSame($data['legal'], $user->getLegal());
        $this->assertSame($data['website'], $user->getWebsite());
        $this->assertSame($data['source'], $user->getSource());
        $this->assertSame($data['address'], $user->getAddress());
        $this->assertSame($data['additional'], $user->getAdditional());
        $this->assertSame($data['email'], $user->getEmail());
        $this->assertSame($data['auth_code'], $user->getAuthCode());
        $this->assertSame($data['language'], $user->getLanguage());
        $this->assertSame($data['external_id'], $user->getExternalId());
    }

    public function testUpdateWithUserNotFound(): void
    {
        $this->expectException(UserNotFoundException::class);

        $this->service->update(null);
    }

    public function testUpdatePhone(): void
    {
        $user = $this->service->create([
            'username' => $this->getFaker()->userName,
            'password' => $this->getFaker()->password,
        ]);

        // флаг простой проверки телефона
        $_ENV['SIMPLE_PHONE_CHECK'] = 1;

        // 1
        $phone = $this->getFaker()->phoneNumber;
        $this->service->update($user, ['phone' => $phone]);
        $this->assertSame(str_replace(['(', ')', ' ', '.', '-'], '', $phone), $user->getPhone());

        // 2
        $phone = '89991112233';
        $this->service->update($user, ['phone' => $phone]);
        $this->assertSame($phone, $user->getPhone());

        // 3
        $this->service->update($user, ['phone' => '8 (999) 111-22-33']);
        $this->assertSame('89991112233', $user->getPhone());

        // убираем флаг
        unset($_ENV['SIMPLE_PHONE_CHECK']);

        // 4
        $phone = '+79991112233';
        $this->service->update($user, ['phone' => $phone]);
        $this->assertSame($phone, $user->getPhone());

        // 5
        $this->service->update($user, ['phone' => '+7 (999) 111-22-33']);
        $this->assertSame('+79991112233', $user->getPhone());
    }

    public function testDelete(): void
    {
        $user = $this->service->create([
            'phone' => $this->getFaker()->e164PhoneNumber,
            'username' => $this->getFaker()->userName,
            'email' => $this->getFaker()->email,
            'password' => $this->getFaker()->password,
        ]);

        $user = $this->service->delete($user);
        $this->assertSame(UserStatusType::STATUS_DELETE, $user->getStatus());
    }

    public function testDeleteWithNotFound(): void
    {
        $this->expectException(UserNotFoundException::class);

        $this->service->delete(null);
    }

    public function testBlock(): void
    {
        $user = $this->service->create([
            'phone' => $this->getFaker()->e164PhoneNumber,
            'username' => $this->getFaker()->userName,
            'email' => $this->getFaker()->email,
            'password' => $this->getFaker()->password,
        ]);

        $user = $this->service->block($user);
        $this->assertSame(UserStatusType::STATUS_BLOCK, $user->getStatus());
    }

    public function testBlockWithNotFound(): void
    {
        $this->expectException(UserNotFoundException::class);

        $this->service->block(null);
    }
}
