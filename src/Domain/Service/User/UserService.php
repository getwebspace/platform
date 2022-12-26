<?php declare(strict_types=1);

namespace App\Domain\Service\User;

use App\Domain\AbstractService;
use App\Domain\Entities\User;
use App\Domain\Repository\UserRepository;
use App\Domain\Service\User\Exception\EmailAlreadyExistsException;
use App\Domain\Service\User\Exception\EmailBannedException;
use App\Domain\Service\User\Exception\MissingUniqueValueException;
use App\Domain\Service\User\Exception\PhoneAlreadyExistsException;
use App\Domain\Service\User\Exception\UsernameAlreadyExistsException;
use App\Domain\Service\User\Exception\UserNotFoundException;
use App\Domain\Service\User\Exception\WrongEmailValueException;
use App\Domain\Service\User\Exception\WrongPasswordException;
use App\Domain\Service\User\Exception\WrongPhoneValueException;
use Illuminate\Support\Collection;
use Ramsey\Uuid\UuidInterface as Uuid;

class UserService extends AbstractService
{
    /**
     * @var UserRepository
     */
    protected mixed $service;

    protected function init(): void
    {
        $this->service = $this->entityManager->getRepository(User::class);
    }

    /**
     * @throws EmailAlreadyExistsException
     * @throws EmailBannedException
     * @throws UsernameAlreadyExistsException
     * @throws PhoneAlreadyExistsException
     * @throws MissingUniqueValueException
     * @throws WrongEmailValueException
     * @throws WrongPhoneValueException
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
            'patronymic' => '',
            'gender' => '',
            'birthdate' => '',
            'country' => '',
            'city' => '',
            'address' => '',
            'postcode' => '',
            'additional' => '',
            'allow_mail' => true,
            'status' => \App\Domain\Types\UserStatusType::STATUS_WORK,
            'company' => [],
            'legal' => [],
            'messenger' => [],
            'website' => '',
            'source' => '',
            'group' => null,
            'auth_code' => '',
            'language' => '',
            'external_id' => '',
            'token' => [],
        ];
        $data = array_merge($default, $data);

        if ($data['username'] && $this->service->findOneByUsername($data['username']) !== null) {
            throw new UsernameAlreadyExistsException();
        }
        if ($data['email']) {
            if ($this->service->findOneByEmail($data['email']) !== null) {
                throw new EmailAlreadyExistsException();
            }
            if ($this->check_email($data['email'])) {
                throw new EmailBannedException();
            }
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

        $user = (new User())
            ->setUsername($data['username'])
            ->setEmail($data['email'])
            ->setPhone($data['phone'])
            ->setPassword($data['password'])
            ->setFirstname($data['firstname'])
            ->setLastname($data['lastname'])
            ->setPatronymic($data['patronymic'])
            ->setGender($data['gender'])
            ->setBirthdate($data['birthdate'])
            ->setCountry($data['country'])
            ->setCity($data['city'])
            ->setAddress($data['address'])
            ->setPostcode($data['postcode'])
            ->setAdditional($data['additional'])
            ->setAllowMail($data['allow_mail'])
            ->setStatus($data['status'])
            ->setCompany($data['company'])
            ->setLegal($data['legal'])
            ->setMessanger($data['messenger'])
            ->setWebsite($data['website'])
            ->setSource($data['source'])
            ->setGroup($data['group'])
            ->setAuthCode($data['auth_code'])
            ->setLanguage($data['language'])
            ->setExternalId($data['external_id'])
            ->setToken($data['token'])
            ->setRegister('now', $this->parameter('common_timezone', 'UTC'))
            ->setChange('now', $this->parameter('common_timezone', 'UTC'));

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    /**
     * @throws UserNotFoundException
     * @throws WrongPasswordException
     *
     * @return Collection|User
     */
    public function read(array $data = [])
    {
        $default = [
            'identifier' => null, // field for: username, email, phone
            'uuid' => null,
            'username' => null,
            'email' => null,
            'phone' => null,
            'country' => null,
            'city' => null,
            'postcode' => null,
            'additional' => null,
            'allow_mail' => null,
            'status' => null,
            'external_id' => null,
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
        if ($data['country'] !== null) {
            $criteria['country'] = $data['country'];
        }
        if ($data['city'] !== null) {
            $criteria['city'] = $data['city'];
        }
        if ($data['postcode'] !== null) {
            $criteria['postcode'] = $data['postcode'];
        }
        if ($data['additional'] !== null) {
            $criteria['additional'] = $data['additional'];
        }
        if ($data['allow_mail'] !== null) {
            $criteria['allow_mail'] = (bool) $data['allow_mail'];
        }
        if ($data['status'] !== null && in_array($data['status'], \App\Domain\Types\UserStatusType::LIST, true)) {
            $criteria['status'] = $data['status'];
        }
        if ($data['external_id'] !== null) {
            $criteria['external_id'] = $data['external_id'];
        }

        try {
            if (
                $data['identifier'] !== null
                || !is_array($data['uuid']) && $data['uuid'] !== null
                || !is_array($data['username']) && $data['username'] !== null
                || !is_array($data['email']) && $data['email'] !== null
                || !is_array($data['phone']) && $data['phone'] !== null
                || !is_array($data['external_id']) && $data['external_id'] !== null
            ) {
                switch (true) {
                    case $data['identifier']:
                        $user = $this->service->findOneByIdentifier($data['identifier']);

                        break;

                    case $data['uuid']:
                        $user = $this->service->findOneByUuid($data['uuid']);

                        break;

                    case $data['username']:
                        $user = $this->service->findOneByUsername((string) $data['username']);

                        break;

                    case $data['email']:
                        $user = $this->service->findOneByEmail((string) $data['email']);

                        break;

                    case $data['phone']:
                        $user = $this->service->findOneByPhone((string) $data['phone']);

                        break;

                    case $data['external_id']:
                        $user = $this->service->findOneByExternalId($data['external_id']);

                        break;
                }

                if (
                    empty($user) || (!empty($data['status']) && $data['status'] !== $user->getStatus())
                ) {
                    throw new UserNotFoundException();
                }

                // optional: check password
                if ($data['password'] !== null) {
                    if (crypta_hash_check($data['password'], $user->getPassword())) {
                        // todo remove this in future
                        $user = $this->update($user, ['password' => $data['password']]);
                    }
                    if (!password_verify($data['password'], $user->getPassword())) {
                        throw new WrongPasswordException();
                    }
                }

                return $user;
            }

            return collect($this->service->findBy($criteria, $data['order'], $data['limit'], $data['offset']));
        } catch (\Doctrine\DBAL\Exception\TableNotFoundException $e) {
            return null;
        }
    }

    /**
     * @param string|User|Uuid $entity
     *
     * @throws UsernameAlreadyExistsException
     * @throws EmailAlreadyExistsException
     * @throws EmailBannedException
     * @throws PhoneAlreadyExistsException
     * @throws WrongEmailValueException
     * @throws WrongPhoneValueException
     * @throws UserNotFoundException
     */
    public function update($entity, array $data = []): User
    {
        switch (true) {
            case is_string($entity) && \Ramsey\Uuid\Uuid::isValid($entity):
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
                'patronymic' => null,
                'gender' => null,
                'birthdate' => null,
                'country' => null,
                'city' => null,
                'address' => null,
                'postcode' => null,
                'additional' => null,
                'allow_mail' => null,
                'status' => null,
                'company' => null,
                'legal' => null,
                'messenger' => null,
                'website' => null,
                'source' => null,
                'group' => null,
                'auth_code' => null,
                'language' => null,
                'external_id' => null,
                'token' => null,
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
                    if ($this->check_email($data['email'])) {
                        throw new EmailBannedException();
                    }

                    $found = $this->service->findOneByEmail($data['email']);

                    if ($found === null || $found === $entity) {
                        $entity->setEmail($data['email']);
                    } else {
                        throw new EmailAlreadyExistsException();
                    }
                }
                if ($data['phone'] !== null) {
                    if (blank($data['phone'])) {
                        $entity->setPhone();
                    } else {
                        $found = $this->service->findOneByPhone($data['phone']);

                        if ($found === null || $found === $entity) {
                            $entity->setPhone($data['phone']);
                        } else {
                            throw new PhoneAlreadyExistsException();
                        }
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
                if ($data['patronymic'] !== null) {
                    $entity->setPatronymic($data['patronymic']);
                }
                if ($data['gender'] !== null) {
                    $entity->setGender($data['gender']);
                }
                if ($data['birthdate'] !== null) {
                    $entity->setBirthdate($data['birthdate']);
                }
                if ($data['country'] !== null) {
                    $entity->setCountry($data['country']);
                }
                if ($data['city'] !== null) {
                    $entity->setCity($data['city']);
                }
                if ($data['address'] !== null) {
                    $entity->setAddress($data['address']);
                }
                if ($data['postcode'] !== null) {
                    $entity->setPostcode($data['postcode']);
                }
                if ($data['additional'] !== null) {
                    $entity->setAdditional($data['additional']);
                }
                if ($data['allow_mail'] !== null) {
                    $entity->setAllowMail($data['allow_mail']);
                }
                if ($data['status'] !== null) {
                    $entity->setStatus($data['status']);
                }
                if ($data['company'] !== null) {
                    $entity->setCompany($data['company']);
                }
                if ($data['legal'] !== null) {
                    $entity->setLegal($data['legal']);
                }
                if ($data['messenger'] !== null) {
                    $entity->setMessanger($data['messenger']);
                }
                if ($data['website'] !== null) {
                    $entity->setWebsite($data['website']);
                }
                if ($data['source'] !== null) {
                    $entity->setSource($data['source']);
                }
                if ($data['group'] !== null) {
                    $entity->setGroup($data['group']);
                }
                if ($data['auth_code'] !== null) {
                    $entity->setAuthCode($data['auth_code']);
                }
                if ($data['language'] !== null) {
                    $entity->setLanguage($data['language']);
                }
                if ($data['external_id'] !== null) {
                    $entity->setExternalId($data['external_id']);
                }
                if ($data['token'] !== null) {
                    foreach ($data['token'] as $token => $value) {
                        $entity->changeToken($token, $value);
                    }
                }

                $entity->setChange('now', $this->parameter('common_timezone', 'UTC'));

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
        switch (true) {
            case is_string($entity) && \Ramsey\Uuid\Uuid::isValid($entity):
            case is_object($entity) && is_a($entity, Uuid::class):
                $entity = $this->service->findOneByUuid((string) $entity);

                break;
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
     */
    public function delete($entity): User
    {
        switch (true) {
            case is_string($entity) && \Ramsey\Uuid\Uuid::isValid($entity):
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

    /**
     * @throws EmailBannedException
     */
    protected function check_email(string $email)
    {
        $list = array_map('trim', explode(PHP_EOL, $this->parameter('user_email_list', '')));

        switch ($this->parameter('user_email_list_mode', 'blacklist')) {
            case 'blacklist':
                if (str_end_with($email, $list)) {
                    return true;
                }

                break;

            case 'whitelist':
                if (!str_end_with($email, $list)) {
                    return true;
                }

                break;
        }

        return false;
    }
}
