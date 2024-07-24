<?php declare(strict_types=1);

namespace App\Domain\Models;

use App\Domain\Casts\Boolean;
use App\Domain\Casts\Email;
use App\Domain\Casts\Json;
use App\Domain\Casts\Phone;
use App\Domain\Casts\User\Company as UserCompany;
use App\Domain\Casts\User\Legal as UserLegal;
use App\Domain\Casts\User\Messenger as UserMessenger;
use App\Domain\Casts\User\Password;
use App\Domain\Casts\User\Status as UserStatus;
use App\Domain\Casts\Uuid;
use App\Domain\Traits\HasFiles;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;

/**
 * @property string $uuid
 * @property string $email
 * @property string $phone
 * @property string $username
 * @property string $firstname
 * @property string $lastname
 * @property string $patronymic
 * @property \DateTime $birthdate
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
 * @property array $loyalty
 * @property \DateTime $register
 * @property \DateTime $change
 * @property string $language
 * @property string $additional
 * @property string $group_uuid
 * @property bool $is_allow_mail
 * @property string $password
 * @property string $external_id
 * @property UserStatus $status
 * @property UserGroup $group
 * @property Collection<UserToken> $tokens
 * @property Collection<UserIntegration> $integrations
 * @property Collection<CatalogOrder> $orders
 */
class User extends Model
{
    use HasUuids;
    use HasFiles;

    protected $table = 'user';

    protected $primaryKey = 'uuid';

    public const CREATED_AT = 'register';
    public const UPDATED_AT = 'change';

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
        'loyalty',
        'register',
        'change',
        'language',
        'additional',
        'status',
        'group_uuid',
        'is_allow_mail',
        'password',
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
        'company' => UserCompany::class,
        'legal' => UserLegal::class,
        'messenger' => UserMessenger::class,
        'website' => 'string',
        'source' => 'string',
        'loyalty' => Json::class,
        'register' => 'datetime',
        'change' => 'datetime',
        'language' => 'string',
        'additional' => 'string',
        'status' => UserStatus::class,
        'group_uuid' => Uuid::class,
        'is_allow_mail' => Boolean::class,
        'password' => Password::class,
        'external_id' => 'string',
    ];

    protected $attributes = [
        'email' => '',
        'phone' => '',
        'username' => '',
        'firstname' => '',
        'lastname' => '',
        'patronymic' => '',
        'birthdate' => null,
        'gender' => '',
        'country' => '',
        'city' => '',
        'address' => '',
        'postcode' => '',
        'company' => '{}',
        'legal' => '{}',
        'messenger' => '{}',
        'website' => '',
        'source' => '',
        'loyalty' => '[]',
        'register' => 'now',
        'change' => 'now',
        'language' => '',
        'additional' => '',
        'status' => \App\Domain\Casts\User\Status::WORK,
        'group_uuid' => null,
        'is_allow_mail' => true,
        'password' => '',
        'external_id' => '',
    ];

    public function group(): HasOne
    {
        return $this->hasOne(UserGroup::class, 'uuid', 'group_uuid');
    }

    public function tokens(): HasMany
    {
        return $this->hasMany(UserToken::class, 'user_uuid', 'uuid');
    }

    public function integrations(): HasMany
    {
        return $this->hasMany(UserIntegration::class, 'user_uuid', 'uuid');
    }

    public function orders($limit = 10): HasMany
    {
        return $this->hasMany(CatalogOrder::class, 'user_uuid', 'uuid')
            ->orderBy('serial', 'desc')
            ->limit($limit);
    }

    public function name(string $type = 'full'): string
    {
        if ($this->lastname || $this->patronymic || $this->firstname) {
            switch ($type) {
                case 'full':
                    return trim(implode(' ', [$this->lastname, $this->firstname, $this->patronymic]));

                case 'name':
                    return trim(implode(' ', [$this->lastname, $this->firstname]));

                case 'initials':
                    return trim(
                        implode(' ', array_filter(
                            [
                                $this->lastname ? mb_substr($this->lastname, 0, 1) . '.' : '',
                                $this->patronymic ? mb_substr($this->patronymic, 0, 1) . '.' : '',
                                $this->firstname ?: '',
                            ],
                            fn ($el) => (bool) $el
                        ))
                    );

                case 'short':
                    return trim(
                        implode(' ', [
                            $this->lastname ? mb_substr($this->lastname, 0, 1) . '.' : '',
                            $this->firstname,
                        ])
                    );
            }
        }

        if ($this->username) {
            return $this->username;
        }

        if ($this->email) {
            return explode('@', $this->email)[0];
        }

        return '';
    }

    public function avatar(int $size = 64, string $background = '0D8ABC', string $color = '000000'): string
    {
        static $path;

        if (!$path) {
            if ($this->images->count()) {
                /** @var File $file */
                $file = $this->images->first();

                $path = $file->public_path('small');
            } else {
                $path = "https://ui-avatars.com/api/?name={$this->name('name')}&size={$size}?background={$background}&color={$color}";
            }
        }

        return $path;
    }

    public function toArray(): array
    {
        return array_merge(
            parent::toArray(),
            [
                'name' => [
                    'full' => $this->name('full'),
                    'short' => $this->name('short'),
                ],
                'group' => $this->group,
                'avatar' => $this->avatar(),
                'files' => $this->files()->getResults()->all(),
            ],
        );
    }
}
