<?php declare(strict_types=1);

namespace App\Domain\Entities\Catalog;

use App\Domain\AbstractEntity;
use App\Domain\Entities\Reference;
use App\Domain\Entities\User;
use Doctrine\ORM\Mapping as ORM;
use Illuminate\Support\Collection;

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

    public function setSerial(string|int $serial): self
    {
        if ((is_string($serial) && $this->checkStrLenMax($serial, 12)) || is_int($serial)) {
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

    public function setUser(?User $user): self
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

    public function setDelivery(array $data): self
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
     * @throws \Exception
     */
    public function setShipping(mixed $date, string $timezone = 'UTC'): self
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

    #[ORM\Column(type: 'string', length: 1000, options: ['default' => ''])]
    protected string $comment = '';

    public function setComment(string $comment): self
    {
        if ($this->checkStrLenMax($comment, 1000) && $this->validText($comment)) {
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
     */
    public function setPhone(string $phone = null): self
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
     */
    public function setEmail(string $email = null): self
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

    public function addProduct(OrderProduct $product): self
    {
        $this->products[] = $product;

        return $this;
    }

    public function getProducts(string $type = null): Collection
    {
        $products = collect($this->products)->sortBy('title');

        return $type === null ? $products : $products->where('type', $type);
    }

    public function getTotalPrice(): float
    {
        return $this->getProducts()->sum(fn ($el) => $el->getTotal());
    }

    public function getTotalPriceCalculated(): float
    {
        return $this->getProducts()->sum(fn ($el) => $el->getTotalCalculated());
    }

    public function getTotalDiscount(): float
    {
        return $this->getProducts()->sum(fn ($el) => $el->getDiscount() * $el->getCount());
    }

    public function getTotalTax(): float
    {
        return $this->getProducts()->sum(fn ($el) => (($el->getPrice() + $el->getDiscount()) * ($el->getTax() / 100)) * $el->getCount());
    }

    #[ORM\Column(type: 'uuid', nullable: true, options: ['default' => null])]
    protected ?\Ramsey\Uuid\UuidInterface $status_uuid;

    #[ORM\ManyToOne(targetEntity: 'App\Domain\Entities\Reference')]
    #[ORM\JoinColumn(name: 'status_uuid', referencedColumnName: 'uuid')]
    protected ?Reference $status = null;

    public function setStatus(?Reference $status): self
    {
        if (is_a($status, Reference::class)) {
            $this->status_uuid = $status->getUuid();
            $this->status = $status;
        } else {
            $this->status_uuid = null;
            $this->status = null;
        }

        return $this;
    }

    public function getStatus(): ?Reference
    {
        return $this->status;
    }

    #[ORM\Column(type: 'datetime', options: ['default' => 'CURRENT_TIMESTAMP'])]
    protected \DateTime $date;

    /**
     * @throws \Exception
     */
    public function setDate(mixed $date, string $timezone = 'UTC'): self
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

    #[ORM\Column(type: 'string', length: 64, options: ['default' => 'manual'])]
    protected string $export = 'manual';

    public function setExport(string $export): self
    {
        if ($this->checkStrLenMax($export, 64)) {
            $this->export = $export;
        }

        return $this;
    }

    public function getExport(): string
    {
        return $this->export;
    }

    #[ORM\Column(type: 'string', length: 512, options: ['default' => ''])]
    protected string $system = '';

    public function setSystem(string $system): self
    {
        if ($this->checkStrLenMax($system, 512) && $this->validText($system)) {
            $this->system = $system;
        }

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
            'discount' => $this->getTotalDiscount(),
            'tax' => $this->getTotalTax(),
            'total' => $this->getTotalPrice(),
            'total_calculated' => $this->getTotalPriceCalculated(),
            'status' => $this->status,
            'date' => $this->date,
            'external_id' => $this->external_id,
            'export' => $this->export,
            'system' => $this->system,
        ]);
    }
}
