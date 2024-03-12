<?php declare(strict_types=1);

namespace App\Domain\Models;

use DateTime;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property string $uuid
 * @property string $user_uuid
 * @property User $user
 * @property string $unique
 * @property string $comment
 * @property string $ip
 * @property string $agent
 * @property DateTime $date
 */
class UserIntegration extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'user_integration';
    protected $primaryKey = 'uuid';

    const CREATED_AT = 'date';
    const UPDATED_AT = 'date';

    protected $fillable = [
        'user_uuid',
        'provider',
        'unique',
        'date',
    ];

    protected $guarded = [];

    protected $casts = [
        'user_uuid' => 'string',
        'provider' => 'string',
        'unique' => 'string',
        'date' => 'datetime',
    ];

    protected $attributes = [];

    public function user(): HasOne
    {
        return $this->hasOne(User::class, 'uuid', 'user_uuid');
    }
}
