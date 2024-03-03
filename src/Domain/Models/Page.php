<?php declare(strict_types=1);

namespace App\Domain\Models;

use App\Domain\Casts\AddressUrl;
use App\Domain\Casts\Meta;
use App\Domain\Enums\PageType;
use App\Domain\Traits\FileTrait;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Page extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'page';
    protected $primaryKey = 'uuid';

    const CREATED_AT = 'date';
    const UPDATED_AT = 'date';

    protected $fillable = [
        'title',
        'address',
        'date',
        'content',
        'type',
        'meta',
        'template',
    ];

    protected $guarded = [];

    protected $casts = [
        'title' => 'string',
        'address' => AddressUrl::class,
        'date' => 'datetime',
        'content' => 'string',
        'type' => PageType::class,
        'meta' => Meta::class,
        'template' => 'string',
    ];

    protected $attributes = [
    ];
}
