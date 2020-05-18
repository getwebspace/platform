<?php declare(strict_types=1);

namespace App\Domain\Service\User;

use Alksily\Entity\Collection;
use App\Domain\AbstractService;
use App\Domain\Entities\User\Subscriber as UserSubscriber;
use App\Domain\Exceptions\WrongEmailValueException;
use App\Domain\Repository\User\SubscriberRepository as UserSubscriberRepository;
use App\Domain\Service\User\Exception\EmailAlreadyExistsException;
use App\Domain\Service\User\Exception\MissingUniqueValueException;
use App\Domain\Service\User\Exception\UserNotFoundException;
use Doctrine\ORM\EntityManager;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;

class SubscriberService extends AbstractService
{
    /**
     * @var UserSubscriberRepository
     */
    protected $service;

    public function __construct(EntityManager $entityManager, LoggerInterface $logger = null)
    {
        parent::__construct($entityManager, $logger);

        $this->service = $this->entityManager->getRepository(UserSubscriber::class);
    }

    /**
     * @param array $data
     *
     * @throws EmailAlreadyExistsException
     * @throws WrongEmailValueException
     *
     * @return null|UserSubscriber
     */
    public function create(array $data = []): ?UserSubscriber
    {
        $default = [
            'email' => '',
            'date' => 'now',
        ];
        $data = array_merge($default, $data);

        if ($data['email'] && $this->service->findOneByEmail($data['email']) !== null) {
            throw new EmailAlreadyExistsException();
        }
        if (!$data['email']) {
            throw new MissingUniqueValueException();
        }

        $userSubscriber = (new UserSubscriber)
            ->setEmail($data['email'])
            ->setDate($data['date']);

        $this->entityManager->persist($userSubscriber);
        $this->entityManager->flush();

        return $userSubscriber;
    }

    /**
     * @param array $data
     *
     * @throws UserNotFoundException
     *
     * @return null|Collection|UserSubscriber
     */
    public function read(array $data = [])
    {
        $default = [
            'uuid' => '',
            'email' => '',
            'date' => '',
        ];
        $data = array_merge($default, $data);

        if ($data['uuid'] || $data['email']) {
            switch (true) {
                case $data['uuid']:
                    $userSubscriber = $this->service->findOneByUuid((string) $data['uuid']);

                    break;

                case $data['email']:
                    $userSubscriber = $this->service->findOneByEmail($data['email']);

                    break;
            }

            if (empty($userSubscriber)) {
                throw new UserNotFoundException();
            }

            return $userSubscriber;
        }

        $criteria = [];

        if ($data['date']) {
            $criteria['date'] = $data['date'];
        }

        return collect($this->service->findBy($criteria));
    }

    /**
     * @param string|UserSubscriber|Uuid $entity
     * @param array                      $data
     *
     * @throws EmailAlreadyExistsException
     * @throws UserNotFoundException
     * @throws WrongEmailValueException
     *
     * @return null|string|UserSubscriber
     */
    public function update($entity, array $data = [])
    {
        switch (true) {
            case is_string($entity) && Uuid::isValid($entity):
            case is_object($entity) && is_a($entity, Uuid::class):
                $entity = $this->service->findOneByUuid((string) $entity);

                break;
        }

        if (is_object($entity) && is_a($entity, UserSubscriber::class)) {
            $default = [
                'email' => '',
            ];
            $data = array_merge($default, $data);

            if ($data !== $default) {
                if ($data['email']) {
                    $found = $this->service->findOneByEmail($data['email']);

                    if ($found === null || $found === $entity) {
                        $entity->setEmail($data['email']);
                    } else {
                        throw new EmailAlreadyExistsException();
                    }
                }

                $entity->setDate('now');

                $this->entityManager->flush();
            }

            return $entity;
        }

        throw new UserNotFoundException();
    }

    /**
     * @param $entity
     *
     * @throws UserNotFoundException
     *
     * @return bool
     */
    public function delete($entity)
    {
        switch (true) {
            case is_string($entity) && Uuid::isValid($entity):
            case is_object($entity) && is_a($entity, Uuid::class):
                $entity = $this->service->findOneByUuid((string) $entity);

                break;
        }

        if (is_object($entity) && is_a($entity, UserSubscriber::class)) {
            $this->entityManager->remove($entity);
            $this->entityManager->flush();

            return true;
        }

        throw new UserNotFoundException();
    }
}
