<?php declare(strict_types=1);

namespace App\Domain\Entities\File;

use App\Domain\AbstractEntity;
use App\Domain\Entities\FileRelation;
use App\Domain\Entities\Publication;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 */
class PublicationFileRelation extends FileRelation
{
    /**
     * @ORM\ManyToOne(targetEntity="App\Domain\Entities\Publication", inversedBy="files")
     * @ORM\JoinColumn(name="entity_uuid", referencedColumnName="uuid", nullable=true)
     */
    protected Publication $publication;

    public function setEntity(AbstractEntity $entity)
    {
        if (is_object($entity) && is_a($entity, Publication::class)) {
            $this->entity_uuid = $entity->getUuid();
            $this->publication = $entity;
        }

        return $this;
    }
}
