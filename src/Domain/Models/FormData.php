<?php declare(strict_types=1);

namespace App\Domain\Models;

use App\Domain\Casts\Json;
use App\Domain\Casts\Uuid;
use App\Domain\Traits\HasFiles;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property string $uuid
 * @property string $form_uuid
 * @property array $data
 * @property string $message
 * @property \DateTime $date
 * @property Form $form
 */
class FormData extends Model
{
    use HasUuids;
    use HasFiles;

    protected $table = 'form_data';

    protected $primaryKey = 'uuid';

    public const CREATED_AT = 'date';
    public const UPDATED_AT = 'date';

    protected $fillable = [
        'form_uuid',
        'data',
        'message',
        'date',
    ];

    protected $guarded = [];

    protected $casts = [
        'form_uuid' => Uuid::class,
        'data' => Json::class,
        'message' => 'string',
        'date' => 'datetime',
    ];

    protected $attributes = [
        'form_uuid' => '',
        'data' => '{}',
        'message' => '',
        'date' => 'now',
    ];

    public function form(): HasOne
    {
        return $this->hasOne(Form::class, 'uuid', 'form_uuid');
    }
}
