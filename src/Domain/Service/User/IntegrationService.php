<?php declare(strict_types=1);

namespace App\Domain\Service\User;

use App\Domain\AbstractService;
use App\Domain\Entities\User\Integration as UserIntegration;
use App\Domain\Repository\User\IntegrationRepository as UserIntegrationRepository;
use App\Domain\Service\User\Exception\IntegrationNotFoundException;
use Illuminate\Support\Collection;
use Ramsey\Uuid\UuidInterface as Uuid;

class IntegrationService extends AbstractService
{
    /**
     * @var UserIntegrationRepository
     */
    protected mixed $service;

    protected function init(): void
    {
        $this->service = $this->entityManager->getRepository(UserIntegration::class);
    }

    /**
     * @throws \RuntimeException
     */
    public function create(array $data = []): UserIntegration
    {
        $default = [
            'user' => '',
            'provider' => '',
            'unique' => '',
            'date' => 'now',
        ];
        $data = array_merge($default, $data);

        if (!$data['user'] || !$data['provider'] || !$data['unique']) {
            throw new \RuntimeException();
        }

        $userIntegration = (new UserIntegration())
            ->setUser($data['user'])
            ->setProvider($data['provider'])
            ->setUnique($data['unique'])
            ->setDate($data['date'], $this->parameter('common_timezone', 'UTC'));

        $this->entityManager->persist($userIntegration);
        $this->entityManager->flush();

        return $userIntegration;
    }

    /**
     * @throws IntegrationNotFoundException
     *
     * @return Collection|UserIntegration
     */
    public function read(array $data = [])
    {
        $default = [
            'provider' => null,
            'unique' => null,
        ];
        $data = array_merge($default, static::$default_read, $data);

        $criteria = [];

        if ($data['provider'] !== null) {
            $criteria['provider'] = $data['provider'];
        }
        if ($data['unique'] !== null) {
            $criteria['unique'] = $data['unique'];
        }

        try {
            switch (true) {
                case !is_array($data['provider']) && $data['provider'] !== null:
                case !is_array($data['unique']) && $data['unique'] !== null:
                    $userIntegration = $this->service->findOneBy($criteria);

                    if (empty($userIntegration)) {
                        throw new IntegrationNotFoundException();
                    }

                    return $userIntegration;

                default:
                    return collect($this->service->findBy($criteria, $data['order'], $data['limit'], $data['offset']));
            }
        } catch (\Doctrine\DBAL\Exception\TableNotFoundException $e) {
            return null;
        }
    }

    public function update($entity, array $data = []): void
    {
    }

    /**
     * @param mixed $entity
     *
     * @throws IntegrationNotFoundException
     */
    public function delete($entity): bool
    {
        switch (true) {
            case is_string($entity) && \Ramsey\Uuid\Uuid::isValid($entity):
            case is_object($entity) && is_a($entity, Uuid::class):
                $entity = $this->service->findOneByUuid((string) $entity);

                break;
        }

        if (is_object($entity) && is_a($entity, UserIntegration::class)) {
            $this->entityManager->remove($entity);
            $this->entityManager->flush();

            return true;
        }

        throw new IntegrationNotFoundException();
    }
}
