<?php declare(strict_types=1);

namespace tests\Domain\Service\User;

use App\Domain\Casts\User\Status as UserStatus;
use App\Domain\Models\User;
use App\Domain\Service\User\Exception\EmailAlreadyExistsException;
use App\Domain\Service\User\Exception\MissingUniqueValueException;
use App\Domain\Service\User\Exception\PhoneAlreadyExistsException;
use App\Domain\Service\User\Exception\UsernameAlreadyExistsException;
use App\Domain\Service\User\Exception\UserNotFoundException;
use App\Domain\Service\User\Exception\WrongPasswordException;
use App\Domain\Service\User\UserService;
use tests\TestCase;

/**
 * @internal
 *
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
            'country' => $this->getFaker()->country,
            'city' => $this->getFaker()->city,
            'address' => $this->getFaker()->address,
            'postcode' => $this->getFaker()->postcode,
            'additional' => $this->getFaker()->text,
            'language' => $this->getFaker()->languageCode,
            'external_id' => $this->getFaker()->uuid,
        ];

        $user = $this->service->create($data);
        $this->assertInstanceOf(User::class, $user);
        $this->assertEquals($data['username'], $user->username);
        $this->assertEquals($data['phone'], $user->phone);
        $this->assertEquals($data['firstname'], $user->firstname);
        $this->assertEquals($data['lastname'], $user->lastname);
        $this->assertEquals($data['patronymic'], $user->patronymic);
        $this->assertEquals($data['gender'], $user->gender);
        $this->assertEquals($data['company'], $user->company);
        $this->assertEquals($data['legal'], $user->legal);
        $this->assertEquals($data['website'], $user->website);
        $this->assertEquals($data['source'], $user->source);
        $this->assertEquals($data['country'], $user->country);
        $this->assertEquals($data['city'], $user->city);
        $this->assertEquals($data['address'], $user->address);
        $this->assertEquals($data['postcode'], $user->postcode);
        $this->assertEquals($data['additional'], $user->additional);
        $this->assertEquals($data['language'], $user->language);
        $this->assertEquals($data['external_id'], $user->external_id);
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
        $this->assertEquals($data['email'], $user->email);
        $this->assertEquals($data['phone'], $user->phone);
        $this->assertEquals($data['firstname'], $user->firstname);
        $this->assertEquals($data['lastname'], $user->lastname);
    }

    public function testCreateWithMissingUniqueValue(): void
    {
        $this->expectException(MissingUniqueValueException::class);

        $this->service->create();
    }

    public function testCreateWithUsernameExistent(): void
    {
        $this->expectException(UsernameAlreadyExistsException::class);

        $data = [
            'username' => $this->getFaker()->userName,
            'password' => $this->getFaker()->password,
        ];

        User::create($data);

        $this->service->create($data);
    }

    public function testCreateWithEmailExistent(): void
    {
        $this->expectException(EmailAlreadyExistsException::class);

        $data = [
            'email' => $this->getFaker()->email,
            'password' => $this->getFaker()->password,
        ];

        User::create($data);

        $this->service->create($data);
    }

    public function testCreateWithPhoneExistent(): void
    {
        $this->expectException(PhoneAlreadyExistsException::class);

        $data = [
            'phone' => $this->getFaker()->e164PhoneNumber,
            'password' => $this->getFaker()->password,
        ];

        User::create($data);

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
        $this->assertEquals($data['username'], $user->username);
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
        $this->assertEquals($data['email'], $user->email);
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
        $this->assertEquals($data['phone'], $user->phone);
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
            'country' => $this->getFaker()->country,
            'city' => $this->getFaker()->city,
            'address' => $this->getFaker()->address,
            'postcode' => $this->getFaker()->postcode,
            'additional' => $this->getFaker()->text,
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
            'country' => $this->getFaker()->country,
            'city' => $this->getFaker()->city,
            'address' => $this->getFaker()->address,
            'postcode' => $this->getFaker()->postcode,
            'additional' => $this->getFaker()->text,
            'email' => $this->getFaker()->email,
            'language' => $this->getFaker()->languageCode,
            'external_id' => $this->getFaker()->uuid,
        ];

        $user = $this->service->update($user, $data);
        $this->assertEquals($data['username'], $user->username);
        $this->assertEquals($data['firstname'], $user->firstname);
        $this->assertEquals($data['lastname'], $user->lastname);
        $this->assertEquals($data['patronymic'], $user->patronymic);
        $this->assertEquals($data['gender'], $user->gender);
        $this->assertEquals($data['company'], $user->company);
        $this->assertEquals($data['legal'], $user->legal);
        $this->assertEquals($data['website'], $user->website);
        $this->assertEquals($data['source'], $user->source);
        $this->assertEquals($data['country'], $user->country);
        $this->assertEquals($data['city'], $user->city);
        $this->assertEquals($data['address'], $user->address);
        $this->assertEquals($data['postcode'], $user->postcode);
        $this->assertEquals($data['additional'], $user->additional);
        $this->assertEquals($data['email'], $user->email);
        $this->assertEquals($data['language'], $user->language);
        $this->assertEquals($data['external_id'], $user->external_id);
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
        $this->assertEquals(str_replace(['(', ')', ' ', '.', '-'], '', $phone), $user->phone);

        // 2
        $phone = '89991112233';
        $this->service->update($user, ['phone' => $phone]);
        $this->assertEquals($phone, $user->phone);

        // 3
        $this->service->update($user, ['phone' => '8 (999) 111-22-33']);
        $this->assertEquals('89991112233', $user->phone);

        // убираем флаг
        unset($_ENV['SIMPLE_PHONE_CHECK']);

        // 4
        $phone = '+79991112233';
        $this->service->update($user, ['phone' => $phone]);
        $this->assertEquals($phone, $user->phone);

        // 5
        $this->service->update($user, ['phone' => '+7 (999) 111-22-33']);
        $this->assertEquals('+79991112233', $user->phone);
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
        $this->assertEquals(UserStatus::DELETE, $user->status);
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
        $this->assertEquals(UserStatus::BLOCK, $user->status);
    }

    public function testBlockWithNotFound(): void
    {
        $this->expectException(UserNotFoundException::class);

        $this->service->block(null);
    }
}
