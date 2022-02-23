<?php declare(strict_types=1);

namespace App\Domain\Entities\Catalog;

use App\Domain\AbstractEntity;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Domain\Repository\Catalog\MeasureRepository")
 * @ORM\Table(name="catalog_measure",
 *     uniqueConstraints={
 *         @ORM\UniqueConstraint(name="catalog_measure_contraction_unique", columns={"contraction"})
 *     }
 * )
 */
class Measure extends AbstractEntity
{
    /**
     * @ORM\Id
     * @ORM\Column(type="uuid")
     * @ORM\GeneratedValue(strategy="CUSTOM")
     * @ORM\CustomIdGenerator(class="Ramsey\Uuid\Doctrine\UuidGenerator")
     */
    protected \Ramsey\Uuid\UuidInterface $uuid;

    public function getUuid(): \Ramsey\Uuid\UuidInterface
    {
        return $this->uuid;
    }

    /**
     * @ORM\Column(type="string", length=255, options={"default": ""})
     */
    protected string $title = '';

    public function setTitle(string $title): self
    {
        if ($this->checkStrLenMax($title, 255)) {
            $this->title = $title;
        }

        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * @ORM\Column(type="string", length=255, options={"default": ""})
     */
    protected string $contraction = '';

    public function setContraction(string $contraction): self
    {
        if ($this->checkStrLenMax($contraction, 255)) {
            $this->contraction = $contraction;
        }

        return $this;
    }

    public function getContraction(): string
    {
        return $this->contraction;
    }

    /**
     * @ORM\Column(type="float", scale=2, precision=10, options={"default": 1.00})
     */
    public float $value = 1.00;

    public function setValue(float $value): self
    {
        $this->value = $value;

        return $this;
    }

    public function getValue(): float
    {
        return $this->value;
    }
}
