<?php declare(strict_types=1);

namespace App\Domain\Models;

use App\Domain\Casts\AddressUrl;
use App\Domain\Casts\Boolean;
use App\Domain\Casts\Email;
use App\Domain\Casts\GuestBook\Status as GuestBookStatus;
use App\Domain\Casts\Json;
use App\Domain\Casts\Meta;
use App\Domain\References\Date;
use App\Domain\Traits\FileTrait;
use DateTime;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property string $uuid
 * @property string $title
 * @property string $address
 * @property string $category_uuid
 * @property array $content
 * @property array $meta
 * @property DateTime $date
 * @property string $external_id
 * @property User $user
 */
class Publication extends Model
{
    use HasFactory;
    use HasUuids;
    use FileTrait;

    protected $table = 'publication';
    protected $primaryKey = 'uuid';

    const CREATED_AT = 'date';
    const UPDATED_AT = null;

    protected $fillable = [
        'title',
        'address',
        'category_uuid',
        'user_uuid',
        'content',
        'meta',
        'date',
        'external_id',
    ];

    protected $guarded = [];

    protected $casts = [
        'title' => 'string',
        'address' => AddressUrl::class,
        'category_uuid' => 'string',
        'user_uuid' => 'string',
        'content' => Json::class,
        'meta' => Meta::class,
        'date' => 'datetime',
        'external_id' => 'string',
    ];

    protected $attributes = [
        'content' => [
            'short' => '',
            'full' => '',
        ],
    ];

    public function category(): HasOne
    {
        return $this->hasOne(PublicationCategory::class, 'uuid', 'category_uuid');
    }

    public function user(): HasOne
    {
        return $this->hasOne(User::class, 'uuid', 'user_uuid');
    }
}
