<?php declare(strict_types=1);

namespace App\Domain\Entities\Catalog;

use App\Domain\AbstractEntity;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;

/**
 * @ORM\Entity
 * @ORM\Table(name="catalog_product_related")
 */
class ProductRelation extends AbstractEntity
{
    /**
     * @ORM\Id
     * @ORM\Column(type="uuid")
     * @ORM\GeneratedValue(strategy="CUSTOM")
     * @ORM\CustomIdGenerator(class="Ramsey\Uuid\Doctrine\UuidGenerator")
     */
    protected Uuid $uuid;

    public function getUuid(): Uuid
    {
        return $this->uuid;
    }

    /**
     * @ORM\Column(type="uuid")
     */
    protected Uuid $product_uuid;

    /**
     * @ORM\ManyToOne(targetEntity="App\Domain\Entities\Catalog\Product")
     * @ORM\JoinColumn(name="product_uuid", referencedColumnName="uuid")
     */
    protected Product $product;

    /**
     * @return $this
     */
    public function setProduct(Product $product): ProductRelation
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

    /**
     * @ORM\Column(type="uuid")
     */
    protected Uuid $related_uuid;

    /**
     * @ORM\ManyToOne(targetEntity="App\Domain\Entities\Catalog\Product")
     * @ORM\JoinColumn(name="related_uuid", referencedColumnName="uuid")
     */
    protected Product $related;

    /**
     * @return $this
     */
    public function setRelated(Product $product): ProductRelation
    {
        if (is_a($product, Product::class)) {
            $this->related_uuid = $product->getUuid();
            $this->related = $product;
        }

        return $this;
    }

    public function getRelated(): Product
    {
        return $this->related;
    }

    /**
     * @ORM\Column(type="float", scale=2, precision=10, options={"default": 1})
     */
    public float $count = 1;

    /**
     * @return $this
     */
    public function setCount(float $count): ProductRelation
    {
        $this->count = $count;

        return $this;
    }

    /**
     * @return float
     */
    public function getCount(): float
    {
        return $this->count;
    }

    public function toArray(): array
    {
        return [
            'uuid' => $this->related->getUuid(),
            'title' => $this->related->getTitle(),
            'address' => $this->related->getAddress(),
            'price' => $this->related->getPrice(),
            'count' => $this->getCount(),
        ];
    }
}
