<?php declare(strict_types=1);

namespace App\Domain\McpTools;

use App\Domain\AbstractMcpTool;
use App\Domain\Models\UserSubscriber;
use App\Domain\Service\User\SubscriberService;

class SubscriberListTool extends AbstractMcpTool
{
    public const NAME = 'subscriber_list';
    public const TITLE = 'Newsletter subscribers';
    public const DESCRIPTION = 'Lists e-mail subscribers of the site newsletter, newest first. Search by part of an e-mail. Mailings are not sent from tools - this is read only. Requires a full access API key.';
    public const PRIVATE = true;

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'search' => ['type' => 'string', 'description' => 'Part of an e-mail'],
            ] + $this->paginationSchema(),
        ];
    }

    public function execute(array $args = []): mixed
    {
        $filter = [];

        if (!blank($search = trim((string) ($args['search'] ?? '')))) {
            $filter['search'] = $search;
        }

        /** @var SubscriberService $subscriberService */
        $subscriberService = $this->container->get(SubscriberService::class);

        return $this->paginate(
            $args,
            fn (int $limit, int $offset) => $subscriberService->read($filter + [
                'order' => ['date' => 'desc'],
                'limit' => $limit,
                'offset' => $offset,
            ]),
            fn (UserSubscriber $subscriber) => [
                'uuid' => (string) $subscriber->uuid,
                'email' => $subscriber->email,
                'date' => datetime($subscriber->date)->format('Y-m-d H:i'),
            ]
        );
    }
}
