<?php declare(strict_types=1);

namespace App\Domain\Entities\Catalog;

use App\Domain\AbstractEntity;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: 'catalog_order_product')]
#[ORM\Entity]
#[ORM\HasLifecycleCallbacks]
class OrderProduct extends AbstractEntity
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

    #[ORM\Column(type: 'uuid')]
    protected \Ramsey\Uuid\UuidInterface $order_uuid;

    #[ORM\ManyToOne(targetEntity: 'App\Domain\Entities\Catalog\Order')]
    #[ORM\JoinColumn(name: 'order_uuid', referencedColumnName: 'uuid')]
    protected Order $order;

    public function setOrder(Order $order): self
    {
        if (is_a($order, Order::class)) {
            $this->order = $order;
            $this->order_uuid = $order->getUuid();
        }

        return $this;
    }

    public function getOrder(): Order
    {
        return $this->order;
    }

    #[ORM\Column(type: 'uuid')]
    protected \Ramsey\Uuid\UuidInterface $product_uuid;

    #[ORM\ManyToOne(targetEntity: 'App\Domain\Entities\Catalog\Product')]
    #[ORM\JoinColumn(name: 'product_uuid', referencedColumnName: 'uuid')]
    protected Product $product;

    public function setProduct(Product $product): self
    {
        if (is_a($product, Product::class)) {
            $this->product = $product;
            $this->product_uuid = $product->getUuid();
        }

        return $this;
    }

    public function getProduct(): Product
    {
        return $this->product;
    }

    protected string $title = '';

    public function getTitle(): string
    {
        return $this->title;
    }

    protected string $type = '';

    public function getType(): string
    {
        return $this->type;
    }

    protected string $address = '';

    public function getAddress(): string
    {
        return $this->address;
    }

    protected string $vendorCode = '';

    public function getVendorCode(): string
    {
        return $this->vendorCode;
    }

    protected string $external_id = '';

    public function getExternalId(): string
    {
        return $this->external_id;
    }

    #[ORM\Column(type: 'float', precision: 10, scale: 2, options: ['default' => 0])]
    protected float $price = .00;

    public function setPrice(float $value): self
    {
        $this->price = $value;

        return $this;
    }

    public function getPrice(): float
    {
        return $this->price;
    }

    #[ORM\Column(type: 'string', length: 16, options: ['default' => 'price'])]
    protected string $price_type = 'price';

    public function setPriceType(string $value): self
    {
        if (in_array($value, \App\Domain\References\Catalog::PRODUCT_PRICE_TYPE, true)) {
            $this->price_type = $value;
        }

        return $this;
    }

    public function getPriceType(): string
    {
        return $this->price_type;
    }

    #[ORM\Column(type: 'float', precision: 10, scale: 2, options: ['default' => 1])]
    public float $count = 1;

    public function setCount(float $count): self
    {
        $this->count = $count;

        return $this;
    }

    public function getCount(): float
    {
        return $this->count;
    }

    #[ORM\Column(type: 'float', precision: 10, scale: 2, options: ['default' => 0])]
    public float $discount = .00;

    public function setDiscount(float $value): self
    {
        if ($value > 0) {
            $value = -$value;
        }
        $this->discount = $value;

        return $this;
    }

    public function getDiscount(): float
    {
        return $this->discount;
    }

    #[ORM\Column(type: 'float', precision: 10, scale: 2, options: ['default' => 0])]
    public float $tax = .00;

    public function setTax(float $value): self
    {
        $this->tax = $value;

        return $this;
    }

    public function getTax(): float
    {
        return $this->tax;
    }

    public function getTotal(): float
    {
        return $this->price * $this->count;
    }

    public function getPriceCalculated(): float
    {
        $price = $this->price;

        if ($this->discount < 0) {
            $price = max(0, $price + $this->discount);
        }
        if ($this->tax > 0) {
            $price += $price * ($this->tax / 100);
        }

        return ceil($price);
    }

    public function getTotalCalculated(): float
    {
        return $this->getPriceCalculated() * $this->count;
    }

    public function toArray(): array
    {
        return array_serialize([
            'uuid' => $this->product->getUuid(),
            'title' => $this->product->getTitle(),
            'type' => $this->product->getType(),
            'address' => $this->product->getAddress(),
            'vendorCode' => $this->product->getVendorCode(),
            'external_id' => $this->product->getExternalId(),
            'count' => $this->getCount(),
            'price_type' => $this->getPriceType(),
            'price' => $this->getPrice(),
            'price_calculated' => $this->getPriceCalculated(),
            'discount' => $this->getDiscount(),
            'tax' => $this->getTax(),
            'total' => $this->getTotal(),
            'total_calculated' => $this->getTotalCalculated(),
            'files' => $this->product->getFiles(),
        ]);
    }

    #[ORM\PostLoad]
    public function _populate_fields(): void
    {
        $this->title = $this->product->getTitle();
        $this->type = $this->product->getType();
        $this->address = $this->product->getAddress();
        $this->vendorCode = $this->product->getVendorCode();
        $this->external_id = $this->product->getExternalId();
    }

    // magic
    public function __call($name, $arguments)
    {
        if (method_exists($this->product, $name)) {
            return $this->product->{$name}(...$arguments);
        }

        return null;
    }
}
