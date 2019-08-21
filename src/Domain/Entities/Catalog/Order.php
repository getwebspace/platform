<?php

namespace Domain\Entities\Catalog;

use AEngine\Entity\Model;
use DateTime;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\UuidInterface;

/**
 * @ORM\Entity
 * @ORM\Table(name="catalog_order", indexes={
 *     @ORM\Index(name="status_idx", columns={"status"}),
 * })
 */
class Order extends Model
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
     * @var UuidInterface
     * @ORM\Column(type="uuid", options={"default": \Ramsey\Uuid\Uuid::NIL})
     */
    public $user;

    /**
     * @var string
     * @ORM\Column(type="string", length=500)
     */
    public $delivery;

    /**
     * @var DateTime
     * @ORM\Column(type="datetime", options={"default": "CURRENT_TIMESTAMP"}, nullable=true)
     */
    public $shipping;

    /**
     * @var string
     * @ORM\Column(type="string", length=500)
     */
    public $comment;

    /**
     * @var array
     * @ORM\Column(type="array")
     */
    public $items = [];

    /**
     * @var string
     * @see \Domain\Types\OrderStatusType::LIST
     * @ORM\Column(type="OrderStatusType", length=50)
     */
    public $status = \Domain\Types\OrderStatusType::STATUS_NEW;

    /**
     * @var DateTime
     * @ORM\Column(type="datetime", options={"default": "CURRENT_TIMESTAMP"}, nullable=true)
     */
    public $date;

    /**
     * @ORM\Column(type="string", length=50, nullable=true)
     */
    public $external_id;
}
