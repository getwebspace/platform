<?php declare(strict_types=1);

namespace App\Domain\Models;

use App\Domain\Casts\Boolean;
use App\Domain\Casts\Json;
use App\Domain\Casts\Reference\Type as ReferenceType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

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
    use HasUuids;

    protected $table = 'reference';

    protected $primaryKey = 'uuid';

    public const CREATED_AT = null;
    public const UPDATED_AT = null;

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
        'type' => '',
        'title' => '',
        'value' => '{}',
        'order' => 1,
        'status' => true,
    ];

    public function value(?string $key = null, mixed $default = null): mixed
    {
        if ($key) {
            return $this->value[$key] ?? $default;
        }

        return $this->value;
    }
}
