<?php declare(strict_types=1);

namespace App\Domain\Entities\File;

use App\Domain\AbstractEntity;
use App\Domain\Entities\Catalog\Category as CatalogCategory;
use App\Domain\Entities\FileRelation;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 */
class CatalogCategoryFileRelation extends FileRelation
{
    /**
     * @ORM\ManyToOne(targetEntity="App\Domain\Entities\Catalog\Category", inversedBy="files")
     * @ORM\JoinColumn(name="entity_uuid", referencedColumnName="uuid", nullable=true)
     */
    protected CatalogCategory $catalog_category;

    public function setEntity(AbstractEntity $entity): self
    {
        if (is_a($entity, CatalogCategory::class)) {
            $this->entity_uuid = $entity->getUuid();
            $this->catalog_category = $entity;
        }

        return $this;
    }
}
