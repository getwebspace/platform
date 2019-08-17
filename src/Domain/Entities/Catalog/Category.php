<?php

namespace Domain\Entities\Catalog;

use AEngine\Entity\Model;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\UuidInterface;

/**
 * @ORM\Entity
 * @ORM\Table(name="catalog_category", indexes={
 *     @ORM\Index(name="parent_idx", columns={"parent"}),
 *     @ORM\Index(name="order_idx", columns={"order"})
 * })
 */
class Category extends Model
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
     * @ORM\Column(type="string", length=36)
     */
    public $parent;

    /**
     * @ORM\Column(type="string")
     */
    public $title;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    public $description;

    /**
     * @ORM\Column(type="string")
     */
    public $address;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    public $field1;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    public $field2;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    public $field3;

    /**
     * @var array
     * @ORM\Column(type="array")
     */
    public $product = [
        'field_1' => '',
        'field_2' => '',
        'field_3' => '',
        'field_4' => '',
        'field_5' => '',
    ];

    /**
     * @ORM\Column(name="`order`", type="integer")
     */
    public $order;

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
     * @ORM\Column(type="string", length=50)
     */
    public $template;

    /**
     * @ORM\Column(type="string", length=50, nullable=true)
     */
    public $external_id;
}
