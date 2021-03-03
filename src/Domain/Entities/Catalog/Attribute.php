<?php declare(strict_types=1);

namespace App\Domain\Entities\Catalog;

use App\Domain\AbstractEntity;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;

/**
 * @ORM\Entity
 * @ORM\Table(name="catalog_attribute")
 */
class Attribute extends AbstractEntity
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
     * @ORM\Column(type="string", length=255, options={"default": ""})
     */
    protected string $title = '';

    /**
     * @param string $title
     *
     * @return $this
     */
    public function setTitle(string $title)
    {
        if ($this->checkStrLenMax($title, 255)) {
            $this->title = $title;
        }

        return $this;
    }

    /**
     * @return string
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * @ORM\Column(type="string", length=500, unique=true, options={"default": ""})
     */
    protected string $address = '';

    /**
     * @param string $address
     *
     * @return $this
     */
    public function setAddress(string $address)
    {
        if ($this->checkStrLenMax($address, 500)) {
            $this->address = $this->getAddressByValue($address, $this->getTitle());
        }

        return $this;
    }

    /**
     * @return string
     */
    public function getAddress(): string
    {
        return $this->address;
    }

    /**
     * @see \App\Domain\Types\Catalog\AttributeTypeType::LIST
     * @ORM\Column(type="CatalogAttributeTypeType", options={"default": \App\Domain\Types\Catalog\AttributeTypeType::TYPE_STRING})
     */
    protected string $type = \App\Domain\Types\Catalog\AttributeTypeType::TYPE_STRING;

    /**
     * @param string $type
     *
     * @return $this
     */
    public function setType(string $type)
    {
        if ($this->checkStrLenMax($type, 255) && in_array($type, \App\Domain\Types\Catalog\AttributeTypeType::LIST, true)) {
            $this->type = $type;
        }

        return $this;
    }

    /**
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @ORM\OneToMany(targetEntity="App\Domain\Entities\Catalog\ProductAttribute", mappedBy="attribute", orphanRemoval=true)
     * @ORM\JoinColumn(name="uuid", referencedColumnName="attribute_uuid")
     */
    protected $productAttributes = [];

    /**
     * @param mixed $raw
     *
     * @return array|\Illuminate\Support\Collection
     */
    public function getProductAttributes($raw = false)
    {
        return $raw ? $this->productAttributes : collect($this->productAttributes);
    }

    public function getProducts(\Illuminate\Support\Collection $categories = null): \Illuminate\Support\Collection
    {
        $buf = $this->getProductAttributes();

        if ($categories) {
            $buf = $buf->whereIn('product.category', $categories->pluck('uuid'));
        }

        return $buf->pluck('product');
    }

    public function getValues(\Illuminate\Support\Collection $categories = null): \Illuminate\Support\Collection
    {
        $buf = $this->getProductAttributes();

        if ($categories) {
            $buf = $buf->whereIn('product.category', $categories->pluck('uuid'));
        }

        return $buf->unique('value')->pluck('value');
    }

    public function getValuesWithCounts(\Illuminate\Support\Collection $categories = null): \Illuminate\Support\Collection
    {
        $buf = $this->getProductAttributes();
        $result = collect();

        foreach ($this->getValues($categories) as $value) {
            $result[$value] = $buf->where('value', $value)->count();
        }

        return $result;
    }
}
