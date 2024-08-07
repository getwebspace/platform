<?php declare(strict_types=1);

namespace App\Domain\Models;

use App\Domain\Casts\AddressUrl;
use App\Domain\Casts\Meta;
use App\Domain\Casts\Page\Type as PageType;
use App\Domain\Traits\HasFiles;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $uuid
 * @property string $title
 * @property string $address
 * @property \DateTime $date
 * @property string $content
 * @property string $type
 * @property array $meta
 * @property string $template
 */
class Page extends Model
{
    use HasUuids;
    use HasFiles;

    protected $table = 'page';

    protected $primaryKey = 'uuid';

    public const CREATED_AT = 'date';
    public const UPDATED_AT = 'date';

    protected $fillable = [
        'title',
        'address',
        'content',
        'type',
        'template',
        'meta',
        'date',
    ];

    protected $guarded = [];

    protected $casts = [
        'title' => 'string',
        'address' => AddressUrl::class,
        'content' => 'string',
        'type' => PageType::class,
        'template' => 'string',
        'meta' => Meta::class,
        'date' => 'datetime',
    ];

    protected $attributes = [
        'title' => '',
        'address' => '',
        'content' => '',
        'type' => \App\Domain\Casts\Page\Type::HTML,
        'template' => '',
        'meta' => '{}',
        'date' => 'now',
    ];

    public function toArray(): array
    {
        return array_merge(
            parent::toArray(),
            [
                'files' => $this->files,
            ],
        );
    }
}
