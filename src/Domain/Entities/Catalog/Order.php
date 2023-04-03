<?php declare(strict_types=1);

namespace App\Domain\Entities\Catalog;

use App\Domain\AbstractEntity;
use App\Domain\Entities\User;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: 'catalog_order')]
#[ORM\Index(name: 'catalog_order_serial_idx', columns: ['serial'])]
#[ORM\Index(name: 'catalog_order_status_idx', columns: ['status_uuid'])]
#[ORM\Entity(repositoryClass: 'App\Domain\Repository\Catalog\OrderRepository')]
class Order extends AbstractEntity
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'Ramsey\Uuid\Doctrine\UuidGenerator')]
    protected \Ramsey\Uuid\UuidInterface $uuid;

    public function getUuid(): \Ramsey\Uuid\UuidInterface
    {
        return $this->uuid;
    }

    #[ORM\Column(type: 'string', length: 12, options: ['default' => ''])]
    protected string $serial = '';

    /**
     * @param string $serial
     *
     * @return $this
     */
    public function setSerial($serial)
    {
        if (is_string($serial) && $this->checkStrLenMax($serial, 12) || is_int($serial)) {
            $this->serial = (string) $serial;
        }

        return $this;
    }

    public function getSerial(): string
    {
        return $this->serial;
    }

    #[ORM\Column(type: 'uuid', nullable: true, options: ['default' => null])]
    protected ?\Ramsey\Uuid\UuidInterface $user_uuid;

    #[ORM\ManyToOne(targetEntity: 'App\Domain\Entities\User')]
    #[ORM\JoinColumn(name: 'user_uuid', referencedColumnName: 'uuid')]
    protected ?User $user = null;

    /**
     * @return $this
     */
    public function setUser(?User $user)
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

    public function getUser(): ?User
    {
        return $this->user;
    }

    #[ORM\Column(type: 'json', options: ['default' => '{}'])]
    protected array $delivery = [
        'client' => '',
        'address' => '',
    ];

    /**
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

    public function getDelivery(): array
    {
        return $this->delivery;
    }

    #[ORM\Column(type: 'datetime', options: ['default' => 'CURRENT_TIMESTAMP'])]
    protected \DateTime $shipping;

    /**
     * @param mixed $timezone
     * @param mixed $date
     *
     * @throws \Exception
     *
     * @return $this
     */
    public function setShipping($date, $timezone = 'UTC')
    {
        $this->shipping = $this->getDateTimeByValue($date, $timezone);

        return $this;
    }

    /**
     * @return \DateTime
     */
    public function getShipping()
    {
        return $this->shipping;
    }

    #[ORM\Column(type: 'string', length: 500, options: ['default' => ''])]
    protected string $comment;

    /**
     * @return $this
     */
    public function setComment(string $comment)
    {
        if ($this->checkStrLenMax($comment, 500)) {
            $this->comment = $comment;
        }

        return $this;
    }

    public function getComment(): string
    {
        return $this->comment;
    }

    #[ORM\Column(type: 'string', length: 25, options: ['default' => ''])]
    protected string $phone = '';

    /**
     * @throws \App\Domain\Service\Catalog\Exception\WrongPhoneValueException
     *
     * @return $this
     */
    public function setPhone(string $phone = null)
    {
        if ($phone) {
            try {
                if ($this->checkStrLenMax($phone, 25) && $this->checkPhoneByValue($phone)) {
                    $this->phone = $phone;
                }
            } catch (\RuntimeException $e) {
                throw new \App\Domain\Service\Catalog\Exception\WrongPhoneValueException();
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

    #[ORM\Column(type: 'string', length: 120, options: ['default' => ''])]
    protected string $email = '';

    /**
     * @throws \App\Domain\Service\Catalog\Exception\WrongEmailValueException
     *
     * @return $this
     */
    public function setEmail(string $email = null)
    {
        if ($email) {
            try {
                if ($this->checkStrLenMax($email, 120) && $this->checkEmailByValue($email)) {
                    $this->email = $email;
                }
            } catch (\RuntimeException $e) {
                throw new \App\Domain\Service\Catalog\Exception\WrongEmailValueException();
            }
        } else {
            $this->email = '';
        }

        return $this;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    /**
     * @var array
     */
    #[ORM\OneToMany(targetEntity: 'App\Domain\Entities\Catalog\OrderProduct', mappedBy: 'order', orphanRemoval: true)]
    protected $products = [];

    public function hasProducts()
    {
        return count($this->products);
    }

    public function addProduct(OrderProduct $product)
    {
        $this->products[] = $product;

        return $this;
    }

    public function getProducts($raw = false)
    {
        return $raw ? $this->products : collect($this->products);
    }

    public function getTotalPrice(): float
    {
        return $this->getProducts()->sum(fn ($el) => $el->getSum());
    }

    #[ORM\Column(type: 'uuid', nullable: true, options: ['default' => null])]
    protected ?\Ramsey\Uuid\UuidInterface $status_uuid;

    #[ORM\ManyToOne(targetEntity: 'App\Domain\Entities\Catalog\OrderStatus')]
    #[ORM\JoinColumn(name: 'status_uuid', referencedColumnName: 'uuid')]
    protected ?OrderStatus $status = null;

    /**
     * @return $this
     */
    public function setStatus(?OrderStatus $status)
    {
        if (is_a($status, OrderStatus::class)) {
            $this->status_uuid = $status->getUuid();
            $this->status = $status;
        } else {
            $this->status_uuid = null;
            $this->status = null;
        }

        return $this;
    }

    public function getStatus(): ?OrderStatus
    {
        return $this->status;
    }

    #[ORM\Column(type: 'datetime', options: ['default' => 'CURRENT_TIMESTAMP'])]
    protected \DateTime $date;

    /**
     * @param mixed $timezone
     * @param mixed $date
     *
     * @throws \Exception
     *
     * @return $this
     */
    public function setDate($date, $timezone = 'UTC')
    {
        $this->date = $this->getDateTimeByValue($date, $timezone);

        return $this;
    }

    /**
     * @return \DateTime
     */
    public function getDate()
    {
        return $this->date;
    }

    #[ORM\Column(type: 'string', length: 255, options: ['default' => ''])]
    protected string $external_id = '';

    /**
     * @return $this
     */
    public function setExternalId(string $external_id)
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

    #[ORM\Column(type: 'string', length: 50, options: ['default' => 'manual'])]
    protected string $export = 'manual';

    /**
     * @return $this
     */
    public function setExport(string $export)
    {
        $this->export = $export;

        return $this;
    }

    public function getExport(): string
    {
        return $this->export;
    }

    #[ORM\Column(type: 'string', length: 500, options: ['default' => ''])]
    protected string $system = '';

    /**
     * @return $this
     */
    public function setSystem(string $system)
    {
        $this->system = $system;

        return $this;
    }

    public function getSystem(): string
    {
        return $this->system;
    }

    /**
     * Return model as array
     */
    public function toArray(): array
    {
        $email = $this->email;
        $phone = $this->phone;
        $delivery = $this->delivery;

        if ($this->user_uuid !== null) {
            $email = $this->user->getEmail();
            $phone = $this->user->getPhone();
            $delivery = [
                'client' => $this->user->getName(),
                'address' => $this->user->getAddress(),
            ];
        }

        return array_serialize([
            'uuid' => $this->uuid,
            'serial' => $this->serial,
            'user' => $this->user_uuid ?: \Ramsey\Uuid\Uuid::NIL,
            'delivery' => $delivery,
            'shipping' => $this->shipping,
            'comment' => $this->comment,
            'phone' => $phone,
            'email' => $email,
            'products' => $this->getProducts(),
            'status' => $this->status,
            'date' => $this->date,
            'external_id' => $this->external_id,
            'export' => $this->export,
            'system' => $this->system,
        ]);
    }
}
