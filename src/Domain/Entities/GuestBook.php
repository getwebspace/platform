<?php

namespace App\Domain\Entities;

use AEngine\Entity\Model;
use DateTime;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\UuidInterface;

/**
 * @ORM\Entity
 * @ORM\Table(name="guestbook")
 */
class GuestBook extends Model
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
     * @ORM\Column(type="string", length=50)
     */
    public $name;

    /**
     * @ORM\Column(type="string", length=50)
     */
    public $email;

    /**
     * @ORM\Column(type="text")
     */
    public $message;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    public $response;

    /**
     * @var string
     * @see \App\Domain\Types\GuestBookStatusType::LIST
     * @ORM\Column(type="GuestBookStatusType", length=50)
     */
    public $status = \App\Domain\Types\GuestBookStatusType::STATUS_WORK;

    /**
     * @var DateTime
     * @ORM\Column(type="datetime", options={"default": "CURRENT_TIMESTAMP"})
     */
    public $date;
}
