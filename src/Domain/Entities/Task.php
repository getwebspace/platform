<?php declare(strict_types=1);

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
     * @ORM\Column(type="string", options={"default": ""})
     */
    public $title = '';

    /**
     * @ORM\Column(type="string", options={"default": ""})
     */
    public $action = '';

    /**
     * @ORM\Column(type="integer", options={"default": 0})
     */
    public $progress = 0;

    /**
     * @var string
     *
     * @see \App\Domain\Types\TaskStatusType::LIST
     * @ORM\Column(type="TaskStatusType", options={"default": \App\Domain\Types\TaskStatusType::STATUS_QUEUE})
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
    public $date = null;

    public function getTitle()
    {
        if ($this->title) {
            return $this->title;
        }

        $action = explode('\\', $this->action);

        return end($action);
    }
}
