<?php declare(strict_types=1);

namespace App\Domain\McpTools;

use App\Domain\AbstractMcpTool;
use App\Domain\Casts\Review\EntityType as ReviewEntityType;
use App\Domain\Casts\Review\Status as ReviewStatus;
use App\Domain\Casts\Review\Type as ReviewType;
use App\Domain\Models\CatalogProduct;
use App\Domain\Models\Publication;
use App\Domain\Models\Review;
use App\Domain\Service\Review\ReviewService;

class ReviewListTool extends AbstractMcpTool
{
    public const NAME = 'review_list';
    public const TITLE = 'Reviews and questions';
    public const DESCRIPTION = 'Lists reviews and questions of products and publications, newest first. By default - the ones waiting for moderation. '
        . 'Each item has the text, rating, author, what it is about and the current reply of the administrator, if any. Approve / hide / answer with review_moderate.';
    public const SCOPE = 'review';

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'status' => ['type' => 'string', 'enum' => [...ReviewStatus::LIST, 'all'], 'default' => ReviewStatus::MODERATE, 'description' => 'moderate - waiting, work - published'],
                'type' => ['type' => 'string', 'enum' => ReviewType::LIST],
                'entity_type' => ['type' => 'string', 'enum' => ReviewEntityType::LIST],
                'entity_uuid' => ['type' => 'string', 'description' => 'Product or publication uuid'],
                'search' => ['type' => 'string', 'description' => 'Part of the text'],
            ] + $this->paginationSchema(),
        ];
    }

    public function execute(array $args = []): mixed
    {
        $status = $args['status'] ?? ReviewStatus::MODERATE;

        if ($status !== 'all' && !in_array($status, ReviewStatus::LIST, true)) {
            throw new \InvalidArgumentException('Field status must be one of: ' . implode(', ', [...ReviewStatus::LIST, 'all']));
        }

        // top-level entries only, replies are shown inside
        $filter = ['parent_uuid' => false];

        if ($status !== 'all') {
            $filter['status'] = $status;
        }
        foreach (['type', 'entity_type', 'entity_uuid'] as $key) {
            if (!blank($args[$key] ?? null)) {
                $filter[$key] = $key === 'entity_uuid' ? (string) $args[$key] : [(string) $args[$key]];
            }
        }
        if (!blank($search = trim((string) ($args['search'] ?? '')))) {
            $filter['search'] = $search;
        }

        /** @var ReviewService $reviewService */
        $reviewService = $this->container->get(ReviewService::class);

        return $this->paginate(
            $args,
            function (int $limit, int $offset) use ($reviewService, $filter) {
                $rows = $reviewService->read($filter + [
                    'with' => ['user', 'children'],
                    'order' => ['date' => 'desc'],
                    'limit' => $limit,
                    'offset' => $offset,
                ]);

                $this->titles = $this->entityTitles($rows);

                return $rows;
            },
            fn (Review $review) => [
                'uuid' => (string) $review->uuid,
                'type' => $review->type,
                'status' => $review->status,
                'date' => $review->date?->format('Y-m-d H:i'),
                'about' => [
                    'type' => $review->entity_type,
                    'uuid' => (string) $review->entity_uuid,
                    'title' => $this->titles[(string) $review->entity_uuid] ?? null,
                ],
                'rating' => $review->rating,
                'author' => $review->user?->name('full') ?: null,
                'message' => str_truncate((string) $review->message, 600),
                'reply' => ($reply = $review->children->first()) ? str_truncate((string) $reply->message, 600) : null,
            ]
        );
    }

    /**
     * @var array<string, string>
     */
    private array $titles = [];

    /**
     * Titles of products / publications the entries are about, two queries at most
     *
     * @return array<string, string>
     */
    private function entityTitles(iterable $reviews): array
    {
        $uuids = ['catalog_product' => [], 'publication' => []];

        foreach ($reviews as $review) {
            $uuids[$review->entity_type][] = (string) $review->entity_uuid;
        }

        return CatalogProduct::query()->whereIn('uuid', $uuids['catalog_product'])->pluck('title', 'uuid')
            ->merge(Publication::query()->whereIn('uuid', $uuids['publication'])->pluck('title', 'uuid'))
            ->mapWithKeys(fn ($title, $uuid) => [(string) $uuid => $title])
            ->all();
    }
}
