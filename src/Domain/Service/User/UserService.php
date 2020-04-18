<?php declare(strict_types=1);

namespace App\Domain\Service\User;

use App\Domain\Entities\User;
use App\Domain\Entities\User\Session as UserSession;
use App\Domain\Repository\UserRepository;
use App\Domain\Service\AbstractService;
use App\Domain\Service\User\Exception\EmailAlreadyExistsException;
use App\Domain\Service\User\Exception\UsernameAlreadyExistsException;
use App\Domain\Service\User\Exception\UserNotFoundException;
use App\Domain\Service\User\Exception\WrongPasswordException;
use Doctrine\ORM\EntityManager;
use Psr\Log\LoggerInterface;

class UserService extends AbstractService
{
    /** @var UserRepository */
    private $users;

    public function __construct(EntityManager $entityManager, LoggerInterface $logger = null)
    {
        parent::__construct($entityManager, $logger);

        $this->users = $this->entityManager->getRepository(User::class);
    }

    /**
     * @param array $data
     *
     * @throws UserNotFoundException
     * @throws WrongPasswordException
     * @return User|null
     */
    public function getByLogin(array $data = []): ?User
    {
        $default = [
            'identifier' => '',
            'password' => '',
            'agent' => '',
            'ip' => '',
        ];
        $data = array_merge($default, $data);

        $user = $this->users->findOneByIdentifier($data['identifier']);

        if ($user === null) {
            throw new UserNotFoundException();
        }
        if (!crypta_hash_check($data['password'], $user->getPassword())) {
            throw new WrongPasswordException();
        }

        $user
            ->getSession()
            ->setDate('now')
            ->setAgent($data['agent'])
            ->setIp($data['ip']);

        $this->entityManager->flush();

        return $user;
    }

    /**
     * @param array $data
     *
     * @throws EmailAlreadyExistsException
     * @throws UsernameAlreadyExistsException
     *
     * @return User|null
     */
    public function createByRegister(array $data = []): ?User
    {
        $default = [
            'identifier' => '',
            'username' => '',
            'email' => '',
            'password' => '',
        ];
        $data = array_merge($default, $data);

        if ($data['username'] && $this->users->findOneByUsername($data['username']) !== null) {
            throw new UsernameAlreadyExistsException();
        }
        if ($data['email'] && $this->users->findOneByEmail($data['email']) !== null) {
            throw new EmailAlreadyExistsException();
        }

        $user = (new User)
            ->setUsername($data['username'])
            ->setEmail($data['email'])
            ->setPassword($data['password'])
            ->setRegister('now')
            ->setChange('now')
            ->setSession($session = (new UserSession)->setDate('now'));

        $this->entityManager->persist($user);
        $this->entityManager->persist($session);
        $this->entityManager->flush();

        return $user;
    }

    public function createByAdmin(array $data = []): ?User
    {
        $default = [
            'username' => '',
            'email' => '',
            'phone' => '',
            'password' => '',
            'firstname' => '',
            'lastname' => '',
            'allow_mail' => true,
            'status' => \App\Domain\Types\UserStatusType::STATUS_WORK,
            'level' => \App\Domain\Types\UserLevelType::LEVEL_USER,
        ];
        $data = array_merge($default, $data);

        if ($this->users->findOneByUsername($data['username']) !== null) {
            throw new UsernameAlreadyExistsException();
        }
        if ($this->users->findOneByEmail($data['email']) !== null) {
            throw new EmailAlreadyExistsException();
        }

        $user = (new User)
            ->setUsername($data['username'])
            ->setEmail($data['email'])
            ->setPhone($data['phone'])
            ->setPassword($data['password'])
            ->setFirstname($data['firstname'])
            ->setLastname($data['lastname'])
            ->setAllowMail((bool) $data['allow_mail'])
            ->setStatus($data['status'])
            ->setLevel($data['level'])
            ->setRegister('now')
            ->setChange('now')
            ->setSession($session = (new UserSession)->setDate('now'));

        $this->entityManager->persist($user);
        $this->entityManager->persist($session);
        $this->entityManager->flush();

        return $user;
    }
}
