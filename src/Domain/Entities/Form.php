<?php declare(strict_types=1);

namespace App\Domain\Entities;

use App\Domain\AbstractEntity;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: 'form')]
#[ORM\Entity(repositoryClass: 'App\Domain\Repository\FormRepository')]
class Form extends AbstractEntity
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

    #[ORM\Column(type: 'string', length: 255, options: ['default' => ''])]
    protected string $title = '';

    /**
     * @return $this
     */
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

    #[ORM\Column(type: 'string', length: 1000, unique: true, options: ['default' => ''])]
    protected string $address = '';

    /**
     * @return $this
     */
    public function setAddress(string $address): self
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

    #[ORM\Column(type: 'text', options: ['default' => ''])]
    protected string $template = '';

    /**
     * @return $this
     */
    public function setTemplate(string $template): self
    {
        $this->template = $template;

        return $this;
    }

    public function getTemplate(): string
    {
        return $this->template;
    }

    #[ORM\Column(type: 'string', length: 50, options: ['default' => ''])]
    protected string $templateFile = '';

    /**
     * @return $this
     */
    public function setTemplateFile(string $template): self
    {
        if ($this->checkStrLenMax($template, 50)) {
            $this->templateFile = $template;
        }

        return $this;
    }

    public function getTemplateFile(): string
    {
        return $this->templateFile;
    }

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    protected bool $recaptcha = true;

    /**
     * @param mixed $recaptcha
     *
     * @return $this
     */
    public function setRecaptcha($recaptcha): self
    {
        $this->recaptcha = $this->getBooleanByValue($recaptcha);

        return $this;
    }

    public function getRecaptcha(): bool
    {
        return $this->recaptcha;
    }

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    protected bool $authorSend = false;

    /**
     * @param mixed $authorSend
     *
     * @return $this
     */
    public function setAuthorSend($authorSend): self
    {
        $this->authorSend = $this->getBooleanByValue($authorSend);

        return $this;
    }

    public function getAuthorSend(): bool
    {
        return $this->authorSend;
    }

    #[ORM\Column(type: 'array', options: ['default' => 'a:0:{}'])]
    protected array $origin = [];

    /**
     * @param mixed $value
     *
     * @return $this
     */
    public function setOrigin($value): self
    {
        $this->origin = $this->getArrayByExplodeValue($value, PHP_EOL);

        return $this;
    }

    public function getOrigin(): array
    {
        return $this->origin;
    }

    #[ORM\Column(type: 'array', options: ['default' => 'a:0:{}'])]
    protected array $mailto = [];

    /**
     * @param mixed $value
     *
     * @return $this
     */
    public function setMailto($value): self
    {
        $this->mailto = $this->getArrayByExplodeValue($value, PHP_EOL);

        return $this;
    }

    public function getMailto(): array
    {
        return $this->mailto;
    }

    #[ORM\Column(type: 'string', length: 250, options: ['default' => ''])]
    protected string $duplicate = '';

    /**
     * @return $this
     */
    public function setDuplicate(string $duplicate): self
    {
        if ($this->checkStrLenMax($duplicate, 250)) {
            $this->duplicate = $duplicate;
        }

        return $this;
    }

    public function getDuplicate(): string
    {
        return $this->duplicate;
    }
}
