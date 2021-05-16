<?php declare(strict_types=1);

namespace App\Domain\Entities\Catalog;

use App\Domain\AbstractEntity;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;

/**
 * @ORM\Entity(repositoryClass="App\Domain\Repository\Catalog\MeasureRepository")
 * @ORM\Table(name="catalog_measure")
 */
class Measure extends AbstractEntity
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
     * @ORM\Column(type="string", length=255, options={"default": ""})
     */
    protected string $title = '';

    /**
     * @return $this
     */
    public function setTitle(string $title)
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

    /**
     * @return $this
     */
    public function setContraction(string $contraction)
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

    /**
     * @return $this
     */
    public function setValue(float $value)
    {
        $this->value = $value;

        return $this;
    }

    public function getValue(): float
    {
        return $this->value;
    }
}
