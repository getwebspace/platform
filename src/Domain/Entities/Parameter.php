<?php declare(strict_types=1);

namespace App\Domain\Entities;

use App\Domain\AbstractEntity;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Domain\Repository\ParameterRepository")
 * @ORM\Table(name="params")
 */
class Parameter extends AbstractEntity
{
    /**
     * @ORM\Id
     * @ORM\Column(name="name", type="string", length=50, unique=true, options={"default": ""})
     */
    protected string $key = '';

    /**
     * @return $this
     */
    public function setKey(string $key)
    {
        if ($this->checkStrLenMax($key, 50)) {
            $this->key = $key;
        }

        return $this;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    /**
     * @ORM\Column(type="text", length=3000, options={"default": ""})
     */
    public string $value = '';

    /**
     * @param mixed $value
     *
     * @return $this
     */
    public function setValue($value)
    {
        $value = (string) $value;

        if ($this->checkStrLenMax($value, 3000)) {
            $this->value = $value;
        }

        return $this;
    }

    public function getValue(): string
    {
        return $this->value;
    }
}
