<?php declare(strict_types=1);

namespace App\Domain\Entities\User;

use App\Domain\AbstractEntity;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\UuidInterface as Uuid;

#[ORM\Table(name: 'user_group')]
#[ORM\Entity(repositoryClass: 'App\Domain\Repository\User\GroupRepository')]
class Group extends AbstractEntity
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

    #[ORM\Column(type: 'text', length: 1000, options: ['default' => ''])]
    protected string $description = '';

    /**
     * @return $this
     */
    public function setDescription(string $description)
    {
        if ($this->checkStrLenMax($description, 1000)) {
            $this->description = $description;
        }

        return $this;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    #[ORM\Column(type: 'array', options: ['default' => 'a:0:{}'])]
    protected array $access = [];

    /**
     * @return $this
     */
    public function setAccess(array $access)
    {
        $this->access = $access;

        return $this;
    }

    /**
     * @return array
     */
    public function getAccess()
    {
        return $this->access;
    }

    public function toArray(): array
    {
        return [
            'uuid' => $this->getUuid(),
            'title' => $this->getTitle(),
            'access' => $this->getAccess(),
        ];
    }
}
