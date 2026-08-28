<?php declare(strict_types=1);

namespace App\Domain\Service\Page;

use App\Domain\AbstractService;
use App\Domain\Models\Page;
use App\Domain\Service\Page\Exception\AddressAlreadyExistsException;
use App\Domain\Service\Page\Exception\MissingTitleValueException;
use App\Domain\Service\Page\Exception\PageNotFoundException;
use App\Domain\Service\Page\Exception\TitleAlreadyExistsException;
use Illuminate\Support\Collection;
use Ramsey\Uuid\UuidInterface as Uuid;

class PageService extends AbstractService
{
    protected static array $eager = ['files'];

    protected static array $search_columns = ['title', 'address'];

    protected function init(): void {}

    /**
     * @throws TitleAlreadyExistsException
     * @throws MissingTitleValueException
     * @throws AddressAlreadyExistsException
     */
    public function create(array $data = []): Page
    {
        $page = new Page();
        $page->fill($data);

        if (!$page->title) {
            throw new MissingTitleValueException();
        }

        // if address generation is enabled
        if ($this->parameter('common_auto_generate_address', 'no') === 'yes' && (!isset($data['address']) || blank($data['address']))) {
            $page->address = $page->title ?? uniqid();
        }

        if (Page::firstWhere(['title' => $page->title]) !== null) {
            throw new TitleAlreadyExistsException();
        }

        if (Page::firstWhere(['address' => $page->address]) !== null) {
            throw new AddressAlreadyExistsException();
        }

        $page->save();

        return $page;
    }

    /**
     * @throws PageNotFoundException
     */
    public function read(array $data = []): Collection|Page
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
        if ($data['type'] !== null && ($types = $this->limitByList($data['type'], \App\Domain\Casts\Page\Type::LIST))) {
            $criteria['type'] = $types;
        }

        switch (true) {
            case !is_array($data['uuid']) && $data['uuid'] !== null:
            case !is_array($data['title']) && $data['title'] !== null:
            case !is_array($data['address']) && $data['address'] !== null:
                /** @var Page $page */
                $page = $this->buildQuery(Page::query(), $criteria, $data)->first();

                return $page ?: throw new PageNotFoundException();

            default:
                return $this->buildQuery(Page::query(), $criteria, $data)->get();
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
                $entity = $this->read(['uuid' => $entity]);

                break;
        }

        if (is_object($entity) && is_a($entity, Page::class)) {
            $entity->fill($data);

            // if address generation is enabled
            if ($this->parameter('common_auto_generate_address', 'no') === 'yes' && (!isset($data['address']) || blank($data['address']))) {
                $entity->address = $entity->title ?? uniqid();
            }

            if ($entity->isDirty('title')) {
                $found = Page::firstWhere(['title' => $entity->title]);

                if ($found && $found->uuid !== $entity->uuid) {
                    throw new TitleAlreadyExistsException();
                }
            }

            if ($entity->isDirty('address')) {
                $found = Page::firstWhere(['address' => $entity->address]);

                if ($found && $found->uuid !== $entity->uuid) {
                    throw new AddressAlreadyExistsException();
                }
            }

            $entity->save();

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
                $entity = $this->read(['uuid' => $entity]);

                break;
        }

        if (is_object($entity) && is_a($entity, Page::class)) {
            $entity->files()->detach();
            $entity->delete();

            return true;
        }

        throw new PageNotFoundException();
    }
}
