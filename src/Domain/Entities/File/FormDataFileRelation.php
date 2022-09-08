<?php declare(strict_types=1);

namespace App\Domain\Entities\File;

use App\Domain\AbstractEntity;
use App\Domain\Entities\FileRelation;
use App\Domain\Entities\Form\Data as FormData;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class FormDataFileRelation extends FileRelation
{
    #[ORM\ManyToOne(targetEntity: 'App\Domain\Entities\Form\Data', inversedBy: 'files')]
    #[ORM\JoinColumn(name: 'entity_uuid', referencedColumnName: 'uuid')]
    protected FormData $form_data;

    public function setEntity(AbstractEntity $entity): self
    {
        if (is_a($entity, FormData::class)) {
            $this->entity_uuid = $entity->getUuid();
            $this->form_data = $entity;
        }

        return $this;
    }
}
