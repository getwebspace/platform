<?php declare(strict_types=1);

namespace App\Domain\Entities;

use App\Domain\AbstractEntity;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: 'params')]
#[ORM\Entity(repositoryClass: 'App\Domain\Repository\ParameterRepository')]
class Parameter extends AbstractEntity
{
    #[ORM\Id]
    #[ORM\Column(name: 'name', type: 'string', length: 50, unique: true, options: ['default' => ''])]
    protected string $key = '';

    /**
     * @return $this
     */
    public function setKey(string $key): static
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

    #[ORM\Column(type: 'text', length: 100000, options: ['default' => ''])]
    public string $value = '';

    /**
     * @return $this
     */
    public function setValue(mixed $value): static
    {
        $value = (string) $value;

        if ($this->checkStrLenMax($value, 100000)) {
            $this->value = $value;
        }

        return $this;
    }

    public function getValue(): string
    {
        return $this->value;
    }
}
