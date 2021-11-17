<?php declare(strict_types=1);

namespace App\Domain\Entities\File;

use App\Domain\AbstractEntity;
use App\Domain\Entities\FileRelation;
use App\Domain\Entities\User;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 */
class UserFileRelation extends FileRelation
{
    /**
     * @ORM\ManyToOne(targetEntity="App\Domain\Entities\User", inversedBy="files")
     * @ORM\JoinColumn(name="entity_uuid", referencedColumnName="uuid", nullable=true)
     */
    protected User $user;

    public function setEntity(AbstractEntity $entity): self
    {
        if (is_a($entity, User::class)) {
            $this->entity_uuid = $entity->getUuid();
            $this->user = $entity;
        }

        return $this;
    }
}
