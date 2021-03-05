<?php declare(strict_types=1);

namespace App\Domain\Entities;

use App\Domain\AbstractEntity;
use DateTime;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;

/**
 * @ORM\Entity(repositoryClass="App\Domain\Repository\GuestBookRepository")
 * @ORM\Table(name="guestbook")
 */
class GuestBook extends AbstractEntity
{
    /**
     * @var Uuid
     * @ORM\Id
     * @ORM\Column(type="uuid")
     * @ORM\GeneratedValue(strategy="CUSTOM")
     * @ORM\CustomIdGenerator(class="Ramsey\Uuid\Doctrine\UuidGenerator")
     */
    protected Uuid $uuid;

    /**
     * @return Uuid
     */
    public function getUuid(): Uuid
    {
        return $this->uuid;
    }

    /**
     * @ORM\Column(type="string", options={"default": ""})
     */
    protected string $name = '';

    /**
     * @param string $name
     *
     * @return $this
     */
    public function setName(string $name)
    {
        if ($this->checkStrLenMax($name, 255)) {
            $this->name = $name;
        }

        return $this;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @ORM\Column(type="string", length=120, unique=true, options={"default": ""})
     */
    protected string $email = '';

    /**
     * @param string $email
     *
     * @throws \App\Domain\Exceptions\WrongEmailValueException
     *
     * @return $this
     */
    public function setEmail(string $email)
    {
        if ($this->checkStrLenMax($email, 120) && $this->checkEmailByValue($email)) {
            $this->email = $email;
        }

        return $this;
    }

    /**
     * @return string
     */
    public function getEmail(): string
    {
        return $this->email;
    }

    /**
     * @ORM\Column(type="text", options={"default": ""})
     */
    protected string $message = '';

    /**
     * @param string $message
     *
     * @return $this
     */
    public function setMessage(string $message)
    {
        $this->message = $message;

        return $this;
    }

    /**
     * @return string
     */
    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * @ORM\Column(type="text", options={"default": ""})
     */
    protected string $response = '';

    /**
     * @param string $response
     *
     * @return $this
     */
    public function setResponse(string $response)
    {
        $this->response = $response;

        return $this;
    }

    /**
     * @return string
     */
    public function getResponse(): string
    {
        return $this->response;
    }

    /**
     * @var string
     *
     * @see \App\Domain\Types\GuestBookStatusType::LIST
     * @ORM\Column(type="GuestBookStatusType", options={"default": \App\Domain\Types\GuestBookStatusType::STATUS_WORK})
     */
    protected string $status = \App\Domain\Types\GuestBookStatusType::STATUS_WORK;

    /**
     * @param string $status
     *
     * @return $this
     */
    public function setStatus(string $status)
    {
        if (in_array($status, \App\Domain\Types\GuestBookStatusType::LIST, true)) {
            $this->status = $status;
        }

        return $this;
    }

    /**
     * @return string
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * @var DateTime
     * @ORM\Column(type="datetime", options={"default": "CURRENT_TIMESTAMP"})
     */
    protected DateTime $date;

    /**
     * @param $date
     *
     * @throws \Exception
     *
     * @return $this
     */
    public function setDate($date)
    {
        $this->date = $this->getDateTimeByValue($date);

        return $this;
    }

    /**
     * @return DateTime
     */
    public function getDate()
    {
        return $this->date;
    }
}
