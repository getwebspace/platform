<?php

namespace Domain\Entities;

use AEngine\Entity\Model;
use DateTime;
use Doctrine\ORM\Mapping as ORM;
use Entity\User\Session;
use Ramsey\Uuid\UuidInterface;

/**
 * @ORM\Entity
 * @ORM\Table(name="user")
 */
class User extends Model
{
    /**
     * @var UuidInterface
     * @ORM\Id
     * @ORM\Column(type="uuid")
     * @ORM\GeneratedValue(strategy="CUSTOM")
     * @ORM\CustomIdGenerator(class="Ramsey\Uuid\Doctrine\UuidGenerator")
     */
    public $uuid;

    /**
     * @ORM\Column(type="string", length=50, unique=true)
     */
    public $username;

    /**
     * @ORM\Column(type="string", length=120, unique=true)
     */
    public $email;

    /**
     * @ORM\Column(type="string", length=140)
     */
    public $password;

    /**
     * @ORM\Column(type="string", length=50)
     */
    public $firstname;

    /**
     * @ORM\Column(type="string", length=50)
     */
    public $lastname;

    /**
     * @var string
     * @see \Domain\Types\UserStatusType::LIST
     * @ORM\Column(type="string", length=50)
     */
    public $status = \Domain\Types\UserStatusType::STATUS_WORK;

    /**
     * @var string
     * @see \Domain\Types\UserLevelType::LIST
     * @ORM\Column(type="string", length=50)
     */
    public $level = \Domain\Types\UserLevelType::LEVEL_USER;

    /**
     * @var DateTime
     * @ORM\Column(type="datetime", options={"default": "CURRENT_TIMESTAMP"})
     */
    public $register;

    /**
     * @var DateTime
     * @ORM\Column(type="datetime", options={"default": "CURRENT_TIMESTAMP"})
     */
    public $change;

    /**
     * @var \Domain\Entities\User\Session
     * @ORM\OneToOne(targetEntity="Domain\Entities\User\Session")
     * @ORM\JoinColumn(name="uuid", referencedColumnName="uuid")
     */
    public $session;

    public function __construct(array $data = [])
    {
        parent::__construct($data);

        $this->register = $this->register ?? new \DateTime('now');
    }

    /**
     * @return string
     */
    public function name($type = 'full')
    {
        switch ($type) {
            case 'full':
                return implode(' ', [$this->lastname, $this->firstname]);
                break;
            case 'short':
                return implode(' ', [substr($this->lastname, 0, 1) . '.', $this->firstname]);
                break;
        }

    }

    /**
     * Gravatar
     *
     * @param int $size
     *
     * @return string
     */
    public function avatar(int $size = 40) {
        return 'https://www.gravatar.com/avatar/' . md5(strtolower(trim($this->email))) . '?s=' . $size;
    }
}
