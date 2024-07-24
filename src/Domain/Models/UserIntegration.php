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
class UserIntegration extends Model
{
    use HasUuids;

    protected $table = 'user_integration';

    protected $primaryKey = 'uuid';

    public const CREATED_AT = 'date';
    public const UPDATED_AT = 'date';

    protected $fillable = [
        'user_uuid',
        'provider',
        'unique',
        'date',
    ];

    protected $guarded = [];

    protected $casts = [
        'user_uuid' => Uuid::class,
        'provider' => 'string',
        'unique' => 'string',
        'date' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_uuid', 'uuid');
    }
}
