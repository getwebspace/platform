<?php declare(strict_types=1);

namespace App\Domain\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string         $key
 * @property string         $schedule
 * @property null|\DateTime $last_run
 * @property null|\DateTime $last_success
 * @property string         $status    ok|fail|running|skipped
 * @property null|string    $error
 * @property float          $duration  seconds
 * @property int            $failures  in a row
 * @property bool           $enabled
 * @property bool           $run_now   asked from the panel, the next pass of cron runs the job
 */
class Schedule extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    protected $table = 'schedule';

    protected $primaryKey = 'key';

    protected $keyType = 'string';

    protected $fillable = [
        'key',
        'schedule',
        'last_run',
        'last_success',
        'status',
        'error',
        'duration',
        'failures',
        'enabled',
        'run_now',
    ];

    protected $casts = [
        'key' => 'string',
        'schedule' => 'string',
        'last_run' => 'datetime',
        'last_success' => 'datetime',
        'status' => 'string',
        'error' => 'string',
        'duration' => 'float',
        'failures' => 'integer',
        'enabled' => 'boolean',
        'run_now' => 'boolean',
    ];

    protected $attributes = [
        'schedule' => '',
        'status' => '',
        'duration' => 0,
        'failures' => 0,
        'enabled' => true,
        'run_now' => false,
    ];
}
