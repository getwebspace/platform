<?php declare(strict_types=1);

namespace App\Domain\Entities;

use App\Domain\AbstractEntity;
use DateTime;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;

/**
 * @ORM\Entity
 * @ORM\Table(name="notification")
 */
class Notification extends AbstractEntity
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
     * @ORM\Column(type="uuid", options={"default": \Ramsey\Uuid\Uuid::NIL})
     */
    public $user_uuid = \Ramsey\Uuid\Uuid::NIL;

    /**
     * @var string
     * @ORM\Column(type="string", options={"default": ""})
     */
    public $title = '';

    /**
     * @var string
     * @ORM\Column(type="text", options={"default": ""})
     */
    public $message = '';

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
