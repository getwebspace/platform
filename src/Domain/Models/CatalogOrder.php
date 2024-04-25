<?php declare(strict_types=1);

namespace App\Domain\Models;

use App\Domain\Casts\AddressUrl;
use App\Domain\Casts\Boolean;
use App\Domain\Casts\Catalog\Order\Delivery;
use App\Domain\Casts\Email;
use App\Domain\Casts\Catalog\Status as CatalogStatus;
use App\Domain\Casts\Json;
use App\Domain\Casts\Meta;
use App\Domain\Casts\Phone;
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
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * @property string $uuid
 * @property string $serial
 * @property string $user_uuid
 * @property string $status_uuid
 * @property string $payment_uuid
 * @property array $delivery
 * @property string $shipping
 * @property string $comment
 * @property string $phone
 * @property string $email
 * @property DateTime $date
 * @property string $system
 * @property string $external_id
 * @property string $export
 * @property Collection $products
 */
class CatalogOrder extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'catalog_order';
    protected $primaryKey = 'uuid';

    const CREATED_AT = null;
    const UPDATED_AT = null;

    protected $fillable = [
        'serial',
        'user_uuid',
        'status_uuid',
        'payment_uuid',
        'delivery',
        'shipping',
        'comment',
        'phone',
        'email',
        'date',
        'system',
        'external_id',
        'export',
    ];

    protected $guarded = [];

    protected $casts = [
        'serial' => 'string',
        'user_uuid' => 'string',
        'status_uuid' => 'string',
        'payment_uuid' => 'string',
        'delivery' => Delivery::class,
        'shipping' => 'datetime',
        'comment' => 'string',
        'phone' => Phone::class,
        'email' => Email::class,
        'date' => 'datetime',
        'system' => 'string',
        'external_id' => 'string',
        'export' => 'string',
    ];

    protected $attributes = [
        'serial' => '',
        'user_uuid' => null,
        'status_uuid' => null,
        'payment_uuid' => null,
        'delivery' => '{}',
        'shipping' => '',
        'comment' => '',
        'phone' => '',
        'email' => '',
        'date' => 'now',
        'system' => '',
        'external_id' => '',
        'export' => 'manual',
    ];

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(
            CatalogProduct::class,
            'catalog_order_product',
            'order_uuid',
            'product_uuid',
            'uuid',
            'uuid'
        )->withPivot(['price', 'price_type', 'count', 'discount', 'tax', 'tax_included']);
    }

    public function totalSum(): float
    {
        return $this->products->sum(fn (CatalogProduct $el) => $el->totalSum());
    }

    public function totalDiscount(): float
    {
        return $this->products->sum(fn (CatalogProduct $el) => $el->totalDiscount());
    }

    public function totalTax($precision = 0): float
    {
        return $this->products->sum(fn (CatalogProduct $el) => $el->totalTax($precision));
    }

    public function toArray(): array
    {
        return array_merge(
            parent::toArray(),
            [
                'products' => $this->products()->getResults()->keyBy('uuid')->map(function (CatalogProduct $item) {
                    return [
                        'title' => $item->title,
                        'address' => $item->address,
                        'price' => $item->pivot->price ?? 0,
                        'price_type' => $item->pivot->price_type ?? 'price',
                        'count' => $item->pivot->count ?? 1,
                        'discount' => $item->pivot->discount ?? 0,
                        'tax' => $item->pivot->tax ?? 0,
                    ];
                }),
            ],
        );
    }
}
