<?php declare(strict_types=1);

namespace App\Domain\Service\Form;

use App\Domain\AbstractService;
use App\Domain\Models\Form;
use App\Domain\Models\Page;
use App\Domain\Service\Form\Exception\AddressAlreadyExistsException;
use App\Domain\Service\Form\Exception\FormNotFoundException;
use App\Domain\Service\Form\Exception\MissingTitleValueException;
use App\Domain\Service\Form\Exception\TitleAlreadyExistsException;
use App\Domain\Service\Page\Exception\PageNotFoundException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Ramsey\Uuid\UuidInterface as Uuid;

class FormService extends AbstractService
{


    /**
     * @throws TitleAlreadyExistsException
     * @throws MissingTitleValueException
     * @throws AddressAlreadyExistsException
     */
    public function create(array $data = []): Form
    {
        $form = new Form;
        $form->fill($data);

        if (!$form->title) {
            throw new MissingTitleValueException();
        }

        if (Form::firstWhere(['title' => $form->title]) !== null) {
            throw new TitleAlreadyExistsException();
        }

        if (Form::firstWhere(['address' => $form->address]) !== null) {
            throw new AddressAlreadyExistsException();
        }

        $form->save();

        return $form;
    }

    /**
     * @throws FormNotFoundException
     *
     * @return Collection|Form
     */
    public function read(array $data = [])
    {
        $default = [
            'uuid' => null,
            'title' => null,
            'address' => null,
            'template' => null,
            'mailto' => null,
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
        if ($data['mailto'] !== null) {
            $criteria['mailto'] = $data['mailto'];
        }

        switch (true) {
            case !is_array($data['uuid']) && $data['uuid'] !== null:
            case !is_array($data['title']) && $data['title'] !== null:
            case !is_array($data['address']) && $data['address'] !== null:
                /** @var Form $form */
                $form = Form::firstWhere($criteria);

                return $form ?: throw new FormNotFoundException();

            default:
                $query = Form::where($criteria);
                /** @var Builder $query */

                foreach ($data['order'] as $column => $direction) {
                    $query = $query->orderBy($column, $direction);
                }
                if ($data['limit']) {
                    $query = $query->limit($data['limit']);
                }
                if ($data['offset']) {
                    $query = $query->offset($data['offset']);
                }

                return $query->get();
        }
    }

    /**
     * @param Form|string|Uuid $entity
     *
     * @throws TitleAlreadyExistsException
     * @throws AddressAlreadyExistsException
     * @throws FormNotFoundException
     */
    public function update($entity, array $data = []): Form
    {
        switch (true) {
            case is_string($entity) && \Ramsey\Uuid\Uuid::isValid($entity):
            case is_object($entity) && is_a($entity, Uuid::class):
                $entity = $this->read(['uuid' => $entity]);

                break;
        }

        if (is_object($entity) && is_a($entity, Form::class)) {
            $entity->fill($data);

            if ($entity->isDirty('title')) {
                $found = Form::firstWhere(['title' => $entity->title]);

                if ($found && $found->uuid !== $entity->uuid) {
                    throw new TitleAlreadyExistsException();
                }
            }

            if ($entity->isDirty('address')) {
                $found = Form::firstWhere(['address' => $entity->title]);

                if ($found && $found->uuid !== $entity->uuid) {
                    throw new AddressAlreadyExistsException();
                }
            }

            $entity->save();

            return $entity;
        }

        throw new FormNotFoundException();
    }

    /**
     * @param Form|string|Uuid $entity
     *
     * @throws FormNotFoundException
     */
    public function delete($entity): bool
    {
        switch (true) {
            case is_string($entity) && \Ramsey\Uuid\Uuid::isValid($entity):
            case is_object($entity) && is_a($entity, Uuid::class):
                $entity = $this->read(['uuid' => $entity]);

                break;
        }

        if (is_object($entity) && is_a($entity, Form::class)) {
            $entity->delete();

            return true;
        }

        throw new FormNotFoundException();
    }
}
