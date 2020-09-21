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
use App\Domain\Service\User\Exception\PhoneAlreadyExistsException;
use App\Domain\Service\User\Exception\UsernameAlreadyExistsException;
use App\Domain\Service\User\Exception\UserNotFoundException;
use App\Domain\Service\User\Exception\WrongPasswordException;
use Illuminate\Support\Collection;
use Ramsey\Uuid\Uuid;

class UserService extends AbstractService
{
    /**
     * @var UserRepository
     */
    protected $service;

    protected function init(): void
    {
        $this->service = $this->entityManager->getRepository(User::class);
    }

    /**
     * @param array $data
     *
     * @throws EmailAlreadyExistsException
     * @throws UsernameAlreadyExistsException
     * @throws PhoneAlreadyExistsException
     * @throws MissingUniqueValueException
     * @throws WrongEmailValueException
     * @throws WrongPhoneValueException
     *
     * @return User
     */
    public function create(array $data = []): User
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

        if ($data['username'] && $this->service->findOneByUsername($data['username']) !== null) {
            throw new UsernameAlreadyExistsException();
        }
        if ($data['email'] && $this->service->findOneByEmail($data['email']) !== null) {
            throw new EmailAlreadyExistsException();
        }
        if ($data['phone'] && $this->service->findOneByPhone($data['phone']) !== null) {
            throw new PhoneAlreadyExistsException();
        }
        if (!$data['username'] && !$data['email'] && !$data['phone']) {
            throw new MissingUniqueValueException();
        }
        if (!$data['password']) {
            throw new WrongPasswordException();
        }

        $user = (new User)
            ->setUsername($data['username'])
            ->setEmail($data['email'])
            ->setPhone($data['phone'])
            ->setPassword($data['password'])
            ->setFirstname($data['firstname'])
            ->setLastname($data['lastname'])
            ->setAllowMail($data['allow_mail'])
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
     * @param array $data
     *
     * @throws UserNotFoundException
     * @throws WrongPasswordException
     *
     * @return Collection|User
     */
    public function read(array $data = [])
    {
        $default = [
            'identifier' => null, // field for: username, email, email
            'uuid' => null,
            'username' => null,
            'email' => null,
            'phone' => null,
            'allow_mail' => null,
            'status' => null,
            'password' => null, // optional: for check
            'agent' => null, // optional: for update
            'ip' => null, // optional: for update
        ];
        $data = array_merge($default, static::$default_read, $data);

        $criteria = [];

        if ($data['uuid'] !== null) {
            $criteria['uuid'] = $data['uuid'];
        }
        if ($data['username'] !== null) {
            $criteria['username'] = $data['username'];
        }
        if ($data['email'] !== null) {
            $criteria['email'] = $data['email'];
        }
        if ($data['phone'] !== null) {
            $criteria['phone'] = $data['phone'];
        }
        if ($data['allow_mail'] !== null) {
            $criteria['allow_mail'] = (bool) $data['allow_mail'];
        }
        if ($data['status'] !== null && in_array($data['status'], \App\Domain\Types\UserStatusType::LIST, true)) {
            $criteria['status'] = $data['status'];
        }

        if (
            $data['identifier'] !== null ||
            !is_array($data['uuid']) && $data['uuid'] !== null ||
            !is_array($data['username']) && $data['username'] !== null ||
            !is_array($data['email']) && $data['email'] !== null ||
            !is_array($data['phone']) && $data['phone'] !== null
        ) {
            switch (true) {
                case $data['identifier']:
                    $user = $this->service->findOneByIdentifier($data['identifier']);

                    break;

                case $data['uuid']:
                    $user = $this->service->findOneByUuid($data['uuid']);

                    break;

                case $data['username']:
                    $user = $this->service->findOneByUsername($data['username']);

                    break;

                case $data['email']:
                    $user = $this->service->findOneByEmail($data['email']);

                    break;

                case $data['phone']:
                    $user = $this->service->findOneByPhone($data['phone']);

                    break;
            }

            if (
                empty($user) || (!empty($criteria['status']) && $data['status'] !== $user->getStatus())
            ) {
                throw new UserNotFoundException();
            }

            // optional: check password
            if ($data['password'] && !crypta_hash_check($data['password'], $user->getPassword())) {
                throw new WrongPasswordException();
            }

            // optional: update fields
            if ($data['agent'] && $data['ip']) {
                $user
                    ->getSession()
                    ->setDate('now')
                    ->setAgent($data['agent'])
                    ->setIp($data['ip']);

                $this->entityManager->flush();
            }

            return $user;
        }

        return collect($this->service->findBy($criteria, $data['order'], $data['limit'], $data['offset']));
    }

    /**
     * @param string|User|Uuid $entity
     * @param array            $data
     *
     * @throws UsernameAlreadyExistsException
     * @throws EmailAlreadyExistsException
     * @throws PhoneAlreadyExistsException
     * @throws WrongEmailValueException
     * @throws WrongPhoneValueException
     * @throws UserNotFoundException
     *
     * @return User
     */
    public function update($entity, array $data = []): User
    {
        switch (true) {
            case is_string($entity) && Uuid::isValid($entity):
            case is_object($entity) && is_a($entity, Uuid::class):
                $entity = $this->service->findOneByUuid((string) $entity);

                break;
        }

        if (is_object($entity) && is_a($entity, User::class)) {
            $default = [
                'username' => null,
                'email' => null,
                'phone' => null,
                'password' => null,
                'firstname' => null,
                'lastname' => null,
                'allow_mail' => null,
                'status' => null,
                'level' => null,
            ];
            $data = array_merge($default, $data);

            if ($data !== $default) {
                if ($data['username'] !== null) {
                    $found = $this->service->findOneByUsername($data['email']);

                    if ($found === null || $found === $entity) {
                        $entity->setUsername($data['username']);
                    } else {
                        throw new UsernameAlreadyExistsException();
                    }
                }
                if ($data['email'] !== null) {
                    $found = $this->service->findOneByEmail($data['email']);

                    if ($found === null || $found === $entity) {
                        $entity->setEmail($data['email']);
                    } else {
                        throw new EmailAlreadyExistsException();
                    }
                }
                if ($data['phone'] !== null) {
                    $found = $this->service->findOneByPhone($data['phone']);

                    if ($found === null || $found === $entity) {
                        $entity->setPhone($data['phone']);
                    } else {
                        throw new PhoneAlreadyExistsException();
                    }
                }
                if ($data['password'] !== null) {
                    $entity->setPassword($data['password']);
                }
                if ($data['firstname'] !== null) {
                    $entity->setFirstname($data['firstname']);
                }
                if ($data['lastname'] !== null) {
                    $entity->setLastname($data['lastname']);
                }
                if ($data['allow_mail'] !== null) {
                    $entity->setAllowMail($data['allow_mail']);
                }
                if ($data['status'] !== null) {
                    $entity->setStatus($data['status']);
                }
                if ($data['level'] !== null) {
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
     * @return User
     */
    public function block($entity): ?User
    {
        if (
            (is_string($entity) && Uuid::isValid($entity)) ||
            (is_object($entity) && is_a($entity, Uuid::class))
        ) {
            $entity = $this->service->findOneByUuid((string) $entity);
        }

        if (is_object($entity) && is_a($entity, User::class)) {
            $entity->setStatus(\App\Domain\Types\UserStatusType::STATUS_BLOCK)->setChange('now');

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
     * @return User
     */
    public function delete($entity): User
    {
        switch (true) {
            case is_string($entity) && Uuid::isValid($entity):
            case is_object($entity) && is_a($entity, Uuid::class):
                $entity = $this->service->findOneByUuid((string) $entity);

                break;
        }

        if (is_object($entity) && is_a($entity, User::class)) {
            $entity->setStatus(\App\Domain\Types\UserStatusType::STATUS_DELETE)->setChange('now');

            $this->entityManager->flush();

            return $entity;
        }

        throw new UserNotFoundException();
    }
}
