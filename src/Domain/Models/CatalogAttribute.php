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
use App\Domain\Traits\HasFiles;
use DateTime;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * @property string $uuid
 * @property string $title
 * @property string $address
 * @property string $type
 * @property string $group
 * @property bool $is_filter
 * @property CatalogCategory[] $categories
 * @property CatalogProduct[] $products
 */
class CatalogAttribute extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'catalog_attribute';
    protected $primaryKey = 'uuid';

    const CREATED_AT = null;
    const UPDATED_AT = null;

    protected $fillable = [
        'title',
        'address',
        'type',
        'group',
        'is_filter',
    ];

    protected $guarded = [];

    protected $casts = [
        'title' => 'string',
        'address' => AddressUrl::class,
        'type' => 'string',
        'group' => 'string',
        'is_filter' => Boolean::class,
    ];

    protected $attributes = [
        'title' => '',
        'address' => '',
        'type' => '',
        'group' => '',
        'is_filter' => true,
    ];

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(
            CatalogCategory::class,
            'catalog_attribute_category',
            'attribute_uuid',
            'category_uuid',
            'uuid',
            'uuid'
        );
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(
            CatalogProduct::class,
            'catalog_attribute_product',
            'attribute_uuid',
            'product_uuid',
            'uuid',
            'uuid'
        );
    }

    public function values(Collection $products = null): Collection
    {
        $query = $this
            ->newQuery()
            ->from('catalog_attribute_product')
            ->selectRaw('value, COUNT(*) as count')
            ->where('attribute_uuid', $this->uuid)
            ->whereRaw('value')
            ->groupBy('value');

        if ($products) {
            $query->whereIn('product_uuid', $products->pluck('uuid'));
        }

        return $query->pluck('count', 'value');
    }

    public function value(): mixed
    {
        if ($this->pivot && $this->pivot->value) {
            switch ($this->type) {
                case \App\Domain\Casts\Catalog\Attribute\Type::BOOLEAN:
                    return $this->pivot->value === 'yes';

                case \App\Domain\Casts\Catalog\Attribute\Type::INTEGER:
                    return intval($this->pivot->value);

                case \App\Domain\Casts\Catalog\Attribute\Type::FLOAT:
                    return floatval($this->pivot->value);

                case \App\Domain\Casts\Catalog\Attribute\Type::STRING:
                    return (string)$this->pivot->value;
            }
        }

        return null;
    }

    public function toArray(): array
    {
        $array = [
            'uuid' => $this->uuid,
            'title' => $this->title,
            'address' => $this->address,
            'type' => $this->type,
            'group' => $this->group,
            'is_filter' => $this->is_filter,
            'value' => null,
        ];

        if (($value = $this->value()) !== null) {
            $array['value'] = $value;
        }

        return $array;
    }
}
