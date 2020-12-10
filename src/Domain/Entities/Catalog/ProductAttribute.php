<?php declare(strict_types=1);

namespace App\Domain\Entities\Catalog;

use App\Domain\AbstractEntity;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;
use RuntimeException;

/**
 * @ORM\Entity
 * @ORM\Table(name="catalog_product_attribute")
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
     */
    protected Attribute $attribute;

    /**
     * @param Attribute $attribute
     *
     * @return $this
     */
    public function setAttribute(Attribute $attribute)
    {
        if (is_object($attribute) && is_a($attribute, Product::class)) {
            $this->attribute = $attribute;
            $this->attribute_uuid = $attribute->getUuid();
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
        if ($this->checkStrLenMax($value, 1000)) {
            $this->value = $value;
        }

        return $this;
    }

    /**
     * @return mixed
     */
    public function getValue()
    {
        switch ($this->attribute->getType()) {
            case 'string':
                return (string) $this->value;

            case 'integer':
                return intval($this->value);

            case 'float':
                return floatval($this->value);
        }

        throw new RuntimeException('Wrong attribute type');
    }
}
