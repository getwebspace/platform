<?php declare(strict_types=1);

namespace App\Domain\Models;

use App\Domain\Casts\Review\EntityType as ReviewEntityType;
use App\Domain\Casts\Review\Status as ReviewStatus;
use App\Domain\Casts\Review\Type as ReviewType;
use App\Domain\Casts\Uuid;
use App\Domain\Traits\HasFiles;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property string $uuid
 * @property string $parent_uuid
 * @property string $user_uuid
 * @property string $type
 * @property string $entity_type
 * @property string $entity_uuid
 * @property int $rating
 * @property string $message
 * @property string $status
 * @property \DateTime $date
 * @property User $user
 * @property Review $parent
 * @property \Illuminate\Database\Eloquent\Collection<Review> $children
 */
class Review extends Model
{
    use HasUuids;
    use HasFiles;

    protected $table = 'review';

    protected $primaryKey = 'uuid';

    public const CREATED_AT = 'date';
    public const UPDATED_AT = null;

    protected $fillable = [
        'parent_uuid',
        'user_uuid',
        'type',
        'entity_type',
        'entity_uuid',
        'rating',
        'message',
        'status',
        'date',
    ];

    protected $guarded = [];

    protected $hidden = [
        'user_uuid',
    ];

    protected $casts = [
        'parent_uuid' => Uuid::class,
        'user_uuid' => Uuid::class,
        'type' => ReviewType::class,
        'entity_type' => ReviewEntityType::class,
        'entity_uuid' => Uuid::class,
        'rating' => 'int',
        'message' => 'string',
        'status' => ReviewStatus::class,
        'date' => 'datetime',
    ];

    protected $attributes = [
        'parent_uuid' => null,
        'user_uuid' => null,
        'type' => ReviewType::REVIEW,
        'entity_type' => ReviewEntityType::PUBLICATION,
        'entity_uuid' => null,
        'rating' => 0,
        'message' => '',
        'status' => ReviewStatus::MODERATE,
        'date' => 'now',
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

    public function user(): HasOne
    {
        return $this->hasOne(User::class, 'uuid', 'user_uuid');
    }

    public function parent(): HasOne
    {
        return $this->hasOne(self::class, 'uuid', 'parent_uuid');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_uuid', 'uuid');
    }

    public function toArray(): array
    {
        return array_merge(
            parent::toArray(),
            [
                'user' => $this->user ? [
                    'uuid' => $this->user->uuid,
                    'name' => $this->user->name(),
                    'avatar' => $this->user->avatar(),
                    'external_id' => $this->user->external_id,
                ] : null,
                'files' => $this->files,
                'children' => $this->getRelationValue('children')
                    ->where('status', ReviewStatus::WORK)
                    ->values(),
            ],
        );
    }
}
