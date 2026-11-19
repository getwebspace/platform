<?php declare(strict_types=1);

namespace App\Domain\McpTools;

use App\Domain\AbstractMcpTool;
use App\Domain\Service\GuestBook\GuestBookService;

class GuestbookCreateTool extends AbstractMcpTool
{
    public const NAME = 'guestbook_create';
    public const TITLE = 'Add guestbook entry';
    public const DESCRIPTION = 'Adds an entry to the guestbook on behalf of a person (name, e-mail and message are required, e.g. a letter that came by another channel). '
        . 'The entry always waits for moderation (guestbook_moderate publishes it) and nobody is notified. Never invent entries.';
    public const READ_ONLY = false;
    public const PRIVATE = true;
    public const SCOPE = 'guestbook';

    public function getInputSchema(): array
    {
        $string = ['type' => 'string'];

        return [
            'type' => 'object',
            'properties' => ['name' => $string, 'email' => $string, 'message' => $string],
            'required' => ['name', 'email', 'message'],
        ];
    }

    public function execute(array $args = []): mixed
    {
        $data = [];

        foreach (['name', 'email', 'message'] as $key) {
            if (blank($data[$key] = $this->textArgument($args, $key))) {
                throw new \InvalidArgumentException("Field {$key} is required");
            }
        }
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Field email is not a valid e-mail address');
        }

        // status is not accepted from the caller; the create event is not published, it mails the staff and the author
        $entry = $this->container->get(GuestBookService::class)->create($data + ['status' => \App\Domain\Casts\GuestBook\Status::MODERATE]);

        $this->logger->notice('Guestbook entry created via MCP', ['uuid' => (string) $entry->uuid]);

        return ['uuid' => (string) $entry->uuid, 'status' => $entry->status, 'name' => $entry->name];
    }
}
