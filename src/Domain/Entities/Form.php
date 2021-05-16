<?php declare(strict_types=1);

namespace App\Domain\Entities;

use App\Domain\AbstractEntity;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;

/**
 * @ORM\Entity(repositoryClass="App\Domain\Repository\FormRepository")
 * @ORM\Table(name="form")
 */
class Form extends AbstractEntity
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
     * @ORM\Column(type="string", length=1000, unique=true, options={"default": ""})
     */
    protected string $address = '';

    /**
     * @return $this
     */
    public function setAddress(string $address)
    {
        if ($this->checkStrLenMax($address, 1000)) {
            $this->address = $this->getAddressByValue($address, $this->getTitle());
        }

        return $this;
    }

    public function getAddress(): string
    {
        return $this->address;
    }

    /**
     * @ORM\Column(type="text", options={"default": ""})
     */
    protected string $template = '';

    /**
     * @return $this
     */
    public function setTemplate(string $template)
    {
        $this->template = $template;

        return $this;
    }

    public function getTemplate(): string
    {
        return $this->template;
    }

    /**
     * @ORM\Column(type="boolean", options={"default": true})
     */
    protected bool $recaptcha = true;

    /**
     * @param mixed $recaptcha
     *
     * @return $this
     */
    public function setRecaptcha($recaptcha)
    {
        $this->recaptcha = $this->getBooleanByValue($recaptcha);

        return $this;
    }

    /**
     * @return bool
     */
    public function getRecaptcha()
    {
        return $this->recaptcha;
    }

    /**
     * @ORM\Column(type="array")
     */
    protected array $origin = [];

    /**
     * @param mixed $value
     *
     * @return $this
     */
    public function setOrigin($value)
    {
        $this->origin = $this->getArrayByExplodeValue($value, PHP_EOL);

        return $this;
    }

    public function getOrigin(): array
    {
        return $this->origin;
    }

    /**
     * @ORM\Column(type="array")
     */
    protected array $mailto = [];

    /**
     * @param mixed $value
     *
     * @return $this
     */
    public function setMailto($value)
    {
        $this->mailto = $this->getArrayByExplodeValue($value, PHP_EOL);

        return $this;
    }

    public function getMailto(): array
    {
        return $this->mailto;
    }
}
