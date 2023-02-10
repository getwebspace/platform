<?php declare(strict_types=1);

namespace App\Domain\Entities;

use App\Domain\AbstractEntity;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: 'file_related')]
#[ORM\Entity]
#[ORM\InheritanceType('SINGLE_TABLE')]
#[ORM\DiscriminatorColumn(name: 'object_type', type: 'string')]
#[ORM\DiscriminatorMap(['common' => 'FileRelation', 'catalog_product' => '\App\Domain\Entities\File\CatalogProductFileRelation', 'catalog_category' => '\App\Domain\Entities\File\CatalogCategoryFileRelation', 'form_data' => '\App\Domain\Entities\File\FormDataFileRelation', 'page' => '\App\Domain\Entities\File\PageFileRelation', 'publication_category' => '\App\Domain\Entities\File\PublicationCategoryFileRelation', 'publication' => '\App\Domain\Entities\File\PublicationFileRelation', 'user' => '\App\Domain\Entities\File\UserFileRelation'])]
abstract class FileRelation extends AbstractEntity
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
    protected \Ramsey\Uuid\UuidInterface $entity_uuid;

    protected AbstractEntity $entity;

    /**
     * @return $this
     */
    abstract public function setEntity(AbstractEntity $entity);

    public function getEntity()
    {
        return $this->entity;
    }

    #[ORM\Column(type: 'uuid')]
    protected \Ramsey\Uuid\UuidInterface $file_uuid;

    #[ORM\ManyToOne(targetEntity: 'App\Domain\Entities\File')]
    #[ORM\JoinColumn(name: 'file_uuid', referencedColumnName: 'uuid')]
    protected File $file;

    /**
     * @return $this
     */
    public function setFile(File $file)
    {
        if (is_object($file) && is_a($file, File::class)) {
            $this->file = $file;
            $this->file_uuid = $file->getUuid();
        }

        return $this;
    }

    public function getFile(): File
    {
        return $this->file;
    }

    #[ORM\Column(name: '`order`', type: 'integer', options: ['default' => 1])]
    public int $order = 1;

    /**
     * @return $this
     */
    public function setOrder(int $order)
    {
        if ($order > 0) {
            $this->order = $order;
        }

        return $this;
    }

    public function getOrder(): int
    {
        return $this->order;
    }

    #[ORM\Column(type: 'text', length: 1000, options: ['default' => ''])]
    public string $comment = '';

    /**
     * @return $this
     */
    public function setComment(string $value)
    {
        if ($this->checkStrLenMax($value, 1000)) {
            $this->comment = $value;
        }

        return $this;
    }

    public function getComment(): string
    {
        return $this->comment;
    }

    public function toArray(): array
    {
        return array_serialize(array_merge($this->file->toArray(), [
            'order' => $this->order,
            'comment' => $this->comment,
        ]));
    }

    // magic
    public function __call($name, $arguments)
    {
        if (method_exists($this->file, $name)) {
            return $this->file->{$name}(...$arguments);
        }

        return null;
    }
}
