<?php declare(strict_types=1);

namespace App\Domain\Models;

use App\Domain\Casts\Email;
use App\Domain\Casts\GuestBook\Status as GuestBookStatus;
use App\Domain\Traits\FileTrait;
use DateTime;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $uuid
 * @property string $name
 * @property string $email
 * @property string $message
 * @property string $response
 * @property string $status
 * @property DateTime $date
 */
class GuestBook extends Model
{
    use HasFactory;
    use HasUuids;
    use FileTrait;

    protected $table = 'guestbook';
    protected $primaryKey = 'uuid';

    const CREATED_AT = 'date';
    const UPDATED_AT = null;

    protected $fillable = [
        'name',
        'email',
        'message',
        'response',
        'status',
        'date',
    ];

    protected $guarded = [];

    protected $casts = [
        'name' => 'string',
        'email' => Email::class,
        'message' => 'string',
        'response' => 'string',
        'status' => GuestBookStatus::class,
        'date' => 'datetime',
    ];

    protected $attributes = [
        'name' => '',
        'email' => '',
        'message' => '',
        'response' => '',
        'status' => \App\Domain\Casts\GuestBook\Status::MODERATE,
        'date' => 'now',
    ];
}
