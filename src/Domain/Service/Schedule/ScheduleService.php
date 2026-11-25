<?php declare(strict_types=1);

namespace App\Domain\Service\Schedule;

use App\Domain\AbstractService;
use App\Domain\Models\Schedule;
use Illuminate\Support\Collection;

class ScheduleService extends AbstractService
{
    public function create(array $data = []): Schedule
    {
        $row = new Schedule();
        $row->fill($data);
        $row->save();

        return $row;
    }

    /**
     * `key` gives the row of one job (created empty when there is none yet), otherwise all rows by key
     *
     * @return Collection|Schedule
     */
    public function read(array $data = [])
    {
        if (isset($data['key']) && is_string($data['key'])) {
            return Schedule::query()->find($data['key']) ?? $this->create(['key' => $data['key']]);
        }

        return Schedule::query()->orderBy('key')->get()->keyBy('key');
    }

    public function update($entity, array $data = []): Schedule
    {
        if (is_string($entity)) {
            $entity = $this->read(['key' => $entity]);
        }

        $entity->fill($data);
        $entity->save();

        return $entity;
    }

    public function delete($entity): bool
    {
        if (is_string($entity)) {
            $entity = Schedule::query()->find($entity);
        }

        return $entity instanceof Schedule && $entity->delete();
    }
}
