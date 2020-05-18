<?php declare(strict_types=1);

namespace tests\Domain\Service\Publication;

use App\Domain\Entities\Publication\Category as PublicationCategory;
use App\Domain\Repository\Publication\CategoryRepository as PublicationCategoryRepository;
use App\Domain\Service\Publication\CategoryService as PublicationCategoryService;
use Doctrine\ORM\EntityManager;
use tests\TestCase;

class CategoryServiceTest extends TestCase
{
    /**
     * @var EntityManager
     */
    protected $em;

    /**
     * @var PublicationCategoryService
     */
    protected $service;

    public function setUp(): void
    {
        parent::setUp();
        $this->em = $this->getEntityManager();
        $this->service = new PublicationCategoryService($this->em);
    }

    public function testCreateSuccess(): void
    {
        $data = [
            'title' => $this->getFaker()->title,
            'address' => 'some-custom-address',
            'description' => $this->getFaker()->text(255),
            'pagination' => $this->getFaker()->numberBetween(10, 1000),
            'children' => $this->getFaker()->boolean,
            'public' => $this->getFaker()->boolean,
            'sort' => [
                'by' => $this->getFaker()->randomElement(\App\Domain\References\Publication::ORDER_BY),
                'direction' => $this->getFaker()->randomElement(\App\Domain\References\Publication::ORDER_DIRECTION),
            ],
            'meta' => [
                'title' => $this->getFaker()->title,
                'description' => $this->getFaker()->text,
                'keywords' => $this->getFaker()->text,
            ],
            'template' => [
                'list' => $this->getFaker()->title,
                'short' => $this->getFaker()->text,
                'full' => $this->getFaker()->text,
            ],
        ];

        $publicationCategory = $this->service->create($data);
        $this->assertInstanceOf(PublicationCategory::class, $publicationCategory);
        $this->assertSame($data['title'], $publicationCategory->getTitle());
        $this->assertSame($data['address'], $publicationCategory->getAddress());
        $this->assertSame($data['description'], $publicationCategory->getDescription());
        $this->assertSame($data['pagination'], $publicationCategory->getPagination());
        $this->assertSame($data['children'], $publicationCategory->getChildren());
        $this->assertSame($data['public'], $publicationCategory->getPublic());
        $this->assertSame($data['sort'], $publicationCategory->getSort());
        $this->assertSame($data['meta'], $publicationCategory->getMeta());
        $this->assertSame($data['template'], $publicationCategory->getTemplate());

        /** @var PublicationCategoryRepository $publicationCategoryRepo */
        $publicationCategoryRepo = $this->em->getRepository(PublicationCategory::class);
        $pc = $publicationCategoryRepo->findOneByTitle($data['title']);
        $this->assertInstanceOf(PublicationCategory::class, $pc);
        $this->assertSame($data['title'], $pc->getTitle());
        $this->assertSame($data['address'], $pc->getAddress());
        $this->assertSame($data['description'], $pc->getDescription());
        $this->assertSame($data['pagination'], $pc->getPagination());
        $this->assertSame($data['children'], $pc->getChildren());
        $this->assertSame($data['public'], $pc->getPublic());
        $this->assertSame($data['sort'], $pc->getSort());
        $this->assertSame($data['meta'], $pc->getMeta());
        $this->assertSame($data['template'], $pc->getTemplate());
    }

//    public function testCreateWithMissingUniqueValue(): void
//    {
//        $this->expectException(MissingUniqueValueException::class);
//
//        $this->service->create();
//    }
//
//    public function testCreateWithTitleExistent(): void
//    {
//        $this->expectException(UsernameAlreadyExistsException::class);
//
//        $data = [
//            'username' => $this->getFaker()->userName,
//            'password' => $this->getFaker()->password,
//        ];
//
//        $user = (new User)
//            ->setUsername($data['username'])
//            ->setPassword($data['password'])
//            ->setRegister('now')->setChange('now')
//            ->setSession($session = (new UserSession)->setDate('now'));
//
//        $this->em->persist($user);
//        $this->em->persist($session);
//        $this->em->flush();
//
//        $this->service->create($data);
//    }
//
//    public function testCreateWithAddressExistent(): void
//    {
//        $this->expectException(EmailAlreadyExistsException::class);
//
//        $data = [
//            'email' => $this->getFaker()->email,
//            'password' => $this->getFaker()->password,
//        ];
//
//        $user = (new User)
//            ->setEmail($data['email'])
//            ->setPassword($data['password'])
//            ->setRegister('now')->setChange('now')
//            ->setSession($session = (new UserSession)->setDate('now'));
//
//        $this->em->persist($user);
//        $this->em->persist($session);
//        $this->em->flush();
//
//        $this->service->create($data);
//    }
//
//    public function testCreateWithPhoneExistent(): void
//    {
//        $this->expectException(PhoneAlreadyExistsException::class);
//
//        $data = [
//            'phone' => $this->getFaker()->e164PhoneNumber,
//            'password' => $this->getFaker()->password,
//        ];
//
//        $user = (new User)
//            ->setPhone($data['phone'])
//            ->setPassword($data['password'])
//            ->setRegister('now')->setChange('now')
//            ->setSession($session = (new UserSession)->setDate('now'));
//
//        $this->em->persist($user);
//        $this->em->persist($session);
//        $this->em->flush();
//
//        $this->service->create($data);
//    }
//
//    public function testReadSuccess1(): void
//    {
//        $data = [
//            'username' => $this->getFaker()->userName,
//            'password' => $this->getFaker()->password,
//        ];
//
//        $this->service->create($data);
//
//        $user = $this->service->read(['identifier' => $data['username']]);
//        $this->assertInstanceOf(User::class, $user);
//        $this->assertSame($data['username'], $user->getUsername());
//
//        $user = $this->service->read(array_merge($data, ['agent' => $this->getFaker()->userAgent, 'ip' => $this->getFaker()->ipv4]));
//        $this->assertInstanceOf(User::class, $user);
//        $this->assertSame($data['username'], $user->getUsername());
//    }
//
//    public function testReadSuccess2(): void
//    {
//        $data = [
//            'email' => $this->getFaker()->email,
//            'password' => $this->getFaker()->password,
//        ];
//
//        $this->service->create($data);
//
//        $user = $this->service->read(['identifier' => $data['email']]);
//        $this->assertInstanceOf(User::class, $user);
//        $this->assertSame($data['email'], $user->getEmail());
//
//        $user = $this->service->read(array_merge($data, ['agent' => $this->getFaker()->userAgent, 'ip' => $this->getFaker()->ipv4]));
//        $this->assertInstanceOf(User::class, $user);
//        $this->assertSame($data['email'], $user->getEmail());
//    }
//
//    public function testReadSuccess3(): void
//    {
//        $data = [
//            'phone' => $this->getFaker()->e164PhoneNumber,
//            'password' => $this->getFaker()->password,
//        ];
//
//        $this->service->create($data);
//
//        $user = $this->service->read(['identifier' => $data['phone']]);
//        $this->assertInstanceOf(User::class, $user);
//        $this->assertSame($data['phone'], $user->getPhone());
//
//        $user = $this->service->read(array_merge($data, ['agent' => $this->getFaker()->userAgent, 'ip' => $this->getFaker()->ipv4]));
//        $this->assertInstanceOf(User::class, $user);
//        $this->assertSame($data['phone'], $user->getPhone());
//    }
//
//    public function testReadWithUserNotFound1(): void
//    {
//        $this->expectException(UserNotFoundException::class);
//
//        $this->service->read([
//            'identifier' => $this->getFaker()->userName,
//        ]);
//    }
//
//    public function testReadWithUserNotFound2(): void
//    {
//        $this->expectException(UserNotFoundException::class);
//
//        $this->service->read([
//            'identifier' => $this->getFaker()->email,
//        ]);
//    }
//
//    public function testReadWithUserNotFound3(): void
//    {
//        $this->expectException(UserNotFoundException::class);
//
//        $this->service->read([
//            'identifier' => $this->getFaker()->e164PhoneNumber,
//        ]);
//    }
//
//    public function testReadWithWrongPassword(): void
//    {
//        $this->expectException(WrongPasswordException::class);
//
//        $data = [
//            'username' => $this->getFaker()->userName,
//            'password' => $this->getFaker()->password,
//        ];
//
//        $this->service->create($data);
//
//        $this->service->read([
//            'username' => $data['username'],
//            'password' => $data['password'] . '-wrong',
//        ]);
//    }
//
//    public function testUpdate(): void
//    {
//        $user = $this->service->create([
//            'username' => $this->getFaker()->userName,
//            'email' => $this->getFaker()->email,
//            'password' => $this->getFaker()->password,
//        ]);
//
//        $data = [
//            'username' => $this->getFaker()->userName,
//            'password' => $this->getFaker()->password,
//            'phone' => $this->getFaker()->e164PhoneNumber,
//            'firstname' => $this->getFaker()->firstName,
//            'lastname' => $this->getFaker()->lastName,
//            'email' => $this->getFaker()->email,
//        ];
//
//        $user = $this->service->update($user, $data);
//        $this->assertSame($data['username'], $user->getUsername());
//        $this->assertSame($data['firstname'], $user->getFirstname());
//        $this->assertSame($data['lastname'], $user->getLastname());
//        $this->assertSame($data['email'], $user->getEmail());
//    }
//
//    public function testUpdateWithUserNotFound(): void
//    {
//        $this->expectException(UserNotFoundException::class);
//
//        $this->service->update(null);
//    }
//
//    public function testUpdatePhone(): void
//    {
//        $user = $this->service->create([
//            'username' => $this->getFaker()->userName,
//            'password' => $this->getFaker()->password,
//        ]);
//
//        // флаг простой проверки телефона
//        $_ENV['SIMPLE_PHONE_CHECK'] = 1;
//
//        // 1
//        $phone = $this->getFaker()->phoneNumber;
//        $this->service->update($user, ['phone' => $phone]);
//        $this->assertSame(str_replace(['(', ')', ' ', '.', '-'], '', $phone), $user->getPhone());
//
//        // 2
//        $phone = '89991112233';
//        $this->service->update($user, ['phone' => $phone]);
//        $this->assertSame($phone, $user->getPhone());
//
//        // 3
//        $this->service->update($user, ['phone' => '8 (999) 111-22-33']);
//        $this->assertSame('89991112233', $user->getPhone());
//
//        // убираем флаг
//        unset($_ENV['SIMPLE_PHONE_CHECK']);
//
//        // 4
//        $phone = '+79991112233';
//        $this->service->update($user, ['phone' => $phone]);
//        $this->assertSame($phone, $user->getPhone());
//
//        // 5
//        $this->service->update($user, ['phone' => '+7 (999) 111-22-33']);
//        $this->assertSame('+79991112233', $user->getPhone());
//    }
//
//    public function testDelete(): void
//    {
//        $user = $this->service->create([
//            'phone' => $this->getFaker()->e164PhoneNumber,
//            'username' => $this->getFaker()->userName,
//            'email' => $this->getFaker()->email,
//            'password' => $this->getFaker()->password,
//        ]);
//
//        $user = $this->service->delete($user);
//        $this->assertSame(UserStatusType::STATUS_DELETE, $user->getStatus());
//    }
//
//    public function testDeleteWithNotFound(): void
//    {
//        $this->expectException(UserNotFoundException::class);
//
//        $this->service->delete(null);
//    }
//
//    public function testBlock(): void
//    {
//        $user = $this->service->create([
//            'phone' => $this->getFaker()->e164PhoneNumber,
//            'username' => $this->getFaker()->userName,
//            'email' => $this->getFaker()->email,
//            'password' => $this->getFaker()->password,
//        ]);
//
//        $user = $this->service->block($user);
//        $this->assertSame(UserStatusType::STATUS_BLOCK, $user->getStatus());
//    }
//
//    public function testBlockWithNotFound(): void
//    {
//        $this->expectException(UserNotFoundException::class);
//
//        $this->service->block(null);
//    }
}
