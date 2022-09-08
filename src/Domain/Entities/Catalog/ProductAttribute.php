<?php declare(strict_types=1);

namespace App\Domain\Entities\Catalog;

use App\Domain\AbstractEntity;
use Doctrine\ORM\Mapping as ORM;
use RuntimeException;

#[ORM\Table(name: 'catalog_product_attributes')]
#[ORM\Entity]
#[ORM\HasLifecycleCallbacks]
class ProductAttribute extends AbstractEntity
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
    protected \Ramsey\Uuid\UuidInterface $product_uuid;

    #[ORM\ManyToOne(targetEntity: 'App\Domain\Entities\Catalog\Product')]
    #[ORM\JoinColumn(name: 'product_uuid', referencedColumnName: 'uuid')]
    protected Product $product;

    /**
     * @return $this
     */
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

    #[ORM\Column(type: 'uuid')]
    protected \Ramsey\Uuid\UuidInterface $attribute_uuid;

    #[ORM\ManyToOne(targetEntity: 'App\Domain\Entities\Catalog\Attribute')]
    #[ORM\JoinColumn(name: 'attribute_uuid', referencedColumnName: 'uuid')]
    protected Attribute $attribute;

    /**
     * @return $this
     */
    public function setAttribute(Attribute $attribute): self
    {
        if (is_a($attribute, Attribute::class)) {
            $this->attribute_uuid = $attribute->getUuid();
            $this->attribute = $attribute;
        }

        return $this;
    }

    public function getAttribute(): Attribute
    {
        return $this->attribute;
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

    #[ORM\Column(type: 'string', length: 1000, options: ['default' => ''])]
    public string $value = '';

    /**
     * @param mixed $value
     *
     * @return $this
     */
    public function setValue($value): self
    {
        switch ($this->attribute->getType()) {
            case \App\Domain\Types\Catalog\AttributeTypeType::TYPE_STRING:
                if ($this->checkStrLenMax($value, 1000)) {
                    $this->value = mb_strtolower($value);
                }

                break;

            case \App\Domain\Types\Catalog\AttributeTypeType::TYPE_BOOLEAN:
            case \App\Domain\Types\Catalog\AttributeTypeType::TYPE_INTEGER:
            case \App\Domain\Types\Catalog\AttributeTypeType::TYPE_FLOAT:
                $this->value = $value;

                break;
        }

        return $this;
    }

    /**
     * @return mixed
     */
    public function getValue()
    {
        switch ($this->attribute->getType()) {
            case \App\Domain\Types\Catalog\AttributeTypeType::TYPE_STRING:
                return (string) $this->value;

            case \App\Domain\Types\Catalog\AttributeTypeType::TYPE_BOOLEAN:
            case \App\Domain\Types\Catalog\AttributeTypeType::TYPE_INTEGER:
                return intval($this->value);

            case \App\Domain\Types\Catalog\AttributeTypeType::TYPE_FLOAT:
                return floatval($this->value);
        }

        throw new RuntimeException('Wrong attribute type');
    }

    /**
     * Return other values current attribute
     */
    public function getOtherValues(): \Illuminate\Support\Collection
    {
        return $this->attribute
            ->getProductAttributes()
            ->unique('value')
            ->whereNotIn('value', $this->value)
            ->pluck('value');
    }

    /**
     * Return other Products with current attribute value
     */
    public function getOtherProducts(): \Illuminate\Support\Collection
    {
        return $this->attribute
            ->getProductAttributes()
            ->where('value', $this->value)
            ->whereNotIn('product.uuid', $this->product->getUuid())
            ->pluck('product');
    }

    /**
     * Return count products with current attribute value
     */
    public function getCount(): int
    {
        return $this->attribute
            ->getProductAttributes()
            ->where('value', $this->value)
            ->count();
    }

    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'type' => $this->type,
            'address' => $this->address,
            'value' => $this->value,
            'count' => $this->getCount(),
        ];
    }

    #[ORM\PostLoad]
    public function _populate_fields(): void
    {
        $this->title = $this->attribute->getTitle();
        $this->type = $this->attribute->getType();
        $this->address = $this->attribute->getAddress();
    }
}
