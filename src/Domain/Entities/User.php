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
     * @ORM\Column(type="boolean", options={"default": true})
     */
    public $allow_mail = true;

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
     * @var array
     * @ORM\ManyToMany(targetEntity="App\Domain\Entities\File", cascade={"persist", "remove"}, orphanRemoval=true)
     * @ORM\JoinTable(name="user_files",
     *  joinColumns={@ORM\JoinColumn(name="user_uuid", referencedColumnName="uuid")},
     *  inverseJoinColumns={@ORM\JoinColumn(name="file_uuid", referencedColumnName="uuid")}
     * )
     */
    protected $files = [];

    public function addFile(\App\Domain\Entities\File $file)
    {
        $this->files[] = $file;
    }

    public function addFiles(array $files)
    {
        foreach ($files as $file) {
            $this->addFile($file);
        }
    }

    public function removeFile(\App\Domain\Entities\File $file)
    {
        foreach ($this->files as $key => $value) {
            if ($file === $value) {
                unset($this->files[$key]);
                $value->unlink();
            }
        }
    }

    public function removeFiles(array $files)
    {
        foreach ($files as $file) {
            $this->removeFile($file);
        }
    }

    public function clearFiles()
    {
        foreach ($this->files as $key => $file) {
            unset($this->files[$key]);
            $file->unlink();
        }
    }

    public function getFiles($raw = false)
    {
        return $raw ? $this->files : collect($this->files);
    }

    public function hasFiles()
    {
        return count($this->files);
    }

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
                    return implode(' ', [mb_substr($this->lastname, 0, 1) . '.', $this->firstname]);
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
