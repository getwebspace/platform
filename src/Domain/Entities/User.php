<?php declare(strict_types=1);

namespace App\Domain\Entities;

use App\Domain\AbstractEntity;
use App\Domain\Entities\User\Group as UserGroup;
use App\Domain\Entities\User\Integration as UserIntegration;
use App\Domain\Entities\User\Session as UserSession;
use App\Domain\Traits\FileTrait;
use DateTime;
use Doctrine\ORM\Mapping as ORM;
use Illuminate\Support\Collection;
use Ramsey\Uuid\UuidInterface as Uuid;
use RuntimeException;

/**
 * @ORM\Entity(repositoryClass="App\Domain\Repository\UserRepository")
 * @ORM\Table(name="user")
 */
class User extends AbstractEntity
{
    use FileTrait;

    /**
     * @ORM\Id
     * @ORM\Column(type="uuid")
     * @ORM\GeneratedValue(strategy="CUSTOM")
     * @ORM\CustomIdGenerator(class="Ramsey\Uuid\Doctrine\UuidGenerator")
     */
    protected \Ramsey\Uuid\UuidInterface $uuid;

    public function getUuid(): \Ramsey\Uuid\UuidInterface
    {
        return $this->uuid;
    }

    /**
     * @ORM\Column(type="string", length=50, options={"default": ""})
     */
    protected string $username = '';

    /**
     * @return $this
     */
    public function setUsername(string $username): self
    {
        if ($this->checkStrLenMax($username, 50)) {
            $this->username = $username;
        }

        return $this;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    /**
     * @ORM\Column(type="string", length=120, unique=true, options={"default": ""})
     */
    protected string $email = '';

    /**
     * @throws \App\Domain\Service\User\Exception\WrongEmailValueException
     *
     * @return $this
     */
    public function setEmail(string $email): self
    {
        try {
            if ($this->checkStrLenMax($email, 120) && $this->checkEmailByValue($email)) {
                $this->email = $email;
            }
        } catch (RuntimeException $e) {
            throw new \App\Domain\Service\User\Exception\WrongEmailValueException();
        }

        return $this;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    /**
     * Gravatar
     */
    public function avatar(int $size = 40): string
    {
        if ($this->hasFiles()) {
            return $this->getFiles()->first()->getPublicPath();
        }

        return 'https://www.gravatar.com/avatar/' . md5(mb_strtolower(trim($this->email))) . '?d=identicon&s=' . $size;
    }

    /**
     * @ORM\Column(type="string", length=25, options={"default": ""})
     */
    protected string $phone = '';

    /**
     * @throws \App\Domain\Service\User\Exception\WrongPhoneValueException
     *
     * @return $this
     */
    public function setPhone(string $phone = null): self
    {
        if ($phone) {
            try {
                if ($this->checkStrLenMax($phone, 25) && $this->checkPhoneByValue($phone)) {
                    $this->phone = $phone;
                }
            } catch (RuntimeException $e) {
                throw new \App\Domain\Service\User\Exception\WrongPhoneValueException();
            }
        } else {
            $this->phone = '';
        }

        return $this;
    }

    public function getPhone(): string
    {
        return $this->phone;
    }

    /**
     * @ORM\Column(type="string", length=140, options={"default": ""})
     */
    protected string $password = '';

    /**
     * @return $this
     */
    public function setPassword(string $password): self
    {
        if ($password && $this->checkStrLenMax($password, 140)) {
            $this->password = $this->getPasswordHashByValue($password);
        }

        return $this;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    /**
     * @ORM\Column(type="string", length=50, options={"default": ""})
     */
    protected string $firstname = '';

    /**
     * @return $this
     */
    public function setFirstname(string $firstname): self
    {
        if ($this->checkStrLenMax($firstname, 50)) {
            $this->firstname = $firstname;
        }

        return $this;
    }

    public function getFirstname(): string
    {
        return $this->firstname;
    }

    /**
     * @ORM\Column(type="string", length=50, options={"default": ""})
     */
    protected string $lastname = '';

    /**
     * @return $this
     */
    public function setLastname(string $lastname): self
    {
        if ($this->checkStrLenMax($lastname, 50)) {
            $this->lastname = $lastname;
        }

        return $this;
    }

    public function getLastname(): string
    {
        return $this->lastname;
    }

    /**
     * @ORM\Column(type="string", length=50, options={"default": ""})
     */
    protected string $patronymic = '';

    /**
     * @return $this
     */
    public function setPatronymic(string $patronymic): self
    {
        if ($this->checkStrLenMax($patronymic, 50)) {
            $this->patronymic = $patronymic;
        }

        return $this;
    }

    public function getPatronymic(): string
    {
        return $this->patronymic;
    }

    public function getName(string $type = 'full'): string
    {
        if ($this->lastname || $this->patronymic || $this->firstname) {
            switch ($type) {
                case 'full':
                    return trim(implode(' ', [$this->lastname, $this->firstname, $this->patronymic]));

                case 'name':
                    return trim(implode(' ', [$this->lastname, $this->firstname]));

                case 'initials':
                    return trim(
                        implode(' ', [
                            $this->lastname ? mb_substr($this->lastname, 0, 1) . '.' : '',
                            $this->patronymic ? mb_substr($this->patronymic, 0, 1) . '.' : '',
                            $this->firstname ?: '',
                        ])
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

        return '';
    }

    /**
     * @ORM\Column(type="date", nullable=true)
     */
    protected ?DateTime $birthdate = null;

    /**
     * @param $birthdate
     * @param mixed $timezone
     *
     * @throws \Exception
     *
     * @return $this
     */
    public function setBirthdate($birthdate)
    {
        $this->birthdate = $this->getDateByValue($birthdate);

        return $this;
    }

    /**
     * @return ?DateTime
     */
    public function getBirthdate(): ?DateTime
    {
        return $this->birthdate;
    }

    /**
     * @ORM\Column(type="string", length=25, options={"default": ""})
     */
    protected string $gender = '';

    /**
     * @return $this
     */
    public function setGender(string $gender): self
    {
        if ($this->checkStrLenMax($gender, 25)) {
            $this->gender = $gender;
        }

        return $this;
    }

    public function getGender(): string
    {
        return $this->gender;
    }

    /**
     * @ORM\Column(type="string", length=500, options={"default": ""})
     */
    protected string $address = '';

    /**
     * @return $this
     */
    public function setAddress(string $address): self
    {
        if ($this->checkStrLenMax($address, 500)) {
            $this->address = $address;
        }

        return $this;
    }

    public function getAddress(): string
    {
        return $this->address;
    }

    /**
     * @ORM\Column(type="string", length=250, options={"default": ""})
     */
    protected string $additional = '';

    /**
     * @return $this
     */
    public function setAdditional(string $additional): self
    {
        if ($this->checkStrLenMax($additional, 250)) {
            $this->additional = $additional;
        }

        return $this;
    }

    public function getAdditional(): string
    {
        return $this->additional;
    }

    /**
     * @ORM\Column(type="boolean", options={"default": true})
     */
    protected bool $allow_mail = true;

    /**
     * @param mixed $allow_mail
     *
     * @return $this
     */
    public function setAllowMail($allow_mail): self
    {
        $this->allow_mail = $this->getBooleanByValue($allow_mail);

        return $this;
    }

    public function getAllowMail(): bool
    {
        return $this->allow_mail;
    }

    /**
     * @ORM\Column(type="array")
     */
    protected array $company = [
        'title' => '',
        'position' => '',
    ];

    public function setCompany(array $data): self
    {
        $default = [
            'title' => '',
            'position' => '',
        ];
        $data = array_merge($default, $data);

        $this->company = [
            'title' => $data['title'],
            'position' => $data['position'],
        ];

        return $this;
    }

    public function getCompany(): array
    {
        return $this->company;
    }

    /**
     * @ORM\Column(type="array")
     */
    protected array $legal = [
        'code' => '',
        'number' => '',
    ];

    public function setLegal(array $data): self
    {
        $default = [
            'code' => '',
            'number' => '',
        ];
        $data = array_merge($default, $data);

        $this->legal = [
            'code' => $data['code'],
            'number' => $data['number'],
        ];

        return $this;
    }

    public function getLegal(): array
    {
        return $this->legal;
    }

    /**
     * @ORM\Column(type="array")
     */
    protected array $messenger = [
        'skype' => '',
        'telegram' => '',
        'whatsapp' => '',
        'viber' => '',
        'facebook' => '',
        'instagram' => '',
        'signal' => '',
    ];

    public function setMessanger(array $data): self
    {
        $default = [
            'skype' => '',
            'telegram' => '',
            'whatsapp' => '',
            'viber' => '',
            'facebook' => '',
            'instagram' => '',
            'signal' => '',
        ];
        $data = array_merge($default, $data);

        $this->messenger = [
            'skype' => $data['skype'],
            'telegram' => $data['telegram'],
            'whatsapp' => $data['whatsapp'],
            'viber' => $data['viber'],
            'facebook' => $data['facebook'],
            'instagram' => $data['instagram'],
            'signal' => $data['signal'],
        ];

        return $this;
    }

    public function getMessanger(): array
    {
        return $this->messenger;
    }

    /**
     * @see \App\Domain\Types\UserStatusType::LIST
     * @ORM\Column(type="UserStatusType", options={"default": \App\Domain\Types\UserStatusType::STATUS_WORK})
     */
    protected string $status = \App\Domain\Types\UserStatusType::STATUS_WORK;

    /**
     * @return $this
     */
    public function setStatus(string $status): self
    {
        if (in_array($status, \App\Domain\Types\UserStatusType::LIST, true)) {
            $this->status = $status;
        }

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    /**
     * @ORM\Column(type="uuid", nullable=true, options={"default": NULL})
     */
    protected ?\Ramsey\Uuid\UuidInterface $group_uuid;

    /**
     * @ORM\ManyToOne(targetEntity="App\Domain\Entities\User\Group")
     * @ORM\JoinColumn(name="group_uuid", referencedColumnName="uuid")
     */
    protected ?UserGroup $group = null;

    /**
     * @param string|UserGroup $group
     */
    public function setGroup($group): self
    {
        if (is_a($group, UserGroup::class)) {
            $this->group_uuid = $group->getUuid();
            $this->group = $group;
        } else {
            $this->group_uuid = null;
            $this->group = null;
        }

        return $this;
    }

    public function getGroup(): ?UserGroup
    {
        return $this->group;
    }

    /**
     * @ORM\Column(type="datetime", options={"default": "CURRENT_TIMESTAMP"})
     */
    protected DateTime $register;

    /**
     * @param $register
     * @param mixed $timezone
     *
     * @throws \Exception
     *
     * @return $this
     */
    public function setRegister($register, $timezone = 'UTC')
    {
        $this->register = $this->getDateTimeByValue($register, $timezone);

        return $this;
    }

    /**
     * @return DateTime
     */
    public function getRegister()
    {
        return $this->register;
    }

    /**
     * @ORM\Column(name="`change`", type="datetime", options={"default": "CURRENT_TIMESTAMP"})
     */
    protected DateTime $change;

    /**
     * @param $change
     * @param mixed $timezone
     *
     * @throws \Exception
     *
     * @return $this
     */
    public function setChange($change, $timezone = 'UTC')
    {
        $this->change = $this->getDateTimeByValue($change, $timezone);

        return $this;
    }

    /**
     * @return DateTime
     */
    public function getChange()
    {
        return $this->change;
    }

    /**
     * @ORM\Column(type="string", length=100, options={"default": ""})
     */
    protected string $website = '';

    /**
     * @return $this
     */
    public function setWebsite(string $url): self
    {
        if ($this->checkStrLenMax($url, 100)) {
            $this->website = $url;
        }

        return $this;
    }

    public function getWebsite(): string
    {
        return $this->website;
    }

    /**
     * @ORM\Column(type="string", length=500, options={"default": ""})
     */
    protected string $source = '';

    /**
     * @return $this
     */
    public function setSource(string $text): self
    {
        if ($this->checkStrLenMax($text, 500)) {
            $this->source = $text;
        }

        return $this;
    }

    public function getSource(): string
    {
        return $this->source;
    }

    /**
     * @ORM\Column(type="string", length=12, options={"default": ""})
     */
    protected string $auth_code = '';

    /**
     * @return $this
     */
    public function setAuthCode(string $code): self
    {
        if ($this->checkStrLenMax($code, 12)) {
            $this->auth_code = $code;
        }

        return $this;
    }

    public function getAuthCode(): string
    {
        return $this->auth_code;
    }

    /**
     * @ORM\Column(type="string", length=255, options={"default": ""})
     */
    protected string $external_id = '';

    /**
     * @return $this
     */
    public function setExternalId(string $external_id): self
    {
        if ($this->checkStrLenMax($external_id, 255)) {
            $this->external_id = $external_id;
        }

        return $this;
    }

    public function getExternalId(): string
    {
        return $this->external_id;
    }

    /**
     * @ORM\Column(type="array", options={"default": "a:0:{}"})
     */
    protected array $token = [];

    /**
     * @return $this
     */
    public function setToken(array $token): self
    {
        $this->token = $token;

        return $this;
    }

    /**
     * @param mixed  $data
     *
     * @return $this
     */
    public function changeToken(string $name, $data): self
    {
        $this->token[$name] = $data;

        return $this;
    }

    /**
     * @return null|array|mixed
     */
    public function getToken(?string $name = null)
    {
        if ($name) {
            return $this->token[$name] ?? null;
        }

        return $this->token;
    }

    /**
     * @ORM\OneToMany(targetEntity="App\Domain\Entities\User\Integration", mappedBy="user", orphanRemoval=true)
     */
    protected $integrations = [];

    /**
     * @return $this
     */
    public function addIntegration(UserIntegration $integration): self
    {
        $this->integrations[] = $integration;
        $integration->setUser($this);

        return $this;
    }

    /**
     * @param mixed $raw
     *
     * @return array|Collection
     */
    public function getIntegrations($raw = false)
    {
        return $raw ? $this->integrations : collect($this->integrations);
    }

    /**
     * @ORM\OneToOne(targetEntity="App\Domain\Entities\User\Session", mappedBy="user", orphanRemoval=true)
     */
    protected ?UserSession $session = null;

    /**
     * @return $this
     */
    public function setSession(UserSession $session): self
    {
        $this->session = $session;
        $this->session->setUser($this);

        return $this;
    }

    public function getSession(): ?UserSession
    {
        return $this->session;
    }

    /**
     * @var array
     * @ORM\OneToMany(targetEntity="\App\Domain\Entities\File\UserFileRelation", mappedBy="user", orphanRemoval=true)
     * @ORM\OrderBy({"order": "ASC"})
     */
    protected $files = [];

    /**
     * @var array
     * @ORM\OneToMany(targetEntity="\App\Domain\Entities\Publication", mappedBy="user", orphanRemoval=true)
     * @ORM\OrderBy({"date": "ASC"})
     */
    protected $publications = [];

    /**
     * @var array
     * @ORM\OneToMany(targetEntity="\App\Domain\Entities\Catalog\Order", mappedBy="user", orphanRemoval=true)
     * @ORM\OrderBy({"date": "ASC"})
     */
    protected $orders = [];

    /**
     * Return model as array
     */
    public function toArray(): array
    {
        $buf = parent::toArray();

        if ($this->session) {
            $buf['session'] = $this->session->toArray();
        }

        return $buf;
    }
}
