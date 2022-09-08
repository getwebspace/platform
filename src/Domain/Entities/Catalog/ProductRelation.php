<?php declare(strict_types=1);

namespace App\Domain\Entities\Catalog;

use App\Domain\AbstractEntity;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\UuidInterface as Uuid;

#[ORM\Table(name: 'catalog_product_related')]
#[ORM\Entity]
class ProductRelation extends AbstractEntity
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
    protected \Ramsey\Uuid\UuidInterface $related_uuid;

    #[ORM\ManyToOne(targetEntity: 'App\Domain\Entities\Catalog\Product')]
    #[ORM\JoinColumn(name: 'related_uuid', referencedColumnName: 'uuid')]
    protected Product $related;

    /**
     * @return $this
     */
    public function setRelated(Product $product): self
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

    #[ORM\Column(type: 'float', scale: 2, precision: 10, options: ['default' => 1])]
    public float $count = 1;

    /**
     * @return $this
     */
    public function setCount(float $count): self
    {
        $this->count = $count;

        return $this;
    }

    public function getCount(): float
    {
        return $this->count;
    }

    public function toArray(): array
    {
        $type = get_class($this->related) === Product::class ? 'product' : 'related';

        return [
            'uuid' => $this->{$type}->getUuid(),
            'title' => $this->{$type}->getTitle(),
            'address' => $this->{$type}->getAddress(),
            'price' => $this->{$type}->getPrice(),
            'count' => $type === 'related' ? $this->getCount() : 1,
        ];
    }
}
