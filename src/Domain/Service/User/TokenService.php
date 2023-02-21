<?php declare(strict_types=1);

namespace App\Domain\Service\User;

use App\Domain\AbstractService;
use App\Domain\Entities\User\Token as UserToken;
use App\Domain\Repository\User\TokenRepository as UserTokenRepository;
use App\Domain\Service\User\Exception\TokenNotFoundException;
use App\Domain\Service\User\Exception\UserGroupNotFoundException;
use App\Domain\Service\User\Exception\UserNotFoundException;
use Illuminate\Support\Collection;
use Ramsey\Uuid\UuidInterface as Uuid;

class TokenService extends AbstractService
{
    /**
     * @var UserTokenRepository
     */
    protected mixed $service;

    protected function init(): void
    {
        $this->service = $this->entityManager->getRepository(UserToken::class);
    }

    public function create(array $data = []): UserToken
    {
        $default = [
            'user' => '',
            'unique' => '',
            'comment' => '',
            'ip' => '',
            'agent' => '',
            'date' => 'now',
        ];
        $data = array_merge($default, $data);

        if (!$data['user']) {
            throw new \RuntimeException();
        }

        $userToken = (new UserToken())
            ->setUser($data['user'])
            ->setUnique($data['unique'])
            ->setComment($data['comment'])
            ->setIp($data['ip'])
            ->setAgent($data['agent'])
            ->setDate($data['date'], $this->parameter('common_timezone', 'UTC'));

        $this->entityManager->persist($userToken);
        $this->entityManager->flush();

        return $userToken;
    }

    /**
     * @throws UserNotFoundException
     *
     * @return Collection|UserToken
     */
    public function read(array $data = [])
    {
        $default = [
            'unique' => null,
            'agent' => null,
            'ip' => null,
        ];
        $data = array_merge($default, static::$default_read, $data);

        $criteria = [];

        if ($data['unique'] !== null) {
            $criteria['unique'] = $data['unique'];
        }
        if ($data['agent'] !== null) {
            $criteria['agent'] = $data['agent'];
        }
        if ($data['ip'] !== null) {
            $criteria['ip'] = $data['ip'];
        }

        try {
            switch (true) {
                case !is_array($data['unique']) && $data['unique'] !== null:
                    $userToken = $this->service->findOneBy($criteria);

                    if (empty($userToken)) {
                        throw new TokenNotFoundException();
                    }

                    return $userToken;

                default:
                    return collect($this->service->findBy($criteria, $data['order'], $data['limit'], $data['offset']));
            }
        } catch (\Doctrine\DBAL\Exception\TableNotFoundException $e) {
            return null;
        }
    }

    public function update($entity, array $data = []): UserToken
    {
        switch (true) {
            case is_string($entity) && \Ramsey\Uuid\Uuid::isValid($entity):
            case is_object($entity) && is_a($entity, Uuid::class):
                $entity = $this->service->findOneByUuid((string) $entity);

                break;
        }

        if (is_object($entity) && is_a($entity, UserToken::class)) {
            $default = [
                'user' => null,
                'unique' => null,
                'comment' => null,
                'ip' => null,
                'agent' => null,
                'date' => null,
            ];
            $data = array_merge($default, $data);

            if ($data !== $default) {
                if ($data['user'] !== null) {
                    $entity->setUser($data['user']);
                }
                if ($data['unique'] !== null) {
                    $entity->setUnique($data['unique']);
                }
                if ($data['comment'] !== null) {
                    $entity->setComment($data['comment']);
                }
                if ($data['ip'] !== null) {
                    $entity->setIp($data['ip']);
                }
                if ($data['agent'] !== null) {
                    $entity->setAgent($data['agent']);
                }
                if ($data['date'] !== null) {
                    $entity->setDate($data['date'], $this->parameter('common_timezone', 'UTC'));
                }

                $this->entityManager->flush($entity);
            }

            return $entity;
        }

        throw new UserNotFoundException();
    }

    public function delete($entity): bool
    {
        switch (true) {
            case is_string($entity) && \Ramsey\Uuid\Uuid::isValid($entity):
            case is_object($entity) && is_a($entity, Uuid::class):
                $entity = $this->service->findOneByUuid((string) $entity);

                break;
        }

        if (is_object($entity) && is_a($entity, UserToken::class)) {
            $this->entityManager->remove($entity);
            $this->entityManager->flush();

            return true;
        }

        throw new UserGroupNotFoundException();
    }
}
