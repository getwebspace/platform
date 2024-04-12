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
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;

/**
 * @property string $uuid
 * @property string $title
 * @property string $address
 * @property string $type
 * @property string $group
 * @property boolean $is_filter
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

    public function value(): mixed
    {
        if ($this->pivot->value) {
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
}
