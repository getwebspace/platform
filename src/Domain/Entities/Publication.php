<?php

namespace Domain\Entities;

use AEngine\Entity\Model;
use DateTime;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\UuidInterface;

/**
 * @ORM\Entity
 * @ORM\Table(name="publication")
 */
class Publication extends Model
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
     * @ORM\Column(type="string", unique=true)
     */
    public $address;

    /**
     * @ORM\Column(type="string")
     */
    public $title;

    /**
     * @ORM\Column(type="string", length=36)
     */
    public $category;

    /**
     * @var DateTime
     * @ORM\Column(type="datetime", options={"default": "CURRENT_TIMESTAMP"})
     */
    public $date;

    /**
     * @var array
     * @ORM\Column(type="array")
     */
    public $content = [
        'short' => '',
        'full' => '',
    ];

    /**
     * @var array
     * @ORM\Column(type="array")
     */
    public $poll = [
        'question' => '',
        'answer' => '',
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
}
