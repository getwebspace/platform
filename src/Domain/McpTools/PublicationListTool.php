<?php declare(strict_types=1);

namespace App\Domain\McpTools;

use App\Domain\AbstractMcpTool;
use App\Domain\McpTools\Concerns\DescribesPublications;
use App\Domain\Models\Publication;
use App\Domain\Service\Publication\PublicationService;

class PublicationListTool extends AbstractMcpTool
{
    use DescribesPublications;

    public const NAME = 'publication_list';
    public const TITLE = 'Publications';
    public const DESCRIPTION = 'Lists publications (news, articles), newest first, with state: published, scheduled (date in the future) or draft (created by publication_create without publishing). '
        . 'Search by title or address. Use publication_get for the text.';
    public const PRIVATE = true;
    public const SCOPE = 'publication';

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'state' => ['type' => 'string', 'enum' => ['all', 'published', 'draft'], 'default' => 'all'],
                'category_uuid' => ['type' => 'string', 'description' => 'See publication_category in the admin panel; the category is also shown in every item'],
                'search' => ['type' => 'string', 'description' => 'Part of title or address'],
            ] + $this->paginationSchema(),
        ];
    }

    public function execute(array $args = []): mixed
    {
        $state = $args['state'] ?? 'all';

        if (!in_array($state, ['all', 'published', 'draft'], true)) {
            throw new \InvalidArgumentException('Field state must be one of: all, published, draft');
        }

        $filter = [];

        if (!blank($args['category_uuid'] ?? null)) {
            $filter['category_uuid'] = [(string) $args['category_uuid']];
        }
        if (!blank($search = trim((string) ($args['search'] ?? '')))) {
            $filter['search'] = $search;
        }

        /** @var PublicationService $publicationService */
        $publicationService = $this->container->get(PublicationService::class);

        return $this->paginate(
            $args,
            fn (int $limit, int $offset) => $publicationService->read($filter + [
                'with' => ['category'],
                'order' => ['date' => 'desc'],
                'limit' => $limit,
                'offset' => $offset,
            ] + match ($state) {
                'published' => ['date_to' => datetime()->addYears(50)->toDateTimeString()],
                'draft' => ['date_from' => datetime()->addYears(50)->toDateTimeString()],
                default => [],
            }),
            fn (Publication $publication) => $this->publicationCard($publication)
        );
    }
}
