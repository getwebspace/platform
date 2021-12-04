<?php
declare(strict_types=1);

namespace App\Application\Actions;

use JsonSerializable;

class ActionPayload implements JsonSerializable
{
    /**
     * @var int
     */
    private $statusCode;

    /**
     * @var null|array|object
     */
    private $data;

    /**
     * @var null|ActionError
     */
    private $error;

    /**
     * @param null|array|object $data
     */
    public function __construct(
        int $statusCode = 200,
        $data = null,
        ?ActionError $error = null
    ) {
        $this->statusCode = $statusCode;
        $this->data = $data;
        $this->error = $error;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * @return null|array|object
     */
    public function getData()
    {
        return $this->data;
    }

    public function getError(): ?ActionError
    {
        return $this->error;
    }

    public function jsonSerialize(): array
    {
        $payload = [
            'statusCode' => $this->statusCode,
        ];

        if ($this->data !== null) {
            $payload['data'] = $this->data;
        } elseif ($this->error !== null) {
            $payload['error'] = $this->error;
        }

        return $payload;
    }
}
