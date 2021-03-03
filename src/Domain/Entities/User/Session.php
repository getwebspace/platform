<?php declare(strict_types=1);

namespace App\Domain\Entities\User;

use App\Domain\AbstractEntity;
use App\Domain\Entities\User;
use DateTime;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;

/**
 * @ORM\Entity
 * @ORM\Table(name="user_session", uniqueConstraints={@ORM\UniqueConstraint(name="unique_uuid", columns={"uuid"})})
 */
class Session extends AbstractEntity
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
     * @var User
     * @ORM\OneToOne(targetEntity="App\Domain\Entities\User", inversedBy="session")
     * @ORM\JoinColumn(name="uuid", referencedColumnName="uuid")
     */
    protected User $user;

    /**
     * @param User $user
     *
     * @return $this
     */
    public function setUser(User $user)
    {
        if (is_a($user, User::class)) {
            $this->uuid = $user->getUuid();
            $this->user = $user;
        }

        return $this;
    }

    /**
     * @ORM\Column(type="string", length=16, options={"default": ""})
     */
    protected string $ip = '';

    /**
     * @param $ip
     *
     * @throws \App\Domain\Exceptions\WrongIpValueException
     *
     * @return $this
     */
    public function setIp($ip)
    {
        if ($this->checkStrLenMax($ip, 16) && $this->getIpByValue($ip)) {
            $this->ip = $ip;
        }

        return $this;
    }

    /**
     * @return string
     */
    public function getIp()
    {
        return $this->ip;
    }

    /**
     * @ORM\Column(type="string", length=256, options={"default": ""})
     */
    protected string $agent = '';

    /**
     * @param string $agent
     */
    public function setAgent(string $agent)
    {
        if ($this->checkStrLenMax($agent, 256)) {
            $this->agent = $agent;
        }

        return $this;
    }

    /**
     * @return string
     */
    public function getAgent()
    {
        return $this->agent;
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

    /**
     * @return string
     */
    public function getHash()
    {
        return sha1(
            'salt:' . ($_ENV['SALT'] ?? 'Li8.1Ej2-<Cid3[bE') . ';' .
            'uuid:' . $this->getUuid() . ';' .
            'ip:' . md5($this->getIp()) . ';' .
            'agent:' . md5($this->getAgent()) . ';' .
            'date:' . $this->getDate()->getTimestamp()
        );
    }

    public function toArray(): array
    {
        return [
            'ip' => $this->getIp(),
            'date' => $this->getDate()->format(\App\Domain\References\Date::DATETIME),
        ];
    }
}
