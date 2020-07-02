<?php declare(strict_types=1);

namespace App\Domain\Entities\Catalog;

use App\Domain\AbstractEntity;
use DateTime;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;

/**
 * @ORM\Entity
 * @ORM\Table(name="catalog_order", indexes={
 *     @ORM\Index(name="catalog_order_status_idx", columns={"status"}),
 * })
 */
class Order extends AbstractEntity
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
     * @ORM\Column(type="string", length=7, options={"default": ""})
     */
    public $serial = '';

    /**
     * @var Uuid
     * @ORM\Column(type="uuid", options={"default": \Ramsey\Uuid\Uuid::NIL}, nullable=true)
     */
    public $user_uuid = \Ramsey\Uuid\Uuid::NIL;

    /**
     * @ORM\ManyToOne(targetEntity="\App\Domain\Entities\User")
     * @ORM\JoinColumn(name="user_uuid", referencedColumnName="uuid")
     */
    public $user;

    /**
     * @var array
     * @ORM\Column(type="array")
     */
    public $delivery = [
        'client' => '',
        'address' => '',
    ];

    /**
     * @var DateTime
     * @ORM\Column(type="datetime", options={"default": "CURRENT_TIMESTAMP"})
     */
    public $shipping = '';

    /**
     * @var string
     * @ORM\Column(type="string", length=500, options={"default": ""})
     */
    public $comment = '';

    /**
     * @var string
     * @ORM\Column(type="string", length=50, options={"default": ""})
     */
    public $phone = '';

    /**
     * @var string
     * @ORM\Column(type="string", length=50, options={"default": ""})
     */
    public $email = '';

    /**
     * @var array
     * @ORM\Column(name="`list`", type="array")
     */
    public $list = [
        // 'uuid' => 'count',
    ];

    /**
     * @var string
     *
     * @see \App\Domain\Types\Catalog\OrderStatusType::LIST
     * @ORM\Column(type="CatalogOrderStatusType", options={"default": \App\Domain\Types\Catalog\OrderStatusType::STATUS_NEW})
     */
    public $status = \App\Domain\Types\Catalog\OrderStatusType::STATUS_NEW;

    /**
     * @var DateTime
     * @ORM\Column(type="datetime", options={"default": "CURRENT_TIMESTAMP"})
     */
    public $date;

    /**
     * @ORM\Column(type="string", length=50, options={"default": ""})
     */
    public $external_id = '';

    /**
     * @ORM\Column(type="string", length=50, options={"default": ""})
     */
    public $export = 'manual';
}
