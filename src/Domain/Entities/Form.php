<?php declare(strict_types=1);

namespace App\Domain\Entities;

use App\Domain\AbstractEntity;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;

/**
 * @ORM\Entity
 * @ORM\Table(name="form")
 */
class Form extends AbstractEntity
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
     * @ORM\Column(type="string", options={"default": ""})
     */
    protected string $title = '';

    /**
     * @param string $title
     *
     * @return $this
     */
    public function setTitle(string $title)
    {
        if ($this->checkStrLenMax($title, 50)) {
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
     * @ORM\Column(type="string", unique=true, options={"default": ""})
     */
    protected string $address = '';

    /**
     * @param string $address
     *
     * @return $this
     */
    public function setAddress(string $address)
    {
        if ($this->checkStrLenMax($address, 255)) {
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
     * @ORM\Column(type="string", length=50, options={"default": ""})
     */
    protected string $template = '';

    /**
     * @param string $template
     *
     * @return $this
     */
    public function setTemplate(string $template)
    {
        $this->template = $template;

        return $this;
    }

    /**
     * @return string
     */
    public function getTemplate(): string
    {
        return $this->template;
    }

    /**
     * @ORM\Column(type="boolean", options={"default": true})
     */
    protected bool $save_data = true;

    /**
     * @param mixed $save_data
     *
     * @return $this
     */
    public function setSaveData($save_data)
    {
        $this->save_data = $this->getBooleanByValue($save_data);

        return $this;
    }

    /**
     * @return bool
     */
    public function getSaveData()
    {
        return $this->save_data;
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
     * @var array
     * @ORM\Column(type="array")
     */
    protected array $origin = [];

    /**
     * @param array $data
     *
     * @return $this
     */
    public function setOrigin(array $data)
    {
        $this->origin = $data;

        return $this;
    }

    /**
     * @return array
     */
    public function getOrigin(): array
    {
        return $this->origin;
    }

    /**
     * @var array
     * @ORM\Column(type="array")
     */
    protected array $mailto = [];

    /**
     * @param array $data
     *
     * @return $this
     */
    public function setMailto(array $data)
    {
        $this->mailto = $data;

        return $this;
    }

    /**
     * @return array
     */
    public function getMailto(): array
    {
        return $this->mailto;
    }
}
