<?php declare(strict_types=1);

namespace App\Domain\Models;

use App\Domain\Casts\Boolean;
use App\Domain\Casts\Json;
use App\Domain\Casts\User\Company;
use App\Domain\Casts\User\Legal;
use App\Domain\Casts\User\Messenger;
use App\Domain\Traits\FileTrait;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $uuid
 * @property string $title
 * @property string $description
 * @property array $access
 * @property User[] $users
 */
class UserGroup extends Model
{
    use HasFactory;
    use HasUuids;
    use FileTrait;

    protected $table = 'user_group';
    protected $primaryKey = 'uuid';

    const CREATED_AT = null;
    const UPDATED_AT = null;

    protected $fillable = [
        'title',
        'description',
        'access',
    ];

    protected $guarded = [];

    protected $casts = [
        'title' => 'string',
        'description' => 'string',
        'access' => Json::class
    ];

    protected $attributes = [
        'title' => '',
        'description' => '',
        'access' => '{}',
    ];

    protected $hidden = [
        'access',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'group_uuid', 'uuid');
    }
}
