<?php

namespace App\Domain\Entities;

use Alksily\Entity\Model;
use DateTime;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;

/**
 * @ORM\Entity
 * @ORM\Table(name="notification")
 */
class Notification extends Model
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
     * @var Uuid
     * @ORM\Column(type="uuid", options={"default": NULL}, nullable=true)
     */
    public $user_uuid;

    /**
     * @var string
     * @ORM\Column(type="string", nullable=true)
     */
    public $title;

    /**
     * @var string
     * @ORM\Column(type="text", nullable=true)
     */
    public $message;

    /**
     * @ORM\Column(type="array")
     */
    public $params = [];

    /**
     * @var DateTime
     * @ORM\Column(type="datetime", options={"default": "CURRENT_TIMESTAMP"})
     */
    public $date;
}
