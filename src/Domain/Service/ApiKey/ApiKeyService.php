<?php declare(strict_types=1);

namespace App\Domain\Service\ApiKey;

use App\Domain\AbstractService;
use App\Domain\Casts\ApiKey\Status as ApiKeyStatus;
use App\Domain\Models\ApiKey;
use App\Domain\References\ApiEntity;
use App\Domain\Service\ApiKey\Exception\ApiKeyNotFoundException;
use App\Domain\Service\ApiKey\Exception\MissingTitleValueException;
use App\Domain\Traits\UseSecurity;
use Illuminate\Support\Collection;
use Ramsey\Uuid\UuidInterface as Uuid;

class ApiKeyService extends AbstractService
{
    use UseSecurity;

    /**
     * @throws MissingTitleValueException
     */
    public function create(array $data = []): ApiKey
    {
        $apiKey = new ApiKey();
        $apiKey->fill($this->prepare($data));

        if (!$apiKey->title) {
            throw new MissingTitleValueException();
        }

        $apiKey->save();

        return $apiKey;
    }

    /**
     * @throws ApiKeyNotFoundException
     *
     * @return ApiKey|Collection
     */
    public function read(array $data = [])
    {
        $default = [
            'uuid' => null,
            'status' => null,
        ];
        $data = array_merge($default, static::$default_read, $data);

        $criteria = [];

        if ($data['uuid'] !== null) {
            $criteria['uuid'] = $data['uuid'];
        }
        if ($data['status'] !== null && ($statuses = $this->limitByList($data['status'], ApiKeyStatus::LIST))) {
            $criteria['status'] = $statuses;
        }

        switch (true) {
            case !is_array($data['uuid']) && $data['uuid'] !== null:
                /** @var ApiKey $apiKey */
                $apiKey = $this->buildQuery(ApiKey::query(), $criteria, $data)->first();

                return $apiKey ?: throw new ApiKeyNotFoundException();

            default:
                return $this->buildQuery(ApiKey::query(), $criteria, $data)->get();
        }
    }

    /**
     * @param ApiKey|string|Uuid $entity
     *
     * @throws MissingTitleValueException
     * @throws ApiKeyNotFoundException
     */
    public function update($entity, array $data = []): ApiKey
    {
        switch (true) {
            case is_string($entity) && \Ramsey\Uuid\Uuid::isValid($entity):
            case is_object($entity) && is_a($entity, Uuid::class):
                $entity = $this->read(['uuid' => $entity]);

                break;
        }

        if (is_object($entity) && is_a($entity, ApiKey::class)) {
            $entity->fill($this->prepare($data));

            if (!$entity->title) {
                throw new MissingTitleValueException();
            }

            $entity->save();

            return $entity;
        }

        throw new ApiKeyNotFoundException();
    }

    /**
     * @param ApiKey|string|Uuid $entity
     *
     * @throws ApiKeyNotFoundException
     */
    public function delete($entity): bool
    {
        switch (true) {
            case is_string($entity) && \Ramsey\Uuid\Uuid::isValid($entity):
            case is_object($entity) && is_a($entity, Uuid::class):
                $entity = $this->read(['uuid' => $entity]);

                break;
        }

        if (is_object($entity) && is_a($entity, ApiKey::class)) {
            $entity->delete();

            return true;
        }

        throw new ApiKeyNotFoundException();
    }

    /**
     * The bearer credential handed to the integration
     *
     * A JWT rather than a stored secret: nothing at rest can be leaked from a
     * database dump beyond the same private key every other signed link in
     * the app already depends on, and it is re-derivable any time the admin
     * wants to display it again. It never expires by clock - revocation is
     * the `status` column, checked on every request in AuthorizationAPIMiddleware
     */
    public function issueToken(ApiKey $apiKey): string
    {
        return $this->encodeJWT('api-key', $apiKey->uuid, [], 0);
    }

    /**
     * Only accept scopes for entities that actually exist, both to keep the
     * stored data honest and so a typo in a form field can't quietly grant
     * access to something unintended - or, worse, nothing at all silently
     */
    private function prepare(array $data): array
    {
        foreach (['read', 'write'] as $mode) {
            if (isset($data['scopes'][$mode])) {
                $data['scopes'][$mode] = array_values(array_intersect(
                    (array) $data['scopes'][$mode],
                    array_keys(ApiEntity::MAP)
                ));
            }
        }

        return $data;
    }
}
