<?php declare(strict_types=1);

namespace App\Domain\Entities;

use App\Domain\AbstractEntity;
use App\Domain\Service\Reference\Exception\WrongTitleValueException;
use App\Domain\Traits\FileTrait;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: 'reference')]
#[ORM\Entity(repositoryClass: 'App\Domain\Repository\ReferenceRepository')]
#[ORM\UniqueConstraint(name: 'reference_unique', columns: ['type', 'title'])]
class Reference extends AbstractEntity
{
    use FileTrait;

    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'Ramsey\Uuid\Doctrine\UuidGenerator')]
    protected \Ramsey\Uuid\UuidInterface $uuid;

    public function getUuid(): \Ramsey\Uuid\UuidInterface
    {
        return $this->uuid;
    }

    /**
     * @see \App\Domain\Types\ReferenceTypeType::LIST
     */
    #[ORM\Column(type: 'ReferenceTypeType', options: ['default' => \App\Domain\Types\ReferenceTypeType::TYPE_TEXT])]
    protected string $type = \App\Domain\Types\ReferenceTypeType::TYPE_TEXT;

    /**
     * @return $this
     */
    public function setType(string $type): static
    {
        if (in_array($type, \App\Domain\Types\ReferenceTypeType::LIST, true)) {
            $this->type = $type;
        }

        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    #[ORM\Column(type: 'string', length: 255, options: ['default' => ''])]
    protected string $title = '';

    /**
     * @return $this
     */
    public function setTitle(string $title): static
    {
        if ($this->checkStrLenMax($title, 255)) {
            if ($this->validName($title)) {
                $this->title = $title;
            } else {
                throw new WrongTitleValueException();
            }
        }

        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    #[ORM\Column(type: 'json', options: ['default' => '{}'])]
    public array $value = [];

    /**
     * @return $this
     */
    public function setValue(array $value): static
    {
        $this->value = $value;

        return $this;
    }

    public function getValue(): array
    {
        return $this->value;
    }

    #[ORM\Column(name: '`order`', type: 'integer', options: ['default' => 1])]
    protected int $order = 1;

    public function setOrder(int $order): static
    {
        $this->order = $order;

        return $this;
    }

    public function getOrder(): int
    {
        return $this->order;
    }

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    protected bool $status = false;

    /**
     * @return $this
     */
    public function setStatus(mixed $value): static
    {
        $this->status = $this->getBooleanByValue($value);

        return $this;
    }

    public function getStatus(): bool
    {
        return $this->status;
    }

    /**
     * Return model as array
     */
    public function toArray(): array
    {
        return array_serialize([
            'uuid' => $this->uuid,
            'type' => $this->type,
            'title' => $this->title,
            'value' => $this->value,
            'order' => $this->order,
            'status' => $this->status,
        ]);
    }
}
