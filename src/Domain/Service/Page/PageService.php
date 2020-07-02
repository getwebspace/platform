<?php declare(strict_types=1);

namespace App\Domain\Service\Page;

use App\Domain\AbstractService;
use App\Domain\Entities\Page;
use App\Domain\Repository\PageRepository;
use App\Domain\Service\Page\Exception\AddressAlreadyExistsException;
use App\Domain\Service\Page\Exception\MissingTitleValueException;
use App\Domain\Service\Page\Exception\PageNotFoundException;
use App\Domain\Service\Page\Exception\TitleAlreadyExistsException;
use Ramsey\Uuid\Uuid;
use Tightenco\Collect\Support\Collection;

class PageService extends AbstractService
{
    /**
     * @var PageRepository
     */
    protected $service;

    protected function init(): void
    {
        $this->service = $this->entityManager->getRepository(Page::class);
    }

    /**
     * @param array $data
     *
     * @throws TitleAlreadyExistsException
     * @throws MissingTitleValueException
     * @throws AddressAlreadyExistsException
     *
     * @return Page
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

        $page = (new Page)
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
     * @param array $data
     *
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

        if ($data['uuid'] || $data['title'] || $data['address']) {
            switch (true) {
                case $data['uuid']:
                    $page = $this->service->findOneByUuid((string) $data['uuid']);

                    break;

                case $data['title']:
                    $page = $this->service->findOneByTitle($data['title']);

                    break;

                case $data['address']:
                    $page = $this->service->findOneByAddress($data['address']);

                    break;
            }

            if (empty($page)) {
                throw new PageNotFoundException();
            }

            return $page;
        }

        $criteria = [];

        if ($data['template'] !== null) {
            $criteria['template'] = $data['template'];
        }
        if ($data['type'] !== null && in_array($data['type'], \App\Domain\Types\PageTypeType::LIST, true)) {
            $criteria['type'] = $data['type'];
        }

        return collect($this->service->findBy($criteria, $data['order'], $data['limit'], $data['offset']));
    }

    /**
     * @param Page|string|Uuid $entity
     * @param array            $data
     *
     * @throws TitleAlreadyExistsException
     * @throws AddressAlreadyExistsException
     * @throws PageNotFoundException
     *
     * @return Page
     */
    public function update($entity, array $data = []): Page
    {
        switch (true) {
            case is_string($entity) && Uuid::isValid($entity):
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
     *
     * @return bool
     */
    public function delete($entity): bool
    {
        switch (true) {
            case is_string($entity) && Uuid::isValid($entity):
            case is_object($entity) && is_a($entity, Uuid::class):
                $entity = $this->service->findOneByUuid((string) $entity);

                break;
        }

        if (is_object($entity) && is_a($entity, Page::class)) {
            $this->entityManager->remove($entity);
            $this->entityManager->flush();

            return true;
        }

        throw new PageNotFoundException();
    }
}
