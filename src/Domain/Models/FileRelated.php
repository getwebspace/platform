<?php declare(strict_types=1);

namespace App\Domain\Models;

use App\Domain\Casts\AddressUrl;
use App\Domain\Casts\Boolean;
use App\Domain\Casts\Meta;
use App\Domain\Enums\PageType;
use App\Domain\Traits\FileTrait;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $file_uuid
 * @property string $entity_uuid
 * @property int $order
 * @property string $comment
 * @property string $object_type
 */
class FileRelated extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'file_related';
    protected $primaryKey = 'uuid';

    const CREATED_AT = 'date';
    const UPDATED_AT = 'date';

    protected $fillable = [
        'file_uuid',
        'entity_uuid', // todo rename to model_uuid
        'order',
        'comment',
        'object_type', // todo remove
    ];

    protected $guarded = [];

    protected $casts = [
        'file_uuid' => 'string',
        'entity_uuid' => 'string',
        'order' => 'int',
        'comment' => 'string',
        'object_type' => 'string',
    ];

    protected $attributes = [
        'order' => 1,
        'comment' => '',
        'object_type' => '-',
    ];
}
