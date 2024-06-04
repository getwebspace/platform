<?php declare(strict_types=1);

namespace App\Domain\Models;

use App\Domain\Casts\AddressUrl;
use App\Domain\Casts\Boolean;
use App\Domain\Casts\Catalog\Status as CatalogStatus;
use App\Domain\Casts\Json;
use App\Domain\Casts\Meta;
use App\Domain\Casts\Sort;
use App\Domain\Casts\Uuid;
use App\Domain\Traits\HasFiles;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;

/**
 * @property string $uuid
 * @property string $title
 * @property string $description
 * @property string $address
 * @property string $parent_uuid
 * @property int $pagination
 * @property bool $is_allow_nested
 * @property bool $is_hidden
 * @property int $order
 * @property string $status
 * @property array $sort
 * @property array $meta
 * @property array $template
 * @property string $external_id
 * @property string $export
 * @property string $system
 * @property array $specifics
 * @property CatalogCategory $parent
 * @property Collection<CatalogAttribute> $attributes
 * @property Collection<CatalogAttribute> $filters
 * @property Collection<CatalogProduct> $products
 */
class CatalogCategory extends Model
{
    use HasFactory;
    use HasUuids;
    use HasFiles;

    protected $table = 'catalog_category';

    protected $primaryKey = 'uuid';

    public const CREATED_AT = null;
    public const UPDATED_AT = null;

    protected $fillable = [
        'title',
        'description',
        'address',
        'parent_uuid',
        'pagination',
        'is_allow_nested',
        'is_hidden',
        'template',
        'meta',
        'sort',
        'status',
        'order',
        'specifics',
        'external_id',
        'export',
        'system',
    ];

    protected $guarded = [];

    protected $casts = [
        'title' => 'string',
        'description' => 'string',
        'address' => AddressUrl::class,
        'parent_uuid' => Uuid::class,
        'pagination' => 'int',
        'is_allow_nested' => Boolean::class,
        'is_hidden' => Boolean::class,
        'template' => Json::class,
        'meta' => Meta::class,
        'sort' => Sort::class,
        'status' => CatalogStatus::class,
        'order' => 'int',
        'specifics' => Json::class,
        'external_id' => 'string',
        'system' => 'string',
        'export' => 'string',
    ];

    protected $attributes = [
        'title' => '',
        'description' => '',
        'address' => '',
        'parent_uuid' => null,
        'pagination' => 10,
        'is_allow_nested' => false,
        'is_hidden' => false,
        'template' => '{}',
        'meta' => '{}',
        'sort' => '{}',
        'status' => \App\Domain\Casts\Catalog\Status::WORK,
        'order' => 1,
        'specifics' => '{}',
        'external_id' => '',
        'system' => '',
        'export' => 'manual',
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
            /** @var \App\Domain\Models\CatalogCategory $category */
            foreach (self::where(['parent_uuid' => $this->uuid])->get() as $child) {
                $collect = $collect->merge($child->nested($force));
            }
        }

        return $collect;
    }

    public function attributes(): BelongsToMany
    {
        return $this->belongsToMany(
            CatalogAttribute::class,
            'catalog_attribute_category',
            'category_uuid',
            'attribute_uuid',
            'uuid',
            'uuid'
        );
    }

    public function filtres(): BelongsToMany
    {
        return $this->attributes()->where('is_filter', true);
    }

    public function products(): HasMany
    {
        return $this->hasMany(CatalogProduct::class, 'category_uuid', 'uuid');
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
                'attributes' => $this->attributes()->getResults()->keyBy('address'),
                'files' => $this->files,
            ],
        );
    }
}
