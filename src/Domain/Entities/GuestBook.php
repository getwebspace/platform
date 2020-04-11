<?php declare(strict_types=1);

namespace App\Domain\Entities;

use Alksily\Entity\Model;
use DateTime;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;

/**
 * @ORM\Entity
 * @ORM\Table(name="guestbook")
 */
class GuestBook extends Model
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
     * @ORM\Column(type="string", length=50, options={"default": ""})
     */
    public $name = '';

    /**
     * @ORM\Column(type="string", length=50, options={"default": ""})
     */
    public $email = '';

    /**
     * @ORM\Column(type="text", options={"default": ""})
     */
    public $message = '';

    /**
     * @ORM\Column(type="text", options={"default": ""})
     */
    public $response = '';

    /**
     * @var string
     *
     * @see \App\Domain\Types\GuestBookStatusType::LIST
     * @ORM\Column(type="GuestBookStatusType")
     */
    public $status = \App\Domain\Types\GuestBookStatusType::STATUS_WORK;

    /**
     * @var DateTime
     * @ORM\Column(type="datetime", options={"default": "CURRENT_TIMESTAMP"})
     */
    public $date;
}
