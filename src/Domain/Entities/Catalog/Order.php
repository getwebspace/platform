<?php

namespace App\Domain\Entities\Catalog;

use AEngine\Entity\Model;
use DateTime;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;

/**
 * @ORM\Entity
 * @ORM\Table(name="catalog_order", indexes={
 *     @ORM\Index(name="status_idx", columns={"status"}),
 * })
 */
class Order extends Model
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
     * @var string
     * @ORM\Column(type="string", length=7)
     */
    public $serial;

    /**
     * @var Uuid
     * @ORM\Column(type="uuid", options={"default": NULL}, nullable=true)
     */
    public $user_uuid;

    /**
     * @ORM\OneToOne(targetEntity="\App\Domain\Entities\User")
     * @ORM\JoinColumn(name="user_uuid", referencedColumnName="uuid")
     */
    public $user;

    /**
     * @var string
     * @ORM\Column(type="array", nullable=true)
     */
    public $delivery = [
        'client' => '',
        'address' => '',
    ];

    /**
     * @var DateTime
     * @ORM\Column(type="datetime", options={"default": "CURRENT_TIMESTAMP"}, nullable=true)
     */
    public $shipping;

    /**
     * @var string
     * @ORM\Column(type="string", length=500, nullable=true)
     */
    public $comment;

    /**
     * @var string
     * @ORM\Column(type="string", length=50, nullable=true)
     */
    public $phone;

    /**
     * @var string
     * @ORM\Column(type="string", length=50, nullable=true)
     */
    public $email;

    /**
     * @var array
     * @ORM\Column(name="`list`", type="array")
     */
    public $list = [
        // 'uuid' => 'count',
    ];

    /**
     * @var string
     * @see \App\Domain\Types\Catalog\OrderStatusType::LIST
     * @ORM\Column(type="CatalogOrderStatusType", length=50)
     */
    public $status = \App\Domain\Types\Catalog\OrderStatusType::STATUS_NEW;

    /**
     * @var DateTime
     * @ORM\Column(type="datetime", options={"default": "CURRENT_TIMESTAMP"})
     */
    public $date;

    /**
     * @ORM\Column(type="string", length=50, nullable=true)
     */
    public $external_id;

    /**
     * @ORM\Column(type="string", length=50, nullable=true)
     */
    public $export = 'manual';
}
