<?php declare(strict_types=1);

namespace App\Domain\Models;

use App\Domain\Casts\AddressUrl;
use App\Domain\Casts\Boolean;
use App\Domain\Casts\Catalog\Product\Dimension as ProductDimension;
use App\Domain\Casts\Catalog\Product\Type as ProductType;
use App\Domain\Casts\Catalog\Product\Tags as ProductTags;
use App\Domain\Casts\Catalog\Status;
use App\Domain\Casts\Json;
use App\Domain\Casts\Meta;
use App\Domain\Traits\FileTrait;
use DateTime;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

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
 * @property DateTime $date
 * @property array $meta
 * @property string $external_id
 * @property string $export
 * @property CatalogCategory $category
 */
class CatalogProduct extends Model
{
    use HasFactory;
    use HasUuids;
    use FileTrait;

    protected $table = 'catalog_product';
    protected $primaryKey = 'uuid';

    const CREATED_AT = 'date';
    const UPDATED_AT = 'date';

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
        'category_uuid' => 'string',
        'vendorcode' => 'string',
        'barcode' => 'string',
        'tax' => 'float',
        'priceFirst' => 'float',
        'price' => 'float',
        'priceWholesale' => 'float',
        'priceWholesaleFrom' => 'float',
        'discount' => 'float',
        'special' => Boolean::class,
        'dimension' => ProductDimension::class,
        'quantity' => 'float',
        'quantityMin' => 'float',
        'stock' => 'float',
        'country' => 'string',
        'manufacturer' => 'string',
        'tags' => ProductTags::class,
        'order' => 'int',
        'status' => Status::class,
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

    public function category(): HasOne
    {
        return $this->hasOne(CatalogCategory::class, 'uuid', 'category_uuid');
    }

    protected function discount(): Attribute
    {
        return Attribute::make(
            set: fn (float $value) => $value > 0 ? -$value : $value,
        );
    }

    public function priceCalculated($type = 'price'): float
    {
        $price = match ($type) {
            'price_first' => $this->priceFirst,
            'price' => $this->price,
            'price_wholesale' => $this->priceWholesale,
        };

        if ($this->discount < 0) {
            $price = max(0, $price + $this->discount);
        }
        if ($this->tax > 0) {
            $price += $price * ($this->tax / 100);
        }

        return $price;
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
        return $this->dimension['weight'] ?? 0;
    }

    public function weightWithClass(): string
    {
        return $this->weight() . ($this->dimension['weight_class'] ? ' ' . $this->dimension['weight_class'] : '');
    }
}
