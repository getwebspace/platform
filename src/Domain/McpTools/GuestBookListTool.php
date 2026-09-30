<?php declare(strict_types=1);

namespace App\Domain\McpTools;

use App\Domain\AbstractMcpTool;
use App\Domain\Casts\GuestBook\Status as GuestBookStatus;
use App\Domain\Models\GuestBook;
use App\Domain\Service\GuestBook\GuestBookService;

class GuestBookListTool extends AbstractMcpTool
{
    public const NAME = 'guestbook_list';
    public const TITLE = 'Guestbook entries';
    public const DESCRIPTION = 'Lists guestbook entries, newest first. By default - the ones waiting for moderation. Publish / hide / answer with guestbook_moderate.';
    public const PRIVATE = true;
    public const SCOPE = 'guestbook';

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'status' => ['type' => 'string', 'enum' => [...GuestBookStatus::LIST, 'all'], 'default' => GuestBookStatus::MODERATE, 'description' => 'moderate - waiting, work - published'],
                'search' => ['type' => 'string', 'description' => 'Part of name, e-mail or text'],
            ] + $this->paginationSchema(),
        ];
    }

    public function execute(array $args = []): mixed
    {
        $status = $args['status'] ?? GuestBookStatus::MODERATE;

        if ($status !== 'all' && !in_array($status, GuestBookStatus::LIST, true)) {
            throw new \InvalidArgumentException('Field status must be one of: ' . implode(', ', [...GuestBookStatus::LIST, 'all']));
        }

        $filter = $status === 'all' ? [] : ['status' => $status];

        if (!blank($search = trim((string) ($args['search'] ?? '')))) {
            $filter['search'] = $search;
        }

        /** @var GuestBookService $guestBookService */
        $guestBookService = $this->container->get(GuestBookService::class);

        return $this->paginate(
            $args,
            fn (int $limit, int $offset) => $guestBookService->read($filter + [
                'order' => ['date' => 'desc'],
                'limit' => $limit,
                'offset' => $offset,
            ]),
            fn (GuestBook $entry) => [
                'uuid' => (string) $entry->uuid,
                'status' => $entry->status,
                'date' => $entry->date?->format('Y-m-d H:i'),
                'name' => $entry->name,
                'email' => $entry->email,
                'message' => str_truncate((string) $entry->message, 600),
                'response' => $entry->response !== '' ? str_truncate((string) $entry->response, 600) : null,
            ]
        );
    }
}
