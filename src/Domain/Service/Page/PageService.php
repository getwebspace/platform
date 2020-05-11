<?php declare(strict_types=1);

namespace App\Domain\Service\Page;

use App\Domain\AbstractService;
use App\Domain\Entities\Page;
use App\Domain\Repository\PageRepository;
use App\Domain\Service\Page\Exception\AddressAlreadyExistsException;
use App\Domain\Service\Page\Exception\MissingTitleValueException;
use App\Domain\Service\Page\Exception\PageNotFoundException;
use App\Domain\Service\Page\Exception\TitleAlreadyExistsException;
use Doctrine\ORM\EntityManager;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;

class PageService extends AbstractService
{
    /**
     * @var PageRepository
     */
    protected $service;

    public function __construct(EntityManager $entityManager, LoggerInterface $logger = null)
    {
        parent::__construct($entityManager, $logger);

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
    function create(array $data = []): ?Page
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
     * @return null|Page|Page[]
     */
    function read(array $data = []): ?Page
    {
        $default = [
            'title' => '',
            'address' => '',
        ];
        $data = array_merge($default, $data);

        if ($data['title'] || $data['address']) {
            switch (true) {
                case $data['title']:
                    $page = $this->service->findOneByTitle($data['title']);
                    break;

                case $data['address']:
                    $page = $this->service->findOneByAddress($data['address']);
                    break;
            }

            if ($page === null) {
                throw new PageNotFoundException();
            }

            return $page;
        }

        return $this->service->findAll();
    }

    /**
     * @param string|Page|Uuid $entity
     * @param array            $data
     *
     * @throws PageNotFoundException
     *
     * @return Page|null
     */
    function update($entity, array $data = []): ?Page
    {
        switch (true) {
            case is_string($entity) && Uuid::isValid($entity):
            case is_object($entity) && is_a($entity, Uuid::class):
                $entity = $this->service->findByUuid((string) $entity);

                break;
        }

        if (is_object($entity) && is_a($entity, Page::class)) {
            $default = [
                'title' => '',
                'address' => '',
                'content' => '',
                'date' => '',
                'meta' => [
                    'title' => '',
                    'description' => '',
                    'keywords' => '',
                ],
                'template' => '',
                'type' => '',
            ];
            $data = array_merge($default, $data);

            if ($data !== $default) {
                if ($data['title']) {
                    $entity->setTitle($data['title']);
                }
                if ($data['address']) {
                    $entity->setAddress($data['address']);
                }
                if ($data['content']) {
                    $entity->setContent($data['content']);
                }
                if ($data['date']) {
                    $entity->setDate($data['date']);
                }
                if ($data['meta']) {
                    $entity->setMeta($data['meta']);
                }
                if ($data['template']) {
                    $entity->setTemplate($data['template']);
                }
                if ($data['type']) {
                    $entity->setType($data['type']);
                }

                $this->entityManager->flush();
            }

            return $entity;
        }

        throw new PageNotFoundException();
    }

    /**
     * @param string|Page|Uuid $entity
     *
     * @throws PageNotFoundException
     *
     * @return bool
     */
    function delete($entity)
    {
        switch (true) {
            case is_string($entity) && Uuid::isValid($entity):
            case is_object($entity) && is_a($entity, Uuid::class):
                $entity = $this->service->findByUuid((string) $entity);

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
