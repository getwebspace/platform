<?php declare(strict_types=1);

namespace App\Domain\Models;

use App\Domain\Casts\AddressUrl;
use App\Domain\Casts\Boolean;
use App\Domain\Casts\Json;
use App\Domain\Casts\Meta;
use App\Domain\Casts\Sort;
use App\Domain\Casts\Uuid;
use App\Domain\Traits\HasFiles;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;

/**
 * @property string $uuid
 * @property string $title
 * @property string $address
 * @property string $parent_uuid
 * @property string $description
 * @property int $pagination
 * @property bool $is_allow_nested
 * @property bool $is_public
 * @property array $sort
 * @property array $template
 * @property array $meta
 * @property PublicationCategory $parent
 * @property Collection<Publication> $publications
 */
class PublicationCategory extends Model
{
    use HasUuids;
    use HasFiles;

    protected $table = 'publication_category';

    protected $primaryKey = 'uuid';

    public const CREATED_AT = null;
    public const UPDATED_AT = null;

    protected $fillable = [
        'title',
        'address',
        'parent_uuid',
        'description',
        'pagination',
        'is_allow_nested',
        'is_public',
        'sort',
        'template',
        'meta',
    ];

    protected $guarded = [];

    protected $casts = [
        'title' => 'string',
        'address' => AddressUrl::class,
        'parent_uuid' => Uuid::class,
        'description' => 'string',
        'pagination' => 'int',
        'is_allow_nested' => Boolean::class,
        'is_public' => Boolean::class,
        'sort' => Sort::class,
        'template' => Json::class,
        'meta' => Meta::class,
    ];

    protected $attributes = [
        'title' => '',
        'address' => '',
        'parent_uuid' => null,
        'description' => '',
        'pagination' => 10,
        'is_allow_nested' => false,
        'is_public' => true,
        'sort' => '{}',
        'template' => '{}',
        'meta' => '{}',
    ];

    public function parent(): HasOne
    {
        return $this->hasOne(self::class, 'uuid', 'parent_uuid');
    }

    public function parents(): Collection
    {
        $collect = collect([$this]);

        if ($this->parent) {
            $collect = $collect->merge($this->parent->parents());
        }

        return $collect;
    }

    public function nested(bool $force = false): Collection
    {
        $collect = collect([$this]);

        if ($this->is_allow_nested || $force) {
            /** @var \App\Domain\Models\PublicationCategory $category */
            foreach (self::where(['parent_uuid' => $this->uuid])->get() as $child) {
                $collect = $collect->merge($child->nested($force));
            }
        }

        return $collect;
    }

    public function publications(): HasMany
    {
        return $this->hasMany(Publication::class, 'category_uuid', 'uuid');
    }

    public function toArray(): array
    {
        $parent = null;

        if ($this->parent) {
            $parent = [
                'title' => $this->parent->title,
                'address' => $this->parent->address,
            ];
        }

        return array_merge(
            parent::toArray(),
            [
                'parent' => $parent,
                'files' => $this->files,
            ],
        );
    }
}
