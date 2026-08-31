<?php declare(strict_types=1);

namespace App\Domain\Models;

use App\Domain\Casts\AddressUrl;
use App\Domain\Casts\Boolean;
use App\Domain\Casts\Catalog\Product\Dimension as ProductDimension;
use App\Domain\Casts\Catalog\Product\Tags as ProductTags;
use App\Domain\Casts\Catalog\Product\Type as ProductType;
use App\Domain\Casts\Catalog\Status as CatalogStatus;
use App\Domain\Casts\Decimal;
use App\Domain\Casts\Meta;
use App\Domain\Casts\Uuid;
use App\Domain\Traits\HasFiles;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;

/**
 * @property string $uuid
 * @property string $title
 * @property string $description
 * @property string $extra
 * @property string $address
 * @property string $type
 * @property string $category_uuid
 * @property string $vendorcode
 * @property string $barcode
 * @property float $tax
 * @property bool $tax_included
 * @property float $priceFirst
 * @property float $price
 * @property float $priceWholesale
 * @property float $priceWholesaleFrom
 * @property float $discount
 * @property bool $special
 * @property array $dimension
 * @property float $quantity
 * @property float $quantityMin
 * @property float $stock
 * @property string $country
 * @property string $manufacturer
 * @property array $tags
 * @property int $order
 * @property string $status
 * @property \DateTime $date
 * @property array $meta
 * @property string $external_id
 * @property string $export
 * @property CatalogCategory $category
 * @property Collection<CatalogAttribute> $attributes
 * @property Collection<CatalogProduct> $relations
 */
class CatalogProduct extends Model
{
    use HasUuids;
    use HasFiles;

    protected $table = 'catalog_product';

    protected $primaryKey = 'uuid';

    public const CREATED_AT = 'date';
    public const UPDATED_AT = 'date';

    protected $fillable = [
        'title',
        'description',
        'extra',
        'address',
        'type',
        'category_uuid',
        'vendorcode',
        'barcode',
        'tax',
        'tax_included',
        'priceFirst',
        'price',
        'priceWholesale',
        'priceWholesaleFrom',
        'discount',
        'special',
        'dimension',
        'quantity',
        'quantityMin',
        'stock',
        'country',
        'manufacturer',
        'tags',
        'order',
        'status',
        'date',
        'meta',
        'external_id',
        'export',
    ];

    protected $guarded = [];

    protected $casts = [
        'title' => 'string',
        'description' => 'string',
        'extra' => 'string',
        'address' => AddressUrl::class,
        'type' => ProductType::class,
        'category_uuid' => Uuid::class,
        'vendorcode' => 'string',
        'barcode' => 'string',
        'tax' => Decimal::class,
        'tax_included' => Boolean::class,
        'priceFirst' => Decimal::class,
        'price' => Decimal::class,
        'priceWholesale' => Decimal::class,
        'priceWholesaleFrom' => Decimal::class,
        'discount' => Decimal::class,
        'special' => Boolean::class,
        'dimension' => ProductDimension::class,
        'quantity' => Decimal::class,
        'quantityMin' => Decimal::class,
        'stock' => Decimal::class,
        'country' => 'string',
        'manufacturer' => 'string',
        'tags' => ProductTags::class,
        'order' => 'int',
        'status' => CatalogStatus::class,
        'date' => 'datetime',
        'meta' => Meta::class,
        'external_id' => 'string',
        'export' => 'string',
    ];

    protected $attributes = [
        'title' => '',
        'description' => '',
        'extra' => '',
        'address' => '',
        'type' => \App\Domain\Casts\Catalog\Product\Type::PRODUCT,
        'category_uuid' => null,
        'vendorcode' => '',
        'barcode' => '',
        'tax' => 0.0,
        'tax_included' => false,
        'priceFirst' => 0.0,
        'price' => 0.0,
        'priceWholesale' => 0.0,
        'priceWholesaleFrom' => 0,
        'discount' => 0.0,
        'special' => false,
        'dimension' => '{}',
        'quantity' => 1.0,
        'quantityMin' => 1.0,
        'stock' => 0.0,
        'country' => '',
        'manufacturer' => '',
        'tags' => '{}',
        'order' => 1,
        'status' => \App\Domain\Casts\Catalog\Status::WORK,
        'date' => 'now',
        'meta' => '{}',
        'external_id' => '',
        'export' => 'manual',
    ];

    protected $hidden = [
        'priceFirst',

        // related products point back at each other, letting eloquent
        // serialise this relation on its own would recurse forever;
        // toArray() below adds a depth-limited `relations` key instead
        'relations',
    ];

    public function category(): HasOne
    {
        return $this->hasOne(CatalogCategory::class, 'uuid', 'category_uuid');
    }

    public function attributes(): BelongsToMany
    {
        return $this->belongsToMany(
            CatalogAttribute::class,
            'catalog_attribute_product',
            'product_uuid',
            'attribute_uuid',
            'uuid',
            'uuid'
        )->withPivot('value');
    }

    /**
     * Top-level reviews for this product (replies excluded)
     *
     * Read via getRelationValue('reviews') if eager-loading matters - see the
     * attributes()/relations() note in CLAUDE.md, the same collision risk
     * applies to any relation name here
     */
    public function reviews(): HasMany
    {
        return $this
            ->hasMany(Review::class, 'entity_uuid', 'uuid')
            ->where('entity_type', \App\Domain\Casts\Review\EntityType::CATALOG_PRODUCT)
            ->where('type', \App\Domain\Casts\Review\Type::REVIEW)
            ->whereNull('parent_uuid');
    }

    /**
     * Top-level questions for this product (replies excluded)
     */
    public function questions(): HasMany
    {
        return $this
            ->hasMany(Review::class, 'entity_uuid', 'uuid')
            ->where('entity_type', \App\Domain\Casts\Review\EntityType::CATALOG_PRODUCT)
            ->where('type', \App\Domain\Casts\Review\Type::QUESTION)
            ->whereNull('parent_uuid');
    }

    public function attribute($address, $default = null): mixed
    {
        try {
            return $this->attributes()->where('address', $address)->firstOrFail()->value();
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $default;
        }
    }

    public function relations(): BelongsToMany
    {
        return $this->belongsToMany(
            self::class,
            'catalog_product_related',
            'product_uuid',
            'related_uuid',
            'uuid',
            'uuid'
        )->withPivot('count')->where('status', CatalogStatus::WORK);
    }

    public function price(string $type = 'price', int $precision = 0): float
    {
        $price = match ($type) {
            'price_first' => $this->priceFirst,
            'price' => $this->price,
            'price_wholesale' => $this->priceWholesale,
        };

        if ($this->discount) {
            $price = max(0, $price + (-abs($this->discount)));
        }
        if (!$this->tax_included && $this->tax > 0) {
            $price += $price * ($this->tax / 100);
        }

        return round($price, $precision);
    }

    public function price_lowest(int $days = 30, int $precision = 0): ?float
    {
        $price = $this::query()
            ->from('catalog_order_product as cop')
            ->selectRaw('MIN(
                    CASE
                        WHEN cop.tax_included = false THEN (cop.price * (1 + cop.tax / 100) - cop.discount)
                        ELSE (cop.price - cop.discount)
                    END
                ) as lowest')
            ->leftJoin('catalog_order as co', 'cop.order_uuid', '=', 'co.uuid')
            ->where('cop.product_uuid', $this->uuid)
            ->where('co.date', '>=', datetime()->subDays($days))
            ->value('lowest');

        return $price ? round($price, $precision) : null;
    }

    public function tax(string $type = 'price', int $precision = 0): float
    {
        $price = match ($type) {
            'price_first' => $this->priceFirst,
            'price' => $this->price,
            'price_wholesale' => $this->priceWholesale,
        };

        if ($this->discount) {
            $price = max(0, $price + (-abs($this->discount)));
        }

        $tax = 0;

        if ($this->tax) {
            $taxRate = $this->tax / 100;

            if ($this->tax_included) {
                $tax = $price - ($price / (1 + $taxRate));
            } else {
                $tax = $price * $taxRate;
            }
        }

        return round($tax, $precision);
    }

    public function specification(): string
    {
        return implode('×', [
            $this->dimension['length'] ?? 0,
            $this->dimension['width'] ?? 0,
            $this->dimension['height'] ?? 0,
        ]);
    }

    public function specificationWithClass(): string
    {
        return $this->specification() . ($this->dimension['length_class'] ? ' ' . $this->dimension['length_class'] : '');
    }

    public function weight(): float
    {
        return floatval($this->dimension['weight'] ?? 0);
    }

    public function weightWithClass(): string
    {
        return $this->weight() . ($this->dimension['weight_class'] ? ' ' . $this->dimension['weight_class'] : '');
    }

    /**
     * Guards the nested level of `relations`
     *
     * Related products may point back at each other, and serialising them in
     * full would recurse until memory runs out
     */
    private static bool $serializingRelations = false;

    public function toArray(): array
    {
        $relations = [];

        if (!self::$serializingRelations) {
            self::$serializingRelations = true;

            try {
                $relations = $this->getRelationValue('relations')->keyBy('uuid')->map(function (CatalogProduct $item) {
                    return $item->toArray();
                });
            } finally {
                self::$serializingRelations = false;
            }
        }

        return array_merge(
            parent::toArray(),
            [
                'calculated' => [
                    'price' => $this->price('price'),
                    'price_wholesale' => $this->price('price_wholesale'),
                ],
                'category' => $this->category ? [
                    'uuid' => $this->category->uuid,
                    'title' => $this->category->title,
                    'address' => $this->category->address,
                ] : null,
                'attributes' => $this->getRelationValue('attributes')->keyBy('address'),
                'relations' => $relations,
                'files' => $this->files,
            ],
        );
    }

    // order product functions ...

    public function totalPrice(): float
    {
        $price = $this->pivot->price;

        if ($this->pivot->discount) {
            $price = max(0, $price + (-abs($this->pivot->discount)));
        }
        if (!$this->pivot->tax_included && $this->pivot->tax > 0) {
            $price += $price * ($this->pivot->tax / 100);
        }

        return $price;
    }

    public function totalCount(): float
    {
        return $this->pivot->count;
    }

    public function totalSum(): float
    {
        return $this->totalPrice() * $this->pivot->count;
    }

    public function totalDiscount(): float
    {
        return (-abs($this->pivot->discount)) * $this->pivot->count;
    }

    public function totalTax($precision = 0): float
    {
        $price = $this->pivot->price;

        if ($this->pivot->discount) {
            $price = max(0, $price + (-abs($this->pivot->discount)));
        }

        $tax = 0;

        if ($this->pivot->tax) {
            $taxRate = $this->pivot->tax / 100;

            if ($this->pivot->tax_included) {
                $tax = $price - ($price / (1 + $taxRate));
            } else {
                $tax = $price * $taxRate;
            }
        }

        return round($tax * $this->pivot->count, $precision);
    }
}
