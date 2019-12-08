<?php

namespace App\Domain\Entities;

use Alksily\Entity\Model;
use DateTime;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;

/**
 * @ORM\Entity
 * @ORM\Table(name="task")
 */
class Task extends Model
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
     * @ORM\Column(type="string", nullable=true)
     */
    public $action;

    /**
     * @var string
     * @see \App\Domain\Types\TaskStatusType::LIST
     * @ORM\Column(type="TaskStatusType")
     */
    public $status = \App\Domain\Types\TaskStatusType::STATUS_QUEUE;

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
