<?php declare(strict_types=1);

namespace App\Domain\Models;

use App\Domain\Casts\AddressUrl;
use App\Domain\Casts\Boolean;
use App\Domain\Casts\Email;
use App\Domain\Casts\Reference\Type as ReferenceType;
use App\Domain\Casts\Json;
use App\Domain\Traits\FileTrait;
use DateTime;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $uuid
 * @property string $type
 * @property string $title
 * @property array $value
 * @property int $order
 * @property bool $status
 */
class Reference extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'reference';
    protected $primaryKey = 'uuid';

    const CREATED_AT = null;
    const UPDATED_AT = null;

    protected $fillable = [
        'type',
        'title',
        'value',
        'order',
        'status',
    ];

    protected $guarded = [];

    protected $casts = [
        'type' => ReferenceType::class,
        'title' => 'string',
        'value' => Json::class,
        'order' => 'int',
        'status' => Boolean::class,
    ];

    protected $attributes = [
        'value' => [],
        'order' => 1,
        'status' => true,
    ];
}
