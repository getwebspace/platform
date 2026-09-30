<?php declare(strict_types=1);

namespace App\Domain\McpTools;

use App\Application\PubSub;
use App\Domain\AbstractMcpTool;
use App\Domain\Casts\GuestBook\Status as GuestBookStatus;
use App\Domain\Service\GuestBook\GuestBookService;

class GuestBookModerateTool extends AbstractMcpTool
{
    public const NAME = 'guestbook_moderate';
    public const TITLE = 'Moderate guestbook entry';
    public const DESCRIPTION = 'Publishes or hides a guestbook entry and can set the public answer of the administrator (an empty string removes it). '
        . 'Nothing is deleted. Show the text to the operator and get explicit confirmation before publishing or answering.';
    public const READ_ONLY = false;
    public const PRIVATE = true;
    public const SCOPE = 'guestbook';

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'uuid' => ['type' => 'string', 'description' => 'Uuid from guestbook_list'],
                'decision' => ['type' => 'string', 'enum' => ['publish', 'hide'], 'description' => 'publish - visible on the site, hide - back to moderation'],
                'response' => ['type' => 'string', 'description' => 'Public answer of the administrator'],
            ],
            'required' => ['uuid'],
        ];
    }

    public function execute(array $args = []): mixed
    {
        $decision = $args['decision'] ?? null;

        if ($decision !== null && !in_array($decision, ['publish', 'hide'], true)) {
            throw new \InvalidArgumentException('Field decision must be publish or hide');
        }
        if ($decision === null && !array_key_exists('response', $args)) {
            throw new \InvalidArgumentException('Pass decision and/or response');
        }
        if (!is_string($args['uuid'] ?? null) || !\Ramsey\Uuid\Uuid::isValid($args['uuid'])) {
            throw new \InvalidArgumentException('Field uuid is required');
        }

        /** @var GuestBookService $guestBookService */
        $guestBookService = $this->container->get(GuestBookService::class);
        $entry = $guestBookService->read(['uuid' => $args['uuid']]);

        $data = [];

        if ($decision !== null) {
            $data['status'] = $decision === 'publish' ? GuestBookStatus::WORK : GuestBookStatus::MODERATE;
        }
        if (array_key_exists('response', $args)) {
            $data['response'] = trim((string) $args['response']);
        }

        $entry = $guestBookService->update($entry, $data);

        $this->container->get(PubSub::class)->publish('api:guestbook:edit', $entry);
        $this->logger->notice('Guestbook entry moderated via MCP', ['uuid' => (string) $entry->uuid, 'decision' => $decision]);

        return [
            'uuid' => (string) $entry->uuid,
            'status' => $entry->status,
            'response' => $entry->response !== '' ? $entry->response : null,
        ];
    }
}
