<?php declare(strict_types=1);

namespace App\Domain\Service\User;

use App\Domain\AbstractService;
use App\Domain\Entities\User\Session as UserSession;
use App\Domain\Repository\User\SessionRepository as UserSessionRepository;
use App\Domain\Service\User\Exception\EmailAlreadyExistsException;
use App\Domain\Service\User\Exception\PhoneAlreadyExistsException;
use App\Domain\Service\User\Exception\UsernameAlreadyExistsException;
use App\Domain\Service\User\Exception\UserNotFoundException;
use App\Domain\Service\User\Exception\WrongEmailValueException;
use App\Domain\Service\User\Exception\WrongPhoneValueException;
use Ramsey\Uuid\UuidInterface as Uuid;

class SessionService extends AbstractService
{
    /**
     * @var UserSessionRepository
     */
    protected mixed $service;

    protected function init(): void
    {
        $this->service = $this->entityManager->getRepository(UserSession::class);
    }

    public function create(array $data = []): UserSession
    {
        $default = [
            'user' => '',
            'agent' => '',
            'ip' => '0.0.0.0',
            'date' => 'now',
        ];
        $data = array_merge($default, $data);

        if (!$data['user']) {
            throw new UserNotFoundException();
        }

        $user_session = (new UserSession())
            ->setAgent($data['agent'])
            ->setDate($data['date'], $this->parameter('common_timezone', 'UTC'))
            ->setIp($data['ip']);

        $this->entityManager->persist($user_session);

        // link user to session
        $data['user']->setSession($user_session);

        $this->entityManager->flush();

        return $user_session;
    }

    public function read(array $data = []): void
    {
    }

    /**
     * @param string|UserSession|Uuid $entity
     *
     * @throws UsernameAlreadyExistsException
     * @throws EmailAlreadyExistsException
     * @throws PhoneAlreadyExistsException
     * @throws WrongEmailValueException
     * @throws WrongPhoneValueException
     * @throws UserNotFoundException
     */
    public function update($entity, array $data = []): UserSession
    {
        switch (true) {
            case is_string($entity) && \Ramsey\Uuid\Uuid::isValid($entity):
            case is_object($entity) && is_a($entity, Uuid::class):
                $entity = $this->service->findOneByUuid((string) $entity);

                break;
        }

        if (is_object($entity) && is_a($entity, UserSession::class)) {
            $default = [
                'user' => null,
                'agent' => null,
                'date' => null,
                'ip' => null,
            ];
            $data = array_merge($default, $data);

            if ($data !== $default) {
                if ($data['user'] !== null) {
                    $entity->setUser($data['user']);
                }
                if ($data['agent'] !== null) {
                    $entity->setAgent($data['agent']);
                }
                if ($data['date'] !== null) {
                    $entity->setDate($data['date'], $this->parameter('common_timezone', 'UTC'));
                }
                if ($data['ip'] !== null) {
                    $entity->setIp($data['ip']);
                }

                $this->entityManager->flush($entity);
            }

            return $entity;
        }

        throw new UserNotFoundException();
    }

    public function delete($entity): void
    {
    }
}
