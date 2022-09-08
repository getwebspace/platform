<?php declare(strict_types=1);

namespace App\Domain\Entities\File;

use App\Domain\AbstractEntity;
use App\Domain\Entities\FileRelation;
use App\Domain\Entities\Page;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class PageFileRelation extends FileRelation
{
    #[ORM\ManyToOne(targetEntity: 'App\Domain\Entities\Page', inversedBy: 'files')]
    #[ORM\JoinColumn(name: 'entity_uuid', referencedColumnName: 'uuid')]
    protected Page $page;

    public function setEntity(AbstractEntity $entity): self
    {
        if (is_a($entity, Page::class)) {
            $this->entity_uuid = $entity->getUuid();
            $this->page = $entity;
        }

        return $this;
    }
}
