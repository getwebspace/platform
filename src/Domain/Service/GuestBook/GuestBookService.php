<?php declare(strict_types=1);

namespace App\Domain\Service\GuestBook;

use App\Domain\AbstractService;
use App\Domain\Models\GuestBook;
use App\Domain\Service\GuestBook\Exception\EntryNotFoundException;
use App\Domain\Service\GuestBook\Exception\MissingEmailValueException;
use App\Domain\Service\GuestBook\Exception\MissingMessageValueException;
use App\Domain\Service\GuestBook\Exception\MissingNameValueException;
use App\Domain\Service\GuestBook\Exception\WrongEmailValueException;
use Illuminate\Support\Collection;
use Ramsey\Uuid\UuidInterface as Uuid;

class GuestBookService extends AbstractService
{
    protected static array $search_columns = ['name', 'email', 'message'];

    /**
     * @throws MissingNameValueException
     * @throws MissingEmailValueException
     * @throws MissingMessageValueException
     * @throws WrongEmailValueException
     */
    public function create(array $data = []): GuestBook
    {
        $entry = new GuestBook();
        $entry->fill($data);

        if (!$entry->name) {
            throw new MissingNameValueException();
        }
        if (!$entry->email) {
            throw new MissingEmailValueException();
        }
        if (!$entry->message) {
            throw new MissingMessageValueException();
        }

        $entry->save();

        return $entry;
    }

    /**
     * @throws EntryNotFoundException
     *
     * @return Collection|GuestBook
     */
    public function read(array $data = [])
    {
        $default = [
            'uuid' => null,
            'email' => null,
            'status' => null,
        ];
        $data = array_merge($default, static::$default_read, $data);

        $criteria = [];

        if ($data['uuid'] !== null) {
            $criteria['uuid'] = $data['uuid'];
        }
        if ($data['email'] !== null) {
            $criteria['email'] = $data['email'];
        }
        if ($data['status'] !== null && ($statuses = $this->limitByList($data['status'], \App\Domain\Casts\GuestBook\Status::LIST))) {
            $criteria['status'] = $statuses;
        }

        switch (true) {
            case !is_array($data['uuid']) && $data['uuid'] !== null:
                /** @var GuestBook $entry */
                $entry = GuestBook::firstWhere($criteria);

                return $entry ?: throw new EntryNotFoundException();

            default:
                return $this->buildQuery(GuestBook::query(), $criteria, $data)->get();
        }
    }

    public function count(array $criteria = []): int
    {
        return GuestBook::where($criteria)->count();
    }

    /**
     * @param GuestBook|string|Uuid $entity
     *
     * @throws WrongEmailValueException
     * @throws EntryNotFoundException
     */
    public function update($entity, array $data = []): GuestBook
    {
        switch (true) {
            case is_string($entity) && \Ramsey\Uuid\Uuid::isValid($entity):
            case is_object($entity) && is_a($entity, Uuid::class):
                $entity = $this->read(['uuid' => $entity]);

                break;
        }

        if (is_object($entity) && is_a($entity, GuestBook::class)) {
            $entity->fill($data);
            $entity->save();

            return $entity;
        }

        throw new EntryNotFoundException();
    }

    /**
     * @param GuestBook|string|Uuid $entity
     *
     * @throws EntryNotFoundException
     */
    public function delete($entity): bool
    {
        switch (true) {
            case is_string($entity) && \Ramsey\Uuid\Uuid::isValid($entity):
            case is_object($entity) && is_a($entity, Uuid::class):
                $entity = $this->read(['uuid' => $entity]);

                break;
        }

        if (is_object($entity) && is_a($entity, GuestBook::class)) {
            $entity->delete();

            return true;
        }

        throw new EntryNotFoundException();
    }
}
