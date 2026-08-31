<?php declare(strict_types=1);

namespace App\Domain\Models;

use App\Domain\Casts\AddressUrl;
use App\Domain\Casts\Json;
use App\Domain\Casts\Meta;
use App\Domain\Casts\Uuid;
use App\Domain\Traits\HasFiles;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
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

    /**
     * Only `date` is kept as a timestamp column (there is no updated_at).
     * Drop the null UPDATED_AT so Eloquent does not index an array with a
     * null key - deprecated since PHP 8.5.
     */
    public function getDates(): array
    {
        return array_values(array_filter(parent::getDates(), static fn ($column) => $column !== null));
    }

    public function category(): HasOne
    {
        return $this->hasOne(PublicationCategory::class, 'uuid', 'category_uuid');
    }

    public function user(): HasOne
    {
        return $this->hasOne(User::class, 'uuid', 'user_uuid');
    }

    /**
     * Top-level reviews left on this publication (replies excluded)
     */
    public function reviews(): HasMany
    {
        return $this
            ->hasMany(Review::class, 'entity_uuid', 'uuid')
            ->where('entity_type', \App\Domain\Casts\Review\EntityType::PUBLICATION)
            ->where('type', \App\Domain\Casts\Review\Type::REVIEW)
            ->whereNull('parent_uuid');
    }

    public function toArray(): array
    {
        return array_merge(
            parent::toArray(),
            [
                'category' => $this->category ? [
                    'uuid' => $this->category->uuid,
                    'title' => $this->category->title,
                    'address' => $this->category->address,
                ] : null,
                'user' => $this->user ? [
                    'uuid' => $this->user->uuid,
                    'name' => $this->user->name(),
                    'avatar' => $this->user->avatar(),
                    'external_id' => $this->user->external_id,
                ] : null,
                'files' => $this->files,
            ],
        );
    }
}
