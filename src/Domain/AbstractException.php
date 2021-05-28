<?php declare(strict_types=1);

namespace App\Domain;

use Exception;
use Throwable;

abstract class AbstractException extends Exception
{
    protected string $title = '';

    protected string $description = '';

    public function __construct(string $message = '', ?Throwable $previous = null)
    {
        if ($message) {
            $this->message = $message;
        }

        parent::__construct($this->message, $this->code, $previous);
    }

    public function getTitle(): string
    {
        return $this->title ?: (new \ReflectionClass($this))->getShortName();
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function getDescription(): string
    {
        return $this->description ?: $this->getMessage();
    }

    public function setDescription(string $description): self
    {
        $this->description = $description;

        return $this;
    }
}
