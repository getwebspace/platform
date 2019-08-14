<?php

namespace Domain\Entities;

use AEngine\Entity\Model;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\UuidInterface;

/**
 * @ORM\Entity
 * @ORM\Table(name="form")
 */
class Form extends Model
{
    /**
     * @var UuidInterface
     * @ORM\Id
     * @ORM\Column(type="uuid")
     * @ORM\GeneratedValue(strategy="CUSTOM")
     * @ORM\CustomIdGenerator(class="Ramsey\Uuid\Doctrine\UuidGenerator")
     */
    public $uuid;

    /**
     * @ORM\Column(type="string")
     */
    public $title;

    /**
     * @ORM\Column(type="string")
     */
    public $address;

    /**
     * @ORM\Column(type="text")
     */
    public $template;

    /**
     * @ORM\Column(type="array")
     */
    public $origin = [];

    /**
     * @ORM\Column(type="array")
     */
    public $mailto = [];
}
