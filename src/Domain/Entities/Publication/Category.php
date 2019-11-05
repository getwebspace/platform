<?php

namespace App\Domain\Entities\Publication;

use Alksily\Entity\Model;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;

/**
 * @ORM\Entity
 * @ORM\Table(name="publication_category")
 */
class Category extends Model
{
    /**
     * @var Uuid
     * @ORM\Id
     * @ORM\Column(type="uuid")
     * @ORM\GeneratedValue(strategy="CUSTOM")
     * @ORM\CustomIdGenerator(class="Ramsey\Uuid\Doctrine\UuidGenerator")
     */
    public $uuid;

    /**
     * @ORM\Column(type="string", unique=true)
     */
    public $address;

    /**
     * @ORM\Column(type="string")
     */
    public $title;

    /**
     * @ORM\Column(type="string")
     */
    public $description;

    /**
     * @ORM\Column(type="uuid", options={"default": NULL})
     */
    public $parent;

    /**
     * @ORM\Column(type="integer", options={"default": "10"})
     */
    public $pagination;

    /**
     * @ORM\Column(type="boolean", options={"default": false})
     */
    public $children = false;

    /**
     * @var array
     * @ORM\Column(type="array", nullable=true)
     */
    public $sort = [
        'by' => \App\Domain\References\Publication::ORDER_BY_DATE,
        'direction' => \App\Domain\References\Publication::ORDER_DIRECTION_ASC,
    ];

    /**
     * @var array
     * @ORM\Column(type="array")
     */
    public $meta = [
        'title' => '',
        'description' => '',
        'keywords' => '',
    ];

    /**
     * @var array
     * @ORM\Column(type="array")
     */
    public $template = [
        'list' => '',
        'short' => '',
        'full' => '',
    ];
}
