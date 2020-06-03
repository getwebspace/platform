<?php declare(strict_types=1);

namespace App\Domain\Service\Parameter;

use Alksily\Entity\Collection;
use App\Domain\AbstractService;
use App\Domain\Entities\Parameter;
use App\Domain\Repository\ParameterRepository;
use App\Domain\Service\Parameter\Exception\ParameterAlreadyExistsException;
use App\Domain\Service\Parameter\Exception\ParameterNotFoundException;
use Doctrine\ORM\EntityManager;
use Psr\Log\LoggerInterface;

class ParameterService extends AbstractService
{
    /**
     * @var ParameterRepository
     */
    protected $service;

    public function __construct(EntityManager $entityManager, LoggerInterface $logger = null)
    {
        parent::__construct($entityManager, $logger);

        $this->service = $this->entityManager->getRepository(Parameter::class);
    }

    /**
     * @param array $data
     *
     * @throws ParameterAlreadyExistsException
     *
     * @return Parameter
     */
    public function create(array $data = []): Parameter
    {
        $default = [
            'key' => '',
            'value' => '',
        ];
        $data = array_merge($default, $data);

        if ($data['key'] && $this->service->findOneByKey($data['key']) !== null) {
            throw new ParameterAlreadyExistsException();
        }

        $parameter = (new Parameter)
            ->setKey($data['key'])
            ->setValue($data['value']);

        $this->entityManager->persist($parameter);
        $this->entityManager->flush();

        return $parameter;
    }

    /**
     * @param array $data
     * @param mixed $fallback
     *
     * @return Collection|Parameter
     */
    public function read(array $data = [], $fallback = null)
    {
        $default = [
            'key' => '',
        ];
        $data = array_merge($default, static::$default_read, $data);

        if ($data['key']) {
            switch (true) {
                case $data['key']:
                    $parameter = $this->service->findOneByKey((string) $data['key']);

                    break;
            }

            if (empty($parameter)) {
                $parameter = (new Parameter())->setKey($data['key'])->setValue($fallback);
            }

            return $parameter;
        }

        return collect($this->service->findBy([], $data['order'], $data['limit'], $data['offset']));
    }

    /**
     * @param Parameter|string $entity
     * @param array            $data
     *
     * @return Parameter
     */
    public function update($entity, array $data = []): Parameter
    {
        switch (true) {
            case is_string($entity):
                $entity = $this->service->findOneByKey((string) $entity);

                break;
        }

        if (is_object($entity) && is_a($entity, Parameter::class)) {
            $default = [
                'key' => '',
                'value' => '',
            ];
            $data = array_merge($default, $data);

            if ($data !== $default) {
                if ($data['key']) {
                    $found = $this->service->findOneByKey($data['key']);

                    if ($found === null || $found === $entity) {
                        $entity->setKey($data['key']);
                    } else {
                        throw new ParameterAlreadyExistsException();
                    }
                }
                if ($data['value']) {
                    $entity->setValue($data['value']);
                }

                $this->entityManager->flush();
            }

            return $entity;
        }

        throw new ParameterNotFoundException();
    }

    /**
     * @param Parameter|string $entity
     *
     * @throws ParameterNotFoundException
     *
     * @return bool
     */
    public function delete($entity): bool
    {
        switch (true) {
            case is_string($entity):
                $entity = $this->service->findOneByKey((string) $entity);

                break;
        }

        if (is_object($entity) && is_a($entity, Parameter::class)) {
            $this->entityManager->remove($entity);
            $this->entityManager->flush();

            return true;
        }

        throw new ParameterNotFoundException();
    }
}
