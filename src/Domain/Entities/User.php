<?php declare(strict_types=1);

namespace App\Domain\Entities;

use App\Domain\AbstractEntity;
use DateTime;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;

/**
 * @ORM\Entity(repositoryClass="App\Domain\Repository\UserRepository")
 * @ORM\Table(name="user")
 */
class User extends AbstractEntity
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
     * @ORM\Column(type="string", length=50, options={"default": ""})
     */
    private $username = '';

    /**
     * @param string $username
     */
    public function setUsername(string $username)
    {
        $this->username = $username;

        return $this;
    }

    /**
     * @return string
     */
    public function getUsername(): string
    {
        return $this->username;
    }

    /**
     * @ORM\Column(type="string", length=120, unique=true, options={"default": ""})
     */
    private $email = '';

    /**
     * @param string $email
     */
    public function setEmail(string $email)
    {
        $this->email = $this->getEmailByValue($email);

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
     * Gravatar
     *
     * @param int $size
     *
     * @return string
     */
    public function avatar(int $size = 40)
    {
        return 'https://www.gravatar.com/avatar/' . md5(mb_strtolower(trim($this->email))) . '?s=' . $size;
    }

    /**
     * @ORM\Column(type="string", length=25, options={"default": ""})
     */
    private $phone = '';

    /**
     * @param string $phone
     */
    public function setPhone(string $phone)
    {
        $this->phone = $this->checkPhoneByValue($phone);

        return $this;
    }

    /**
     * @return string
     */
    public function getPhone(): string
    {
        return $this->phone;
    }

    /**
     * @ORM\Column(type="string", length=140, options={"default": ""})
     */
    private $password = '';

    /**
     * @param string $password
     */
    public function setPassword(string $password)
    {
        if ($password) {
            $this->password = $this->getPasswordHashByValue($password);
        }

        return $this;
    }

    /**
     * @return string
     */
    public function getPassword(): string
    {
        return $this->password;
    }

    /**
     * @ORM\Column(type="string", length=50, options={"default": ""})
     */
    private $firstname = '';

    /**
     * @param string $firstname
     */
    public function setFirstname(string $firstname)
    {
        $this->firstname = $firstname;

        return $this;
    }

    /**
     * @return string
     */
    public function getFirstname(): string
    {
        return $this->firstname;
    }

    /**
     * @ORM\Column(type="string", length=50, options={"default": ""})
     */
    private $lastname = '';

    /**
     * @param string $lastname
     */
    public function setLastname(string $lastname)
    {
        $this->lastname = $lastname;

        return $this;
    }

    /**
     * @return string
     */
    public function getLastname(): string
    {
        return $this->lastname;
    }

    /**
     * @param string $type
     *
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
     * @ORM\Column(type="boolean", options={"default": true})
     */
    private $allow_mail = true;

    public function setAllowMail($allow_mail)
    {
        $this->allow_mail = $this->getBooleanByValue($allow_mail);

        return $this;
    }

    public function getAllowMail()
    {
        return $this->allow_mail;
    }

    /**
     * @var string
     *
     * @see \App\Domain\Types\UserStatusType::LIST
     * @ORM\Column(type="UserStatusType", options={"default": \App\Domain\Types\UserStatusType::STATUS_WORK})
     */
    private $status = \App\Domain\Types\UserStatusType::STATUS_WORK;

    public function setStatus(string $status)
    {
        if (in_array($status, \App\Domain\Types\UserStatusType::LIST, true)) {
            $this->status = $status;
        }

        return $this;
    }

    public function getStatus()
    {
        return $this->status;
    }

    /**
     * @var string
     *
     * @see \App\Domain\Types\UserLevelType::LIST
     * @ORM\Column(type="UserLevelType", options={"default": \App\Domain\Types\UserLevelType::LEVEL_USER})
     */
    private $level = \App\Domain\Types\UserLevelType::LEVEL_USER;

    public function setLevel(string $level)
    {
        if (in_array($level, \App\Domain\Types\UserLevelType::LIST, true)) {
            $this->level = $level;
        }

        return $this;
    }

    public function getLevel()
    {
        return $this->level;
    }

    /**
     * @var DateTime
     * @ORM\Column(type="datetime", options={"default": "CURRENT_TIMESTAMP"})
     */
    private $register;

    public function setRegister($register)
    {
        $this->register = $this->getDateTimeByValue($register);

        return $this;
    }

    public function getRegister()
    {
        return $this->register;
    }

    /**
     * @var DateTime
     * @ORM\Column(name="`change`", type="datetime", options={"default": "CURRENT_TIMESTAMP"})
     */
    private $change;

    public function setChange($change)
    {
        $this->change = $this->getDateTimeByValue($change);

        return $this;
    }

    public function getChange()
    {
        return $this->change;
    }

    /**
     * @var \App\Domain\Entities\User\Session
     * @ORM\OneToOne(targetEntity="App\Domain\Entities\User\Session")
     * @ORM\JoinColumn(name="uuid", referencedColumnName="uuid")
     */
    private $session;

    public function setSession(\App\Domain\Entities\User\Session $session)
    {
        $this->session = $session;

        return $this;
    }

    public function getSession()
    {
        return $this->session;
    }

    /**
     * @var array
     * @ORM\ManyToMany(targetEntity="App\Domain\Entities\File", cascade={"persist", "remove"}, orphanRemoval=true)
     * @ORM\JoinTable(name="user_files",
     *     joinColumns={@ORM\JoinColumn(name="user_uuid", referencedColumnName="uuid")},
     *     inverseJoinColumns={@ORM\JoinColumn(name="file_uuid", referencedColumnName="uuid")}
     * )
     */
    private $files = [];

    public function addFile(\App\Domain\Entities\File $file): void
    {
        $this->files[] = $file;
    }

    public function addFiles(array $files): void
    {
        foreach ($files as $file) {
            $this->addFile($file);
        }
    }

    public function removeFile(\App\Domain\Entities\File $file): void
    {
        foreach ($this->files as $key => $value) {
            if ($file === $value) {
                unset($this->files[$key]);
                $value->unlink();
            }
        }
    }

    public function removeFiles(array $files): void
    {
        foreach ($files as $file) {
            $this->removeFile($file);
        }
    }

    public function clearFiles(): void
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
}
