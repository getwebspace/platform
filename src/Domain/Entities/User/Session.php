<?php declare(strict_types=1);

namespace App\Domain\Entities\User;

use App\Domain\AbstractEntity;
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
    private $uuid;

    public function getUuid(): Uuid
    {
        return $this->uuid;
    }

    /**
     * @ORM\Column(type="string", length=16, options={"default": ""})
     */
    private $ip = '';

    public function setIp($ip)
    {
        $this->ip = $this->getIpByValue($ip);

        return $this;
    }

    public function getIp()
    {
        return $this->ip;
    }

    /**
     * @ORM\Column(type="string", length=256, options={"default": ""})
     */
    private $agent = '';

    public function setAgent(string $agent)
    {
        $this->agent = $agent;

        return $this;
    }

    public function getAgent()
    {
        return $this->agent;
    }

    /**
     * @var DateTime
     * @ORM\Column(type="datetime", options={"default": "CURRENT_TIMESTAMP"})
     */
    private $date;

    public function setDate($date)
    {
        $this->date = $this->getDateTimeByValue($date);

        return $this;
    }

    public function getDate()
    {
        return $this->date;
    }

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
}
