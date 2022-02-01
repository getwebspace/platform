<?php declare(strict_types=1);

namespace App\Domain\Entities\User;

use App\Domain\AbstractEntity;
use App\Domain\Entities\User;
use DateTime;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\UuidInterface as Uuid;

/**
 * @ORM\Entity
 * @ORM\Table(name="user_integration",
 *     uniqueConstraints={
 *         @ORM\UniqueConstraint(name="user_provider_unique", columns={"user_uuid", "provider", "unique"}),
 *     }
 * )
 */
class Integration extends AbstractEntity
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
     * @ORM\Column(type="uuid")
     */
    protected ?\Ramsey\Uuid\UuidInterface $user_uuid;

    /**
     * @ORM\ManyToOne(targetEntity="App\Domain\Entities\User")
     * @ORM\JoinColumn(name="user_uuid", referencedColumnName="uuid")
     */
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

    /**
     * @ORM\Column(type="text", options={"default": ""})
     */
    protected string $provider = '';

    /**
     * @param $provider
     *
     * @return $this
     */
    public function setProvider($provider): self
    {
        $this->provider = $provider;

        return $this;
    }

    public function getProvider(): string
    {
        return $this->provider;
    }

    /**
     * @ORM\Column(name="`unique`", type="string", length=20, options={"default": ""})
     */
    protected string $unique = '';

    /**
     * @param $unique
     *
     * @return $this
     */
    public function setUnique($unique): self
    {
        $this->unique = (string) $unique;

        return $this;
    }

    public function getUnique(): string
    {
        return $this->unique;
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
    public function setDate($date, $timezone = 'UTC'): self
    {
        $this->date = $this->getDateTimeByValue($date, $timezone);

        return $this;
    }

    public function getDate(): DateTime
    {
        return $this->date;
    }

    public function toArray(): array
    {
        return [
            'provider' => $this->getProvider(),
            'unique' => $this->getUnique(),
            'date' => $this->getDate(),
        ];
    }
}
