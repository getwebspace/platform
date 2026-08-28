<?php declare(strict_types=1);

namespace App\Domain\Models;

use App\Domain\Casts\ApiKey\Status as ApiKeyStatus;
use App\Domain\Casts\Json;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string   $uuid
 * @property string   $title
 * @property array    $scopes
 * @property bool     $is_full_access
 * @property string   $status
 * @property \DateTime $date
 */
class ApiKey extends Model
{
    use HasUuids;

    protected $table = 'api_key';

    protected $primaryKey = 'uuid';

    public const CREATED_AT = 'date';
    public const UPDATED_AT = null;

    protected $fillable = [
        'title',
        'scopes',
        'is_full_access',
        'status',
    ];

    protected $casts = [
        'title' => 'string',
        'scopes' => Json::class,
        'is_full_access' => 'boolean',
        'status' => ApiKeyStatus::class,
        'date' => 'datetime',
    ];

    protected $attributes = [
        'title' => '',
        'scopes' => '{"read":[],"write":[]}',
        'is_full_access' => false,
        'status' => ApiKeyStatus::WORK,
        'date' => 'now',
    ];

    /**
     * Whether this key may perform $mode ('read' or 'write') on $entity
     *
     * A full-access key skips the scope list entirely - it is the explicit
     * opt-in equivalent of the old flat `entity_keys` string
     */
    public function can(string $entity, string $mode): bool
    {
        if ($this->status !== ApiKeyStatus::WORK) {
            return false;
        }
        if ($this->is_full_access) {
            return true;
        }

        return in_array($entity, (array) ($this->scopes[$mode] ?? []), true);
    }
}
