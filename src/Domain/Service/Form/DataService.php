<?php declare(strict_types=1);

namespace App\Domain\Service\Form;

use App\Domain\AbstractService;
use App\Domain\Models\Form;
use App\Domain\Models\FormData;
use App\Domain\Repository\Form\DataRepository as FormDataRepository;
use App\Domain\Service\Form\Exception\FormDataNotFoundException;
use App\Domain\Service\Form\Exception\MissingMessageValueException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Ramsey\Uuid\UuidInterface as Uuid;

class DataService extends AbstractService
{


    /**
     * @throws MissingMessageValueException
     */
    public function create(array $data = []): FormData
    {
        $formData = new FormData;
        $formData->fill($data);

        if (!$formData->message && !$formData->data) {
            throw new MissingMessageValueException();
        }

        $formData->save();

        return $formData;
    }

    /**
     * @throws FormDataNotFoundException
     *
     * @return Collection|FormData
     */
    public function read(array $data = [])
    {
        $default = [
            'uuid' => null,
            'form_uuid' => null,
        ];
        $data = array_merge($default, static::$default_read, $data);

        $criteria = [];

        if ($data['uuid'] !== null) {
            $criteria['uuid'] = $data['uuid'];
        }
        if ($data['form_uuid'] !== null) {
            $criteria['form_uuid'] = $data['form_uuid'];
        }

        switch (true) {
            case !is_array($data['uuid']) && $data['uuid'] !== null:
                /** @var FormData $form */
                $form = FormData::firstWhere($criteria);

                return $form ?: throw new FormDataNotFoundException();

            default:
                $query = FormData::where($criteria);
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
     * @param FormData|string|Uuid $entity
     *
     * @throws FormDataNotFoundException
     */
    public function update($entity, array $data = []): FormData
    {
        switch (true) {
            case is_string($entity) && \Ramsey\Uuid\Uuid::isValid($entity):
            case is_object($entity) && is_a($entity, Uuid::class):
                $entity = $this->read(['uuid' => $entity]);

                break;
        }

        if (is_object($entity) && is_a($entity, FormData::class)) {
            $entity->update($data);

            return $entity;
        }

        throw new FormDataNotFoundException();
    }

    /**
     * @param FormData|string|Uuid $entity
     *
     * @throws FormDataNotFoundException
     */
    public function delete($entity): bool
    {
        switch (true) {
            case is_string($entity) && \Ramsey\Uuid\Uuid::isValid($entity):
            case is_object($entity) && is_a($entity, Uuid::class):
                $entity = $this->read(['uuid' => $entity]);

                break;
        }

        if (is_object($entity) && is_a($entity, FormData::class)) {
            $entity->files()->detach();
            $entity->delete();

            return true;
        }

        throw new FormDataNotFoundException();
    }
}
