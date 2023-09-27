<?php declare(strict_types=1);

namespace App\Domain\Entities;

use App\Domain\AbstractEntity;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\UuidInterface as Uuid;

#[ORM\Table(name: 'notification')]
#[ORM\Entity(repositoryClass: 'App\Domain\Repository\NotificationRepository')]
class Notification extends AbstractEntity
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'Ramsey\Uuid\Doctrine\UuidGenerator')]
    protected \Ramsey\Uuid\UuidInterface $uuid;

    public function getUuid(): \Ramsey\Uuid\UuidInterface
    {
        return $this->uuid;
    }

    /**
     * @var string|Uuid
     */
    #[ORM\Column(type: 'uuid', options: ['default' => \Ramsey\Uuid\Uuid::NIL])]
    protected $user_uuid = \Ramsey\Uuid\Uuid::NIL;

    /**
     * @param string|Uuid $uuid
     *
     * @return $this
     */
    public function setUserUuid($uuid)
    {
        $this->user_uuid = $this->getUuidByValue($uuid);

        return $this;
    }

    public function getUserUuid(): \Ramsey\Uuid\UuidInterface
    {
        return $this->user_uuid;
    }

    #[ORM\Column(type: 'string', length: 255, options: ['default' => ''])]
    protected string $title = '';

    /**
     * @return $this
     */
    public function setTitle(string $title)
    {
        if ($this->checkStrLenMax($title, 255) && $this->validName($title)) {
            $this->title = $title;
        }

        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    #[ORM\Column(type: 'text', length: 10000, options: ['default' => ''])]
    protected string $message = '';

    /**
     * @return $this
     */
    public function setMessage(string $message)
    {
        if ($this->checkStrLenMax($message, 10000) && $this->validText($message)) {
            $this->message = $message;
        }

        return $this;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    #[ORM\Column(type: 'json', options: ['default' => '{}'])]
    protected array $params = [];

    /**
     * @return $this
     */
    public function setParams(array $params)
    {
        $this->params = $params;

        return $this;
    }

    public function getParams(): array
    {
        return $this->params;
    }

    #[ORM\Column(type: 'datetime', options: ['default' => 'CURRENT_TIMESTAMP'])]
    protected \DateTime $date;

    /**
     * @param mixed $timezone
     * @param mixed $date
     *
     * @throws \Exception
     *
     * @return $this
     */
    public function setDate($date, $timezone = 'UTC')
    {
        $this->date = $this->getDateTimeByValue($date, $timezone);

        return $this;
    }

    /**
     * @return \DateTime
     */
    public function getDate()
    {
        return $this->date;
    }
}
