<?php declare(strict_types=1);

namespace App\Domain\Models;

use App\Domain\Casts\AddressUrl;
use App\Domain\Casts\Json;
use App\Domain\Casts\Meta;
use App\Domain\Casts\Uuid;
use App\Domain\Traits\HasFiles;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property string $uuid
 * @property string $title
 * @property string $address
 * @property string $category_uuid
 * @property array $content
 * @property array $meta
 * @property \DateTime $date
 * @property string $external_id
 * @property PublicationCategory $category
 * @property User $user
 */
class Publication extends Model
{
    use HasUuids;
    use HasFiles;

    protected $table = 'publication';

    protected $primaryKey = 'uuid';

    public const CREATED_AT = 'date';
    public const UPDATED_AT = null;

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
        'category_uuid' => Uuid::class,
        'user_uuid' => Uuid::class,
        'content' => Json::class,
        'meta' => Meta::class,
        'date' => 'datetime',
        'external_id' => 'string',
    ];

    protected $attributes = [
        'title' => '',
        'address' => '',
        'category_uuid' => null,
        'user_uuid' => '',
        'content' => '{}',
        'meta' => '{}',
        'date' => 'now',
        'external_id' => '',
    ];

    public function category(): HasOne
    {
        return $this->hasOne(PublicationCategory::class, 'uuid', 'category_uuid');
    }

    public function user(): HasOne
    {
        return $this->hasOne(User::class, 'uuid', 'user_uuid');
    }

    public function toArray(): array
    {
        return array_merge(
            parent::toArray(),
            [
                'category' => [
                    'uuid' => $this->category->uuid,
                    'title' => $this->category->title,
                    'address' => $this->category->address,
                ],
                'user' => [
                    'uuid' => $this->user->uuid,
                    'name' => $this->user->name(),
                    'avatar' => $this->user->avatar(),
                    'external_id' => $this->user->external_id,
                ],
                'files' => $this->files,
            ],
        );
    }
}
