<?php declare(strict_types=1);

namespace App\Domain\Entities\File;

use App\Domain\AbstractEntity;
use App\Domain\Entities\FileRelation;
use App\Domain\Entities\Publication\Category as PublicationCategory;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 */
class PublicationCategoryFileRelation extends FileRelation
{
    /**
     * @ORM\ManyToOne(targetEntity="App\Domain\Entities\Publication\Category", inversedBy="files")
     * @ORM\JoinColumn(name="entity_uuid", referencedColumnName="uuid", nullable=true)
     */
    protected PublicationCategory $publication_category;

    public function setEntity(AbstractEntity $entity): PublicationCategoryFileRelation
    {
        if (is_a($entity, PublicationCategory::class)) {
            $this->entity_uuid = $entity->getUuid();
            $this->publication_category = $entity;
        }

        return $this;
    }
}
