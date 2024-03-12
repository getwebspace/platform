<?php declare(strict_types=1);

namespace App\Domain\Models;

use App\Domain\Casts\Boolean;
use App\Domain\Casts\Email;
use App\Domain\Casts\Password;
use App\Domain\Casts\Phone;
use App\Domain\Casts\User\Company;
use App\Domain\Casts\User\Legal;
use App\Domain\Casts\User\Messenger;
use App\Domain\Enums\UserStatus;
use App\Domain\Traits\FileTrait;
use App\Domain\Traits\SecurityTrait;
use DateTime;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property string $uuid
 * @property string $email
 * @property string $phone
 * @property string $username
 * @property string $firstname
 * @property string $lastname
 * @property string $patronymic
 * @property DateTime $birthdate
 * @property string $gender
 * @property string $country
 * @property string $city
 * @property string $address
 * @property string $postcode
 * @property array $company
 * @property array $legal
 * @property array $messenger
 * @property string $website
 * @property string $source
 * @property DateTime $register
 * @property DateTime $change
 * @property string $language
 * @property string $additional
 * @property UserStatus $status
 * @property string $group_uuid
 * @property UserGroup $group
 * @property bool $allow_mail
 * @property string $password
 * @property UserToken $token
 * @property UserIntegration[] $integrations
 * @property string $auth_code
 * @property string $external_id
 */
class User extends Model
{
    use HasFactory;
    use HasUuids;
    use FileTrait;
    use SecurityTrait;

    protected $table = 'user';
    protected $primaryKey = 'uuid';

    const CREATED_AT = 'register';
    const UPDATED_AT = 'change';

    protected $fillable = [
        'email',
        'phone',
        'username',
        'firstname',
        'lastname',
        'patronymic',
        'birthdate',
        'gender',
        'country',
        'city',
        'address',
        'postcode',
        'company',
        'legal',
        'messenger',
        'website',
        'source',
        'register',
        'change',
        'language',
        'additional',
        'status',
        'group_uuid',
        'allow_mail',
        'password',
        'token',
        'auth_code',
        'external_id',
    ];

    protected $guarded = [];

    protected $casts = [
        'email' => Email::class,
        'phone' => Phone::class,
        'username' => 'string',
        'firstname' => 'string',
        'lastname' => 'string',
        'patronymic' => 'string',
        'birthdate' => 'date',
        'gender' => 'string',
        'country' => 'string',
        'city' => 'string',
        'address' => 'string',
        'postcode' => 'string',
        'company' => Company::class,
        'legal' => Legal::class,
        'messenger' => Messenger::class,
        'website' => 'string',
        'source' => 'string',
        'register' => 'datetime',
        'change' => 'datetime',
        'language' => 'string',
        'additional' => 'string',
        'status' => UserStatus::class,
        'group_uuid' => 'string',
        'allow_mail' => Boolean::class,
        'password' => Password::class,
        'token' => 'array',
        'auth_code' => 'string',
        'external_id' => 'string',
    ];

    protected $attributes = [
        'status' => UserStatus::WORK,
    ];

    public function group(): HasOne
    {
        return $this->hasOne(UserGroup::class, 'uuid', 'group_uuid');
    }

    public function token(): HasOne
    {
        return $this->hasOne(UserToken::class, 'uuid', 'user_uuid');
    }

    public function integrations(): HasMany
    {
        return $this->hasMany(UserIntegration::class, 'uuid', 'user_uuid');
    }

    public function avatar(int $size = 40): string
    {
        if ($this->hasFiles()) {
            /** @var File $file */
            $file = $this->files->first();

            return $file->public_path('small');
        }

        return 'https://www.gravatar.com/avatar/' . md5(mb_strtolower(trim($this->email))) . '?d=identicon&s=' . $size;
    }
}
