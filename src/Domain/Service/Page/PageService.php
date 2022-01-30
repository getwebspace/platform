<?php declare(strict_types=1);

namespace App\Domain\Service\Page;

use App\Domain\AbstractService;
use App\Domain\Entities\Page;
use App\Domain\Repository\PageRepository;
use App\Domain\Service\Page\Exception\AddressAlreadyExistsException;
use App\Domain\Service\Page\Exception\MissingTitleValueException;
use App\Domain\Service\Page\Exception\PageNotFoundException;
use App\Domain\Service\Page\Exception\TitleAlreadyExistsException;
use Illuminate\Support\Collection;
use Ramsey\Uuid\UuidInterface as Uuid;

class PageService extends AbstractService
{
    /**
     * @var PageRepository
     */
    protected mixed $service;

    protected function init(): void
    {
        $this->service = $this->entityManager->getRepository(Page::class);
    }

    /**
     * @throws TitleAlreadyExistsException
     * @throws MissingTitleValueException
     * @throws AddressAlreadyExistsException
     */
    public function create(array $data = []): Page
    {
        $default = [
            'title' => '',
            'address' => '',
            'content' => '',
            'date' => 'now',
            'meta' => [
                'title' => '',
                'description' => '',
                'keywords' => '',
            ],
            'template' => '',
            'type' => \App\Domain\Types\PageTypeType::TYPE_HTML,
        ];
        $data = array_merge($default, $data);

        if ($data['title'] && $this->service->findOneByTitle($data['title']) !== null) {
            throw new TitleAlreadyExistsException();
        }
        if (!$data['title']) {
            throw new MissingTitleValueException();
        }
        if ($data['address'] && $this->service->findOneByAddress($data['address']) !== null) {
            throw new AddressAlreadyExistsException();
        }

        $page = (new Page())
            ->setTitle($data['title'])
            ->setAddress($data['address'])
            ->setContent($data['content'])
            ->setDate($data['date'])
            ->setMeta($data['meta'])
            ->setTemplate($data['template'])
            ->setType($data['type']);

        $this->entityManager->persist($page);
        $this->entityManager->flush();

        return $page;
    }

    /**
     * @throws PageNotFoundException
     *
     * @return Collection|Page
     */
    public function read(array $data = [])
    {
        $default = [
            'uuid' => null,
            'title' => null,
            'address' => null,
            'template' => null,
            'type' => null,
        ];
        $data = array_merge($default, static::$default_read, $data);

        $criteria = [];

        if ($data['uuid'] !== null) {
            $criteria['uuid'] = $data['uuid'];
        }
        if ($data['title'] !== null) {
            $criteria['title'] = $data['title'];
        }
        if ($data['address'] !== null) {
            $criteria['address'] = $data['address'];
        }
        if ($data['template'] !== null) {
            $criteria['template'] = $data['template'];
        }
        if ($data['type'] !== null && in_array($data['type'], \App\Domain\Types\PageTypeType::LIST, true)) {
            $criteria['type'] = $data['type'];
        }

        try {
            switch (true) {
                case !is_array($data['uuid']) && $data['uuid'] !== null:
                case !is_array($data['title']) && $data['title'] !== null:
                case !is_array($data['address']) && $data['address'] !== null:
                    $page = $this->service->findOneBy($criteria);

                    if (empty($page)) {
                        throw new PageNotFoundException();
                    }

                    return $page;

                default:
                    return collect($this->service->findBy($criteria, $data['order'], $data['limit'], $data['offset']));
            }
        } catch (\Doctrine\DBAL\Exception\TableNotFoundException $e) {
            return null;
        }
    }

    /**
     * @param Page|string|Uuid $entity
     *
     * @throws TitleAlreadyExistsException
     * @throws AddressAlreadyExistsException
     * @throws PageNotFoundException
     */
    public function update($entity, array $data = []): Page
    {
        switch (true) {
            case is_string($entity) && \Ramsey\Uuid\Uuid::isValid($entity):
            case is_object($entity) && is_a($entity, Uuid::class):
                $entity = $this->service->findOneByUuid((string) $entity);

                break;
        }

        if (is_object($entity) && is_a($entity, Page::class)) {
            $default = [
                'title' => null,
                'address' => null,
                'content' => null,
                'date' => null,
                'meta' => null,
                'template' => null,
                'type' => null,
            ];
            $data = array_merge($default, $data);

            if ($data !== $default) {
                if ($data['title'] !== null) {
                    $found = $this->service->findOneByTitle($data['title']);

                    if ($found === null || $found === $entity) {
                        $entity->setTitle($data['title']);
                    } else {
                        throw new TitleAlreadyExistsException();
                    }
                }
                if ($data['address'] !== null) {
                    $found = $this->service->findOneByAddress($data['address']);

                    if ($found === null || $found === $entity) {
                        $entity->setAddress($data['address']);
                    } else {
                        throw new AddressAlreadyExistsException();
                    }
                }
                if ($data['content'] !== null) {
                    $entity->setContent($data['content']);
                }
                if ($data['date'] !== null) {
                    $entity->setDate($data['date']);
                }
                if ($data['meta'] !== null) {
                    $entity->setMeta($data['meta']);
                }
                if ($data['template'] !== null) {
                    $entity->setTemplate($data['template']);
                }
                if ($data['type'] !== null) {
                    $entity->setType($data['type']);
                }

                $this->entityManager->flush();
            }

            return $entity;
        }

        throw new PageNotFoundException();
    }

    /**
     * @param Page|string|Uuid $entity
     *
     * @throws PageNotFoundException
     */
    public function delete($entity): bool
    {
        switch (true) {
            case is_string($entity) && \Ramsey\Uuid\Uuid::isValid($entity):
            case is_object($entity) && is_a($entity, Uuid::class):
                $entity = $this->service->findOneByUuid((string) $entity);

                break;
        }

        if (is_object($entity) && is_a($entity, Page::class)) {
            if (($files = $entity->getFiles()) && $files->isNotEmpty()) {
                $fileService = \App\Domain\Service\File\FileService::getWithContainer($this->container);

                /**
                 * @var \App\Domain\Entities\File $file
                 */
                foreach ($files as $file) {
                    try {
                        $fileService->delete($file);
                    } catch (\Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException $e) {
                        // nothing, file not found
                    } catch (\App\Domain\Service\File\Exception\FileNotFoundException $e) {
                        // nothing, file not found
                    }
                }
            }

            $this->entityManager->remove($entity);
            $this->entityManager->flush();

            return true;
        }

        throw new PageNotFoundException();
    }
}
