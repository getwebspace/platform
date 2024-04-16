<?php declare(strict_types=1);

namespace App\Domain\Models;

use App\Domain\Casts\AddressUrl;
use App\Domain\Casts\Boolean;
use App\Domain\Casts\Email;
use App\Domain\Casts\Catalog\Status as CatalogStatus;
use App\Domain\Casts\Json;
use App\Domain\Casts\Meta;
use App\Domain\Casts\Sort;
use App\Domain\References\Date;
use App\Domain\Traits\FileTrait;
use DateTime;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;

/**
 * @property string $uuid
 * @property string $title
 * @property string $description
 * @property string $address
 * @property string $parent_uuid
 * @property int $pagination
 * @property bool $children
 * @property bool $hidden
 * @property int $order
 * @property string $status
 * @property array $sort
 * @property array $meta
 * @property array $template
 * @property string $external_id
 * @property string $export
 * @property string $system
 * @property CatalogCategory $parent
 * @property CatalogAttribute[] $attributes
 * @property CatalogProduct[] $products
 */
class CatalogCategory extends Model
{
    use HasFactory;
    use HasUuids;
    use FileTrait;

    protected $table = 'catalog_category';
    protected $primaryKey = 'uuid';

    const CREATED_AT = null;
    const UPDATED_AT = null;

    protected $fillable = [
        'title',
        'description',
        'address',
        'parent_uuid',
        'pagination',
        'children',
        'hidden',
        'order',
        'status',
        'sort',
        'meta',
        'template',
        'external_id',
        'export',
        'system',
    ];

    protected $guarded = [];

    protected $casts = [
        'title' => 'string',
        'description' => 'string',
        'address' => AddressUrl::class,
        'parent_uuid' => 'string',
        'pagination' => 'int',
        'children' => Boolean::class,
        'hidden' => Boolean::class,
        'template' => Json::class,
        'meta' => Meta::class,
        'sort' => Sort::class,
        'status' => CatalogStatus::class,
        'order' => 'int',
        'system' => 'string',
        'export' => 'string',
        'external_id' => 'string',
    ];

    protected $attributes = [
        'title' => '',
        'description' => '',
        'address' => '',
        'parent_uuid' => null,
        'pagination' => 10,
        'children' => false,
        'hidden' => false,
        'template' => '{}',
        'meta' => '{}',
        'sort' => '{}',
        'status' => \App\Domain\Casts\Catalog\Status::WORK,
        'order' => 1,
        'system' => '',
        'export' => 'manual',
        'external_id' => '',
    ];

    public function parent(): HasOne
    {
        return $this->hasOne(CatalogCategory::class, 'uuid', 'parent_uuid');
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

        if ($this->children || $force) {
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

    public function products(): HasMany
    {
        return $this->hasMany(CatalogProduct::class, 'category_uuid', 'uuid');
    }

    public function toArray(): array
    {
        return array_merge(
            parent::toArray(),
            [
                'attributes' => $this->attributes()->getResults()->keyBy('address'),
                'files' => $this->files,
            ],
        );
    }
}
