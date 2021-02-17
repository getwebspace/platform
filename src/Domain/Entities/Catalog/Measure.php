<?php declare(strict_types=1);

namespace App\Domain\Entities\Catalog;

use App\Domain\AbstractEntity;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;

/**
 * @ORM\Entity
 * @ORM\Table(name="catalog_measure")
 */
class Measure extends AbstractEntity
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
     * @ORM\Column(type="string", length=255, options={"default": ""})
     */
    protected string $contraction = '';

    /**
     * @param string $contraction
     *
     * @return $this
     */
    public function setContraction(string $contraction)
    {
        if ($this->checkStrLenMax($contraction, 255)) {
            $this->contraction = $contraction;
        }

        return $this;
    }

    /**
     * @return string
     */
    public function getContraction(): string
    {
        return $this->contraction;
    }

    /**
     * @ORM\Column(type="float", scale=2, precision=10, options={"default": 1.00})
     */
    public float $value = 1.00;

    /**
     * @param float $value
     *
     * @return $this
     */
    public function setValue(float $value)
    {
        $this->value = $value;

        return $this;
    }

    /**
     * @return float
     */
    public function getValue(): float
    {
        return $this->value;
    }
}
