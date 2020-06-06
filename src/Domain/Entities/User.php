<?php declare(strict_types=1);

namespace App\Domain\Entities;

use App\Domain\AbstractEntity;
use App\Domain\Entities\User\Session as UserSession;
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
    protected Uuid $uuid;

    /**
     * @return Uuid
     */
    public function getUuid(): Uuid
    {
        return $this->uuid;
    }

    /**
     * @ORM\Column(type="string", length=50, options={"default": ""})
     */
    protected string $username = '';

    /**
     * @param string $username
     *
     * @return $this
     */
    public function setUsername(string $username)
    {
        if ($this->checkStrLenMax($username, 50)) {
            $this->username = $username;
        }

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
    protected string $phone = '';

    /**
     * @param string $phone
     *
     * @throws \App\Domain\Exceptions\WrongPhoneValueException
     *
     * @return $this
     */
    public function setPhone(string $phone)
    {
        if ($this->checkStrLenMax($phone, 25) && $this->checkPhoneByValue($phone)) {
            $this->phone = $phone;
        }

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
    protected string $password = '';

    /**
     * @param string $password
     *
     * @return $this
     */
    public function setPassword(string $password)
    {
        if ($password && $this->checkStrLenMax($password, 140)) {
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
    protected string $firstname = '';

    /**
     * @param string $firstname
     *
     * @return $this
     */
    public function setFirstname(string $firstname)
    {
        if ($this->checkStrLenMax($firstname, 50)) {
            $this->firstname = $firstname;
        }

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
    protected string $lastname = '';

    /**
     * @param string $lastname
     *
     * @return $this
     */
    public function setLastname(string $lastname)
    {
        if ($this->checkStrLenMax($lastname, 50)) {
            $this->lastname = $lastname;
        }

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

        return '';
    }

    /**
     * @ORM\Column(type="boolean", options={"default": true})
     */
    protected bool $allow_mail = true;

    /**
     * @param mixed $allow_mail
     *
     * @return $this
     */
    public function setAllowMail($allow_mail)
    {
        $this->allow_mail = $this->getBooleanByValue($allow_mail);

        return $this;
    }

    /**
     * @return bool
     */
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
    protected string $status = \App\Domain\Types\UserStatusType::STATUS_WORK;

    /**
     * @param string $status
     *
     * @return $this
     */
    public function setStatus(string $status)
    {
        if (in_array($status, \App\Domain\Types\UserStatusType::LIST, true)) {
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
     * @var string
     *
     * @see \App\Domain\Types\UserLevelType::LIST
     * @ORM\Column(type="UserLevelType", options={"default": \App\Domain\Types\UserLevelType::LEVEL_USER})
     */
    protected string $level = \App\Domain\Types\UserLevelType::LEVEL_USER;

    /**
     * @param string $level
     *
     * @return $this
     */
    public function setLevel(string $level)
    {
        if (in_array($level, \App\Domain\Types\UserLevelType::LIST, true)) {
            $this->level = $level;
        }

        return $this;
    }

    /**
     * @return string
     */
    public function getLevel()
    {
        return $this->level;
    }

    /**
     * @var DateTime
     * @ORM\Column(type="datetime", options={"default": "CURRENT_TIMESTAMP"})
     */
    protected DateTime $register;

    /**
     * @param $register
     *
     * @throws \Exception
     *
     * @return $this
     */
    public function setRegister($register)
    {
        $this->register = $this->getDateTimeByValue($register);

        return $this;
    }

    /**
     * @return DateTime
     */
    public function getRegister()
    {
        return $this->register;
    }

    /**
     * @var DateTime
     * @ORM\Column(name="`change`", type="datetime", options={"default": "CURRENT_TIMESTAMP"})
     */
    protected DateTime $change;

    /**
     * @param $change
     *
     * @throws \Exception
     *
     * @return $this
     */
    public function setChange($change)
    {
        $this->change = $this->getDateTimeByValue($change);

        return $this;
    }

    /**
     * @return DateTime
     */
    public function getChange()
    {
        return $this->change;
    }

    /**
     * @var UserSession
     * @ORM\OneToOne(targetEntity="App\Domain\Entities\User\Session")
     * @ORM\JoinColumn(name="uuid", referencedColumnName="uuid")
     */
    protected UserSession $session;

    /**
     * @param UserSession $session
     *
     * @return $this
     */
    public function setSession(UserSession $session)
    {
        $this->session = $session;

        return $this;
    }

    /**
     * @return UserSession
     */
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
    protected $files;

    /**
     * @param File $file
     *
     * @return $this
     */
    public function addFile(\App\Domain\Entities\File $file)
    {
        $this->files[] = $file;

        return $this;
    }

    /**
     * @param array $files
     *
     * @return $this
     */
    public function addFiles(array $files)
    {
        foreach ($files as $file) {
            $this->addFile($file);
        }

        return $this;
    }

    /**
     * @param File $file
     *
     * @return $this
     */
    public function removeFile(\App\Domain\Entities\File $file)
    {
        foreach ($this->files as $key => $value) {
            if ($file === $value) {
                unset($this->files[$key]);
                $value->unlink();
            }
        }

        return $this;
    }

    /**
     * @param array $files
     *
     * @return $this
     */
    public function removeFiles(array $files)
    {
        foreach ($files as $file) {
            $this->removeFile($file);
        }

        return $this;
    }

    /**
     * @return $this
     */
    public function clearFiles()
    {
        foreach ($this->files as $key => $file) {
            unset($this->files[$key]);
            $file->unlink();
        }

        return $this;
    }

    /**
     * @param bool $raw
     *
     * @return \Alksily\Entity\Collection|array
     */
    public function getFiles($raw = false)
    {
        return $raw ? $this->files : collect($this->files);
    }

    /**
     * @return int
     */
    public function hasFiles()
    {
        return count($this->files);
    }
}
