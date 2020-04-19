<?php declare(strict_types=1);

namespace App\Domain\Service\User;

use App\Domain\AbstractService;
use App\Domain\Entities\User;
use App\Domain\Entities\User\Session as UserSession;
use App\Domain\Exceptions\WrongEmailValueException;
use App\Domain\Exceptions\WrongPhoneValueException;
use App\Domain\Repository\UserRepository;
use App\Domain\Service\User\Exception\EmailAlreadyExistsException;
use App\Domain\Service\User\Exception\MissingUniqueValueException;
use App\Domain\Service\User\Exception\UsernameAlreadyExistsException;
use App\Domain\Service\User\Exception\UserNotFoundException;
use App\Domain\Service\User\Exception\WrongPasswordException;
use Doctrine\ORM\EntityManager;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;

class UserService extends AbstractService
{
    /**
     * @var UserRepository
     */
    protected $service;

    public function __construct(EntityManager $entityManager, LoggerInterface $logger = null)
    {
        parent::__construct($entityManager, $logger);

        $this->service = $this->entityManager->getRepository(User::class);
    }

    /**
     * @param array $data
     *
     * @throws UserNotFoundException
     * @throws WrongPasswordException
     *
     * @return null|User
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

        $user = $this->service->findOneByIdentifier($data['identifier']);

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
     * @throws MissingUniqueValueException
     * @throws EmailAlreadyExistsException
     * @throws WrongEmailValueException
     * @throws UsernameAlreadyExistsException
     *
     * @return null|User
     */
    public function createByRegister(array $data = []): ?User
    {
        $default = [
            'username' => '',
            'email' => '',
            'password' => '',
        ];
        $data = array_merge($default, $data);

        if (!$data['username'] && !$data['email']) {
            throw new MissingUniqueValueException();
        }
        if ($data['username'] && $this->service->findOneByUsername($data['username']) !== null) {
            throw new UsernameAlreadyExistsException();
        }
        if ($data['email'] && $this->service->findOneByEmail($data['email']) !== null) {
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

    /**
     * @param array $data
     *
     * @throws EmailAlreadyExistsException
     * @throws UsernameAlreadyExistsException
     * @throws WrongEmailValueException
     * @throws WrongPhoneValueException
     *
     * @return null|User
     */
    public function createByCup(array $data = []): ?User
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

        if ($this->service->findOneByUsername($data['username']) !== null) {
            throw new UsernameAlreadyExistsException();
        }
        if ($this->service->findOneByEmail($data['email']) !== null) {
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

    /**
     * @param string|User|Uuid $entity
     * @param array            $data
     *
     * @throws UsernameAlreadyExistsException
     * @throws EmailAlreadyExistsException
     * @throws WrongEmailValueException
     * @throws WrongPhoneValueException
     * @throws UserNotFoundException
     *
     * @return null|User
     */
    public function change($entity, array $data = [])
    {
        switch (true) {
            case is_string($entity) && Uuid::isValid($entity):
            case is_object($entity) && is_a($entity, Uuid::class):
                $entity = $this->service->findByUuid((string) $entity);

                break;
        }

        if (is_object($entity) && is_a($entity, User::class)) {
            $default = [
                'username' => '',
                'email' => '',
                'phone' => '',
                'password' => '',
                'firstname' => '',
                'lastname' => '',
                'allow_mail' => '',
                'status' => '',
                'level' => '',
            ];
            $data = array_merge($default, $data);

            if ($data !== $default) {
                if ($data['username']) {
                    $found = $this->service->findOneByUsername($data['email']);

                    if ($found === null || $found === $entity) {
                        $entity->setUsername($data['username']);
                    } else {
                        throw new UsernameAlreadyExistsException();
                    }
                }
                if ($data['email']) {
                    $found = $this->service->findOneByEmail($data['email']);

                    if ($found === null || $found === $entity) {
                        $entity->setEmail($data['email']);
                    } else {
                        throw new EmailAlreadyExistsException();
                    }
                }
                if ($data['phone']) {
                    $entity->setPhone($data['phone']);
                }
                if ($data['password']) {
                    $entity->setPassword($data['password']);
                }
                if ($data['firstname']) {
                    $entity->setFirstname($data['firstname']);
                }
                if ($data['lastname']) {
                    $entity->setLastname($data['lastname']);
                }
                if ($data['allow_mail']) {
                    $entity->setAllowMail($data['allow_mail']);
                }
                if ($data['status']) {
                    $entity->setStatus($data['status']);
                }
                if ($data['level']) {
                    $entity->setLevel($data['level']);
                }

                $entity->setChange('now');

                $this->entityManager->flush();
            }

            return $entity;
        }

        throw new UserNotFoundException();
    }

    /**
     * @param string|User|Uuid $entity
     *
     * @throws UserNotFoundException
     *
     * @return null|User
     */
    public function delete($entity)
    {
        if (
            (is_string($entity) && Uuid::isValid($entity)) ||
            (is_object($entity) && is_a($entity, Uuid::class))
        ) {
            $entity = $this->service->findByUuid((string) $entity);
        }

        if (is_object($entity) && is_a($entity, User::class)) {
            $entity->setStatus(\App\Domain\Types\UserStatusType::STATUS_DELETE)->setChange('now');

            $this->entityManager->flush();

            return $entity;
        }

        throw new UserNotFoundException();
    }

    /**
     * @param string|User|Uuid $entity
     *
     * @throws UserNotFoundException
     *
     * @return null|User
     */
    public function block($entity)
    {
        if (
            (is_string($entity) && Uuid::isValid($entity)) ||
            (is_object($entity) && is_a($entity, Uuid::class))
        ) {
            $entity = $this->service->findByUuid((string) $entity);
        }

        if (is_object($entity) && is_a($entity, User::class)) {
            $entity->setStatus(\App\Domain\Types\UserStatusType::STATUS_BLOCK)->setChange('now');

            $this->entityManager->flush();

            return $entity;
        }

        throw new UserNotFoundException();
    }
}
