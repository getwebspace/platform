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
 * @property string $unique
 * @property string $comment
 * @property string $ip
 * @property string $agent
 * @property DateTime $date
 * @property User $user
 */
class UserToken extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'user_token';
    protected $primaryKey = 'uuid';

    const CREATED_AT = 'date';
    const UPDATED_AT = 'date';

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
        'user_uuid' => 'string',
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

    public function user(): HasOne
    {
        return $this->hasOne(User::class, 'uuid', 'user_uuid');
    }
}
