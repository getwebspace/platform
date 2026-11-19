<?php declare(strict_types=1);

namespace App\Domain\McpTools;

use App\Domain\AbstractMcpTool;
use App\Domain\Casts\Review\EntityType;
use App\Domain\Casts\Review\Status;
use App\Domain\Casts\Review\Type;
use App\Domain\Models\CatalogProduct;
use App\Domain\Models\Publication;
use App\Domain\Service\Review\ReviewService;

class ReviewCreateTool extends AbstractMcpTool
{
    public const NAME = 'review_create';
    public const TITLE = 'Add review';
    public const DESCRIPTION = 'Adds a review or a question to a product or a publication on behalf of a person (e.g. one that came by another channel). The record always waits for moderation (review_moderate publishes it) and nobody is notified. '
        . 'Never invent reviews or ratings.';
    public const READ_ONLY = false;
    public const PRIVATE = true;
    public const SCOPE = 'review';

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'entity_type' => ['type' => 'string', 'enum' => EntityType::LIST],
                'entity_uuid' => ['type' => 'string', 'description' => 'Product or publication uuid'],
                'message' => ['type' => 'string'],
                'rating' => ['type' => 'integer', 'description' => '0 (none) - 5'],
                'type' => ['type' => 'string', 'enum' => Type::LIST, 'default' => Type::REVIEW],
            ],
            'required' => ['entity_type', 'entity_uuid', 'message'],
        ];
    }

    public function execute(array $args = []): mixed
    {
        if (!in_array($args['entity_type'] ?? null, EntityType::LIST, true)) {
            throw new \InvalidArgumentException('Field entity_type must be one of: ' . implode(', ', EntityType::LIST));
        }
        $type = $args['type'] ?? Type::REVIEW;

        if (!in_array($type, Type::LIST, true)) {
            throw new \InvalidArgumentException('Field type must be one of: ' . implode(', ', Type::LIST));
        }
        if (blank($message = $this->textArgument($args, 'message'))) {
            throw new \InvalidArgumentException('Field message is required');
        }

        $uuid = $args['entity_uuid'] ?? null;
        $model = $args['entity_type'] === EntityType::PUBLICATION ? Publication::class : CatalogProduct::class;

        if (!is_string($uuid) || !\Ramsey\Uuid\Uuid::isValid($uuid) || !$model::query()->where('uuid', $uuid)->exists()) {
            throw new \InvalidArgumentException('The product or publication was not found');
        }

        $review = $this->container->get(ReviewService::class)->create([
            'entity_type' => $args['entity_type'],
            'entity_uuid' => $uuid,
            'type' => $type,
            'message' => $message,
            'rating' => $this->intArgument($args, 'rating', 0, 0, 5),
            'status' => Status::MODERATE,
        ]);

        $this->logger->notice('Review created via MCP', ['uuid' => (string) $review->uuid]);

        return ['uuid' => (string) $review->uuid, 'status' => $review->status, 'rating' => $review->rating];
    }
}
