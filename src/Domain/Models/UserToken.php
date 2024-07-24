<?php declare(strict_types=1);

namespace App\Domain\Models;

use App\Domain\Casts\Uuid;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $uuid
 * @property string $user_uuid
 * @property string $unique
 * @property string $comment
 * @property string $ip
 * @property string $agent
 * @property \DateTime $date
 * @property User $user
 */
class UserToken extends Model
{
    use HasUuids;

    protected $table = 'user_token';

    protected $primaryKey = 'uuid';

    public const CREATED_AT = 'date';
    public const UPDATED_AT = 'date';

    protected $fillable = [
        'user_uuid',
        'unique',
        'comment',
        'ip',
        'agent',
        'date',
    ];

    protected $guarded = [];

    protected $casts = [
        'user_uuid' => Uuid::class,
        'unique' => 'string',
        'comment' => 'string',
        'ip' => 'string',
        'agent' => 'string',
        'date' => 'datetime',
    ];

    protected $attributes = [
        'user_uuid' => '',
        'unique' => '',
        'comment' => '',
        'ip' => '',
        'agent' => '',
        'date' => 'now',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_uuid', 'uuid');
    }
}
