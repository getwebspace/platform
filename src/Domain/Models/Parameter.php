<?php declare(strict_types=1);

namespace App\Domain\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $name
 * @property string $value
 */
class Parameter extends Model
{
    protected $table = 'params';

    protected $primaryKey = 'name';

    protected $keyType = 'string';

    public $incrementing = false;

    public const CREATED_AT = null;
    public const UPDATED_AT = null;

    protected $fillable = [
        'name',
        'value',
    ];

    protected $casts = [
        'name' => 'string',
        'value' => 'string',
    ];

    protected $attributes = [
        'name' => '',
        'value' => '',
    ];
}
