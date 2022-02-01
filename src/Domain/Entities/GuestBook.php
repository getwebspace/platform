<?php declare(strict_types=1);

namespace App\Domain\Entities;

use App\Domain\AbstractEntity;
use DateTime;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\UuidInterface as Uuid;
use RuntimeException;

/**
 * @ORM\Entity(repositoryClass="App\Domain\Repository\GuestBookRepository")
 * @ORM\Table(name="guestbook")
 */
class GuestBook extends AbstractEntity
{
    /**
     * @ORM\Id
     * @ORM\Column(type="uuid")
     * @ORM\GeneratedValue(strategy="CUSTOM")
     * @ORM\CustomIdGenerator(class="Ramsey\Uuid\Doctrine\UuidGenerator")
     */
    protected \Ramsey\Uuid\UuidInterface $uuid;

    public function getUuid(): \Ramsey\Uuid\UuidInterface
    {
        return $this->uuid;
    }

    /**
     * @ORM\Column(type="string", options={"default": ""})
     */
    protected string $name = '';

    /**
     * @return $this
     */
    public function setName(string $name)
    {
        if ($this->checkStrLenMax($name, 255)) {
            $this->name = $name;
        }

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @ORM\Column(type="string", length=120, options={"default": ""})
     */
    protected string $email = '';

    /**
     * @throws \App\Domain\Service\GuestBook\Exception\WrongEmailValueException
     *
     * @return $this
     */
    public function setEmail(string $email)
    {
        try {
            if ($this->checkStrLenMax($email, 120) && $this->checkEmailByValue($email)) {
                $this->email = $email;
            }
        } catch (RuntimeException $e) {
            throw new \App\Domain\Service\GuestBook\Exception\WrongEmailValueException();
        }

        return $this;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    /**
     * @ORM\Column(type="text", options={"default": ""})
     */
    protected string $message = '';

    /**
     * @return $this
     */
    public function setMessage(string $message)
    {
        $this->message = $message;

        return $this;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * @ORM\Column(type="text", options={"default": ""})
     */
    protected string $response = '';

    /**
     * @return $this
     */
    public function setResponse(string $response)
    {
        $this->response = $response;

        return $this;
    }

    public function getResponse(): string
    {
        return $this->response;
    }

    /**
     * @see \App\Domain\Types\GuestBookStatusType::LIST
     * @ORM\Column(type="GuestBookStatusType", options={"default": \App\Domain\Types\GuestBookStatusType::STATUS_WORK})
     */
    protected string $status = \App\Domain\Types\GuestBookStatusType::STATUS_WORK;

    /**
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
     * @ORM\Column(type="datetime", options={"default": "CURRENT_TIMESTAMP"})
     */
    protected DateTime $date;

    /**
     * @param $date
     * @param mixed $timezone
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
     * @return DateTime
     */
    public function getDate()
    {
        return $this->date;
    }
}
