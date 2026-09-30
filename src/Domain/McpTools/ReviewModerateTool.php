<?php declare(strict_types=1);

namespace App\Domain\McpTools;

use App\Application\PubSub;
use App\Domain\AbstractMcpTool;
use App\Domain\Casts\Review\Status as ReviewStatus;
use App\Domain\Models\Review;
use App\Domain\Service\Review\ReviewService;

class ReviewModerateTool extends AbstractMcpTool
{
    public const NAME = 'review_moderate';
    public const TITLE = 'Moderate review';
    public const DESCRIPTION = 'Publishes or hides a review / question, and can set the public answer of the administrator (shown on the site under it; an empty string removes the answer). '
        . 'Nothing is deleted. Show the text to the operator and get explicit confirmation before publishing or answering.';
    public const READ_ONLY = false;
    public const SCOPE = 'review';

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'uuid' => ['type' => 'string', 'description' => 'Uuid from review_list'],
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

        /** @var ReviewService $reviewService */
        $reviewService = $this->container->get(ReviewService::class);
        /** @var Review $review */
        $review = $reviewService->read(['uuid' => $args['uuid'], 'parent_uuid' => false, 'with' => ['children']]);

        if ($decision !== null) {
            $review = $reviewService->update($review, [
                'status' => $decision === 'publish' ? ReviewStatus::WORK : ReviewStatus::MODERATE,
            ]);
        }
        if (array_key_exists('response', $args)) {
            $this->syncReply($reviewService, $review, trim((string) $args['response']));
        }

        $this->container->get(PubSub::class)->publish('api:review:edit', $review);
        $this->logger->notice('Review moderated via MCP', ['uuid' => (string) $review->uuid, 'decision' => $decision]);

        $review->load('children');

        return [
            'uuid' => (string) $review->uuid,
            'status' => $review->status,
            'reply' => $review->children->first()?->message,
        ];
    }

    /**
     * The answer is stored as a single child row, same as in the admin panel
     */
    private function syncReply(ReviewService $reviewService, Review $review, string $message): void
    {
        $reply = $review->children->first();

        if ($message === '') {
            if ($reply !== null) {
                $reviewService->delete($reply);
            }

            return;
        }

        if ($reply !== null) {
            $reviewService->update($reply, ['message' => $message, 'status' => ReviewStatus::WORK]);

            return;
        }

        $reviewService->create([
            'parent_uuid' => $review->uuid,
            'type' => $review->type,
            'entity_type' => $review->entity_type,
            'entity_uuid' => $review->entity_uuid,
            'message' => $message,
            'status' => ReviewStatus::WORK,
        ]);
    }
}
