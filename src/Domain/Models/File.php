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

class File extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'file';
    protected $primaryKey = 'uuid';

    const CREATED_AT = 'date';
    const UPDATED_AT = 'date';

    protected $fillable = [
        'name',
        'ext',
        'type',
        'size',
        'salt',
        'hash',
        'private',
        'date',
    ];

    protected $guarded = [];

    protected $casts = [
        'name' => 'string',
        'ext' => 'string',
        'type' => 'string',
        'size' => 'int',
        'salt' => 'string',
        'hash' => 'string',
        'private' => Boolean::class,
        'date' => 'datetime',
    ];

    protected $attributes = [
        'private' => false,
    ];
}
