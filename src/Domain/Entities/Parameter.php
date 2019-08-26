<?php

namespace App\Domain\Entities;

use AEngine\Entity\Model;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\Table(name="params")
 */
class Parameter extends Model
{
    /**
     * @ORM\Id
     * @ORM\Column(name="name", type="string", length=50, unique=true)
     */
    public $key;

    /**
     * @ORM\Column(type="string", length=1024)
     */
    public $value;
}
