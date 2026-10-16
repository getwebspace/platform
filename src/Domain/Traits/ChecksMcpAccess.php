<?php declare(strict_types=1);

namespace App\Domain\Traits;

use App\Domain\Casts\ApiKey\Status as ApiKeyStatus;
use App\Domain\Models\ApiKey;

/**
 * Who may use an MCP tool or resource: same rules as EntityAction
 *
 * The class using it defines constants SCOPE (API entity, see ApiEntity), PRIVATE and READ_ONLY:
 * an API key needs read (write for READ_ONLY = false) scope for the entity; without SCOPE only
 * full access keys may use private or writing things; without a key only public read-only ones are available
 */
trait ChecksMcpAccess
{
    public function isAllowed(?ApiKey $apiKey): bool
    {
        if ($apiKey === null) {
            return !static::PRIVATE && static::READ_ONLY;
        }
        // no scope: private or writing things only for full access keys
        if (static::SCOPE === '') {
            return (!static::PRIVATE && static::READ_ONLY) || ($apiKey->is_full_access && $apiKey->status === ApiKeyStatus::WORK);
        }

        return $apiKey->can(static::SCOPE, static::READ_ONLY ? 'read' : 'write');
    }
}
