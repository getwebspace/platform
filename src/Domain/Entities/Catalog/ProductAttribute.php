<?php declare(strict_types=1);

namespace App\Domain\Entities\Catalog;

use App\Domain\AbstractEntity;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;
use RuntimeException;

/**
 * @ORM\Entity
 * @ORM\Table(name="catalog_product_attributes")
 */
class ProductAttribute extends AbstractEntity
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
     * @ORM\Column(type="uuid")
     */
    protected Uuid $product_uuid;

    /**
     * @var Product
     * @ORM\ManyToOne(targetEntity="App\Domain\Entities\Catalog\Product")
     * @ORM\JoinColumn(name="product_uuid", referencedColumnName="uuid")
     */
    protected Product $product;

    /**
     * @param Product $product
     *
     * @return $this
     */
    public function setProduct(Product $product)
    {
        if (is_object($product) && is_a($product, Product::class)) {
            $this->product = $product;
            $this->product_uuid = $product->getUuid();
        }

        return $this;
    }

    /**
     * @return Product
     */
    public function getProduct(): Product
    {
        return $this->product;
    }

    /**
     * @ORM\Column(type="uuid")
     */
    protected Uuid $attribute_uuid;

    /**
     * @var Attribute
     * @ORM\ManyToOne(targetEntity="App\Domain\Entities\Catalog\Attribute")
     * @ORM\JoinColumn(name="attribute_uuid", referencedColumnName="uuid")
     */
    protected Attribute $attribute;

    /**
     * @param Attribute $attribute
     *
     * @return $this
     */
    public function setAttribute(Attribute $attribute)
    {
        if (is_object($attribute) && is_a($attribute, Attribute::class)) {
            $this->attribute_uuid = $attribute->getUuid();
            $this->attribute = $attribute;
        }

        return $this;
    }

    /**
     * @return Attribute
     */
    public function getAttribute(): Attribute
    {
        return $this->attribute;
    }

    /**
     * @return string
     */
    public function getTitle()
    {
        return $this->attribute->getTitle();
    }

    /**
     * @return string
     */
    public function getType()
    {
        return $this->attribute->getType();
    }

    /**
     * @return string
     */
    public function getAddress()
    {
        return $this->attribute->getAddress();
    }

    /**
     * @ORM\Column(type="string", length=1000, options={"default": ""})
     */
    public string $value = '';

    /**
     * @param mixed $value
     *
     * @return $this
     */
    public function setValue($value)
    {
        switch ($this->attribute->getType()) {
            case \App\Domain\Types\Catalog\AttributeTypeType::TYPE_STRING:
                if ($this->checkStrLenMax($value, 1000)) {
                    $this->value = mb_strtolower($value);
                }

                break;

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

            case \App\Domain\Types\Catalog\AttributeTypeType::TYPE_INTEGER:
                return intval($this->value);

            case \App\Domain\Types\Catalog\AttributeTypeType::TYPE_FLOAT:
                return floatval($this->value);
        }

        throw new RuntimeException('Wrong attribute type');
    }
}
