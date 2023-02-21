<?php declare(strict_types=1);

namespace App\Domain\Entities\User;

use App\Domain\AbstractEntity;
use App\Domain\Entities\User;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: 'user_token')]
#[ORM\Entity]
class Token extends AbstractEntity
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

    #[ORM\Column(type: 'uuid')]
    protected ?\Ramsey\Uuid\UuidInterface $user_uuid;

    #[ORM\ManyToOne(targetEntity: 'App\Domain\Entities\User')]
    #[ORM\JoinColumn(name: 'user_uuid', referencedColumnName: 'uuid')]
    protected ?User $user = null;

    /**
     * @param string|User $user
     *
     * @return $this
     */
    public function setUser($user): self
    {
        if (is_a($user, User::class)) {
            $this->user_uuid = $user->getUuid();
            $this->user = $user;
        }

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    #[ORM\Column(name: '`unique`', type: 'text', length: 1000, options: ['default' => ''])]
    protected string $unique = '';

    /**
     * @param mixed $unique
     *
     * @return $this
     */
    public function setUnique($unique): self
    {
        if ($this->checkStrLenMax($unique, 1000)) {
            $this->unique = (string) $unique;
        }

        return $this;
    }

    public function getUnique(): string
    {
        return $this->unique;
    }

    #[ORM\Column(type: 'text', options: ['default' => ''])]
    protected string $comment = '';

    /**
     * @param mixed $comment
     *
     * @return $this
     */
    public function setComment(string $comment): self
    {
        $this->comment = $comment;

        return $this;
    }

    public function getComment(): string
    {
        return $this->comment;
    }

    #[ORM\Column(type: 'string', length: 16, options: ['default' => ''])]
    protected string $ip = '';

    /**
     * @param mixed $ip
     *
     * @throws \App\Domain\Service\User\Exception\WrongIpValueException
     *
     * @return $this
     */
    public function setIp($ip)
    {
        try {
            if ($this->checkStrLenMax($ip, 16) && $this->getIpByValue($ip)) {
                $this->ip = $ip;
            }
        } catch (\RuntimeException $e) {
            throw new \App\Domain\Service\User\Exception\WrongIpValueException();
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

    #[ORM\Column(type: 'string', length: 256, options: ['default' => ''])]
    protected string $agent = '';

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
    public function setDate($date, $timezone = 'UTC'): self
    {
        $this->date = $this->getDateTimeByValue($date, $timezone);

        return $this;
    }

    public function getDate(): \DateTime
    {
        return $this->date;
    }

    public function toArray(): array
    {
        return array_serialize([
            'unique' => $this->getUnique(),
            'date' => $this->getDate(),
        ]);
    }
}
