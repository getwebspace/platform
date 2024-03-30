<?php declare(strict_types=1);

namespace App\Domain\Models;

use App\Domain\Casts\AddressUrl;
use App\Domain\Casts\Boolean;
use App\Domain\Casts\Email;
use App\Domain\Casts\Task\Status as TaskStatus;
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
 * @property string $title
 * @property string $action
 * @property float $progress
 * @property string $status
 * @property array $params
 * @property string $output
 * @property DateTime $date
 */
class Task extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'task';
    protected $primaryKey = 'uuid';

    const CREATED_AT = 'date';
    const UPDATED_AT = null;

    protected $fillable = [
        'title',
        'action',
        'progress',
        'status',
        'params',
        'output',
        'date',
    ];

    protected $guarded = [];

    protected $casts = [
        'title' => 'string',
        'action' => 'string',
        'progress' => 'float',
        'status' => TaskStatus::class,
        'params' => Json::class,
        'output' => 'string',
        'date' => 'datetime',
    ];

    protected $attributes = [
        'title' => '',
        'action' => '',
        'progress' => .00,
        'status' => \App\Domain\Casts\Task\Status::QUEUE,
        'params' => '{}',
        'output' => '',
        'date' => 'now',
    ];
}
