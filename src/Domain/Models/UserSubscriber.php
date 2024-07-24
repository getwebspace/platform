<?php declare(strict_types=1);

namespace App\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $uuid
 * @property string $email
 * @property \DateTime $date
 */
class UserSubscriber extends Model
{
    use HasUuids;

    protected $table = 'user_subscriber';

    protected $primaryKey = 'uuid';

    public const CREATED_AT = 'date';
    public const UPDATED_AT = 'date';

    protected $fillable = [
        'email',
        'date',
    ];

    protected $guarded = [];

    protected $casts = [
        'email' => 'string',
        'date' => 'datetime',
    ];

    protected $attributes = [
        'email' => '',
        'date' => 'now',
    ];
}
