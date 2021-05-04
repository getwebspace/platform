<?php declare(strict_types=1);

namespace App\Domain\Entities\Catalog;

use App\Domain\AbstractEntity;
use App\Domain\Entities\User;
use DateTime;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;

/**
 * @ORM\Entity(repositoryClass="App\Domain\Repository\Catalog\OrderRepository")
 * @ORM\Table(name="catalog_order", indexes={
 *     @ORM\Index(name="catalog_order_status_idx", columns={"status"}),
 * })
 */
class Order extends AbstractEntity
{
    /**
     * @var Uuid
     * @ORM\Id
     * @ORM\Column(type="uuid")
     * @ORM\GeneratedValue(strategy="CUSTOM")
     * @ORM\CustomIdGenerator(class="Ramsey\Uuid\Doctrine\UuidGenerator")
     */
    protected Uuid $uuid;

    /**
     * @return Uuid
     */
    public function getUuid(): Uuid
    {
        return $this->uuid;
    }

    /**
     * @var string
     * @ORM\Column(type="string", length=7, options={"default": ""})
     */
    protected string $serial = '';

    /**
     * @param int|string $serial
     *
     * @return $this
     */
    public function setSerial($serial)
    {
        if (is_string($serial) && $this->checkStrLenMax($serial, 500) || is_int($serial)) {
            $this->serial = (string) $serial;
        }

        return $this;
    }

    /**
     * @return string
     */
    public function getSerial(): string
    {
        return $this->serial;
    }

    /**
     * @var null|Uuid
     * @ORM\Column(type="uuid", nullable=true, options={"default": null})
     */
    protected ?Uuid $user_uuid;

    /**
     * @var null|User
     * @ORM\ManyToOne(targetEntity="App\Domain\Entities\User")
     * @ORM\JoinColumn(name="user_uuid", referencedColumnName="uuid")
     */
    protected ?User $user = null;

    /**
     * @param string|User $user
     *
     * @return $this
     */
    public function setUser($user)
    {
        if (is_a($user, User::class)) {
            $this->user_uuid = $user->getUuid();
            $this->user = $user;
        } else {
            $this->user_uuid = null;
            $this->user = null;
        }

        return $this;
    }

    /**
     * @return null|User
     */
    public function getUser(): ?User
    {
        return $this->user;
    }

    /**
     * @var array
     * @ORM\Column(type="array")
     */
    protected array $delivery = [
        'client' => '',
        'address' => '',
    ];

    /**
     * @param array $data
     *
     * @return $this
     */
    public function setDelivery(array $data)
    {
        $default = [
            'client' => '',
            'address' => '',
        ];
        $data = array_merge($default, $data);

        $this->delivery = [
            'client' => $data['client'],
            'address' => $data['address'],
        ];

        return $this;
    }

    /**
     * @return array
     */
    public function getDelivery(): array
    {
        return $this->delivery;
    }

    /**
     * @var DateTime
     * @ORM\Column(type="datetime", options={"default": "CURRENT_TIMESTAMP"})
     */
    protected DateTime $shipping;

    /**
     * @param $date
     *
     * @throws \Exception
     *
     * @return $this
     */
    public function setShipping($date)
    {
        $this->shipping = $this->getDateTimeByValue($date);

        return $this;
    }

    /**
     * @return DateTime
     */
    public function getShipping()
    {
        return $this->shipping;
    }

    /**
     * @var string
     * @ORM\Column(type="string", options={"default": ""})
     */
    protected string $comment;

    /**
     * @param string $comment
     *
     * @return $this
     */
    public function setComment(string $comment)
    {
        if ($this->checkStrLenMax($comment, 500)) {
            $this->comment = $comment;
        }

        return $this;
    }

    /**
     * @return string
     */
    public function getComment(): string
    {
        return $this->comment;
    }

    /**
     * @ORM\Column(type="string", length=25, options={"default": ""})
     */
    protected string $phone = '';

    /**
     * @param null|string $phone
     *
     * @throws \App\Domain\Exceptions\WrongPhoneValueException
     *
     * @return $this
     */
    public function setPhone(string $phone = null)
    {
        if ($phone) {
            if ($this->checkStrLenMax($phone, 25) && $this->checkPhoneByValue($phone)) {
                $this->phone = $phone;
            }
        } else {
            $this->phone = '';
        }

        return $this;
    }

    /**
     * @return string
     */
    public function getPhone(): string
    {
        return $this->phone;
    }

    /**
     * @ORM\Column(type="string", length=120, options={"default": ""})
     */
    protected string $email = '';

    /**
     * @param null|string $email
     *
     * @throws \App\Domain\Exceptions\WrongEmailValueException
     *
     * @return $this
     */
    public function setEmail(string $email = null)
    {
        if ($email) {
            if ($this->checkStrLenMax($email, 120) && $this->checkEmailByValue($email)) {
                $this->email = $email;
            }
        } else {
            $this->email = '';
        }

        return $this;
    }

    /**
     * @return string
     */
    public function getEmail(): string
    {
        return $this->email;
    }

    /**
     * @var array
     * @ORM\Column(name="`list`", type="array")
     */
    protected array $list = [
        // 'uuid' => 'count',
    ];

    /**
     * @param array $list
     *
     * @return $this
     */
    public function setList(array $list)
    {
        $this->list = $list;

        return $this;
    }

    /**
     * @return array
     */
    public function getList(): array
    {
        return $this->list;
    }

    /**
     * @var string
     *
     * @see \App\Domain\Types\OrderStatusType::LIST
     * @ORM\Column(type="CatalogOrderStatusType", options={"default": \App\Domain\Types\Catalog\OrderStatusType::STATUS_NEW})
     */
    protected string $status = \App\Domain\Types\Catalog\OrderStatusType::STATUS_NEW;

    /**
     * @param string $status
     *
     * @return $this
     */
    public function setStatus(string $status)
    {
        if (in_array($status, \App\Domain\Types\Catalog\OrderStatusType::LIST, true)) {
            $this->status = $status;
        }

        return $this;
    }

    /**
     * @return string
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * @var DateTime
     * @ORM\Column(type="datetime", options={"default": "CURRENT_TIMESTAMP"})
     */
    protected DateTime $date;

    /**
     * @param $date
     *
     * @throws \Exception
     *
     * @return $this
     */
    public function setDate($date)
    {
        $this->date = $this->getDateTimeByValue($date);

        return $this;
    }

    /**
     * @return DateTime
     */
    public function getDate()
    {
        return $this->date;
    }

    /**
     * @ORM\Column(type="string", length=50, options={"default": ""})
     */
    protected string $external_id = '';

    /**
     * @param string $external_id
     *
     * @return $this
     */
    public function setExternalId(string $external_id)
    {
        if ($this->checkStrLenMax($external_id, 255)) {
            $this->external_id = $external_id;
        }

        return $this;
    }

    /**
     * @return string
     */
    public function getExternalId(): string
    {
        return $this->external_id;
    }

    /**
     * @ORM\Column(type="string", length=50, options={"default": "manual"})
     */
    protected string $export = 'manual';

    /**
     * @param string $export
     *
     * @return $this
     */
    public function setExport(string $export)
    {
        $this->export = $export;

        return $this;
    }

    /**
     * @return string
     */
    public function getExport(): string
    {
        return $this->export;
    }

    /**
     * @ORM\Column(type="string", length=500, options={"default": ""})
     */
    protected string $system = '';

    /**
     * @param string $system
     *
     * @return $this
     */
    public function setSystem(string $system)
    {
        $this->system = $system;

        return $this;
    }

    /**
     * @return string
     */
    public function getSystem(): string
    {
        return $this->system;
    }
}
