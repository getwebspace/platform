<?php

namespace App\Domain\Entities;

use Alksily\Entity\Model;
use DateTime;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;

/**
 * @ORM\Entity
 * @ORM\Table(name="user")
 */
class User extends Model
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
     * @ORM\Column(type="string", length=50, unique=true)
     */
    public $username;

    /**
     * @ORM\Column(type="string", length=120, unique=true)
     */
    public $email;

    /**
     * @ORM\Column(type="string", length=25, nullable=true)
     */
    public $phone;

    /**
     * @ORM\Column(type="string", length=140)
     */
    public $password;

    /**
     * @ORM\Column(type="string", length=50, nullable=true)
     */
    public $firstname;

    /**
     * @ORM\Column(type="string", length=50, nullable=true)
     */
    public $lastname;

    /**
     * @var string
     * @see \App\Domain\Types\UserStatusType::LIST
     * @ORM\Column(type="UserStatusType")
     */
    public $status = \App\Domain\Types\UserStatusType::STATUS_WORK;

    /**
     * @var string
     * @see \App\Domain\Types\UserLevelType::LIST
     * @ORM\Column(type="UserLevelType")
     */
    public $level = \App\Domain\Types\UserLevelType::LEVEL_USER;

    /**
     * @var DateTime
     * @ORM\Column(type="datetime", options={"default": "CURRENT_TIMESTAMP"})
     */
    public $register;

    /**
     * @var DateTime
     * @ORM\Column(name="`change`", type="datetime", options={"default": "CURRENT_TIMESTAMP"})
     */
    public $change;

    /**
     * @var \App\Domain\Entities\User\Session
     * @ORM\OneToOne(targetEntity="App\Domain\Entities\User\Session")
     * @ORM\JoinColumn(name="uuid", referencedColumnName="uuid")
     */
    public $session;

    /**
     * @param String $type
     * @return string
     */
    public function getName(string $type = 'full')
    {
        if ($this->lastname || $this->firstname) {
            switch ($type) {
                case 'full':
                    return implode(' ', [$this->lastname, $this->firstname]);
                    break;
                case 'short':
                    return implode(' ', [substr($this->lastname, 0, 1) . '.', $this->firstname]);
                    break;
            }
        }

        return null;
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
