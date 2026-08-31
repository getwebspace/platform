<?php declare(strict_types=1);

namespace tests\Domain\Service\Review;

use App\Domain\Casts\Review\EntityType as ReviewEntityType;
use App\Domain\Casts\Review\Status as ReviewStatus;
use App\Domain\Casts\Review\Type as ReviewType;
use App\Domain\Models\Review;
use App\Domain\Service\Review\Exception\MissingEntityValueException;
use App\Domain\Service\Review\Exception\MissingMessageValueException;
use App\Domain\Service\Review\Exception\ReviewNotFoundException;
use App\Domain\Service\Review\Exception\WrongEntityTypeValueException;
use App\Domain\Service\Review\Exception\WrongTypeValueException;
use App\Domain\Service\Review\ReviewService;
use tests\TestCase;

/**
 * @internal
 *
 * #[CoversNothing]
 */
class ReviewServiceTest extends TestCase
{
    protected ReviewService $service;

    public function setUp(): void
    {
        parent::setUp();

        $this->service = $this->getService(ReviewService::class);
    }

    protected function payload(array $override = []): array
    {
        return array_merge([
            'user_uuid' => $this->getFaker()->uuid,
            'type' => ReviewType::REVIEW,
            'entity_type' => ReviewEntityType::PUBLICATION,
            'entity_uuid' => $this->getFaker()->uuid,
            'rating' => $this->getFaker()->numberBetween(1, 5),
            'message' => $this->getFaker()->text,
        ], $override);
    }

    public function testCreateSuccess(): void
    {
        $data = $this->payload();

        $review = $this->service->create($data);
        $this->assertInstanceOf(Review::class, $review);
        $this->assertEquals($data['message'], $review->message);
        $this->assertEquals($data['entity_uuid'], $review->entity_uuid);
        $this->assertEquals(ReviewType::REVIEW, $review->type);
        $this->assertEquals(ReviewEntityType::PUBLICATION, $review->entity_type);
        $this->assertEquals(ReviewStatus::MODERATE, $review->status);
    }

    public function testCreateQuestionForProduct(): void
    {
        $review = $this->service->create($this->payload([
            'type' => ReviewType::QUESTION,
            'entity_type' => ReviewEntityType::CATALOG_PRODUCT,
        ]));

        $this->assertEquals(ReviewType::QUESTION, $review->type);
        $this->assertEquals(ReviewEntityType::CATALOG_PRODUCT, $review->entity_type);
    }

    public function testCreateWithMissingMessageValue(): void
    {
        $this->expectException(MissingMessageValueException::class);

        $this->service->create($this->payload(['message' => '']));
    }

    public function testCreateWithMissingEntityValue(): void
    {
        $this->expectException(MissingEntityValueException::class);

        $this->service->create($this->payload(['entity_uuid' => '']));
    }

    public function testCreateWithWrongType(): void
    {
        $this->expectException(WrongTypeValueException::class);

        $this->service->create($this->payload(['type' => 'wrong']));
    }

    public function testCreateWithWrongEntityType(): void
    {
        $this->expectException(WrongEntityTypeValueException::class);

        $this->service->create($this->payload(['entity_type' => 'wrong']));
    }

    public function testReadSuccess(): void
    {
        $data = $this->payload();
        $review = $this->service->create($data);

        $found = $this->service->read(['uuid' => $review->uuid]);
        $this->assertInstanceOf(Review::class, $found);
        $this->assertEquals($data['message'], $found->message);
    }

    public function testReadByEntity(): void
    {
        $entityUuid = $this->getFaker()->uuid;
        $this->service->create($this->payload(['entity_uuid' => $entityUuid]));
        $this->service->create($this->payload(['entity_uuid' => $entityUuid]));
        $this->service->create($this->payload());

        $list = $this->service->read([
            'entity_type' => ReviewEntityType::PUBLICATION,
            'entity_uuid' => $entityUuid,
        ]);

        $this->assertCount(2, $list);
    }

    public function testReadOnlyRoot(): void
    {
        $root = $this->service->create($this->payload());
        $this->service->create($this->payload(['parent_uuid' => $root->uuid]));

        $roots = $this->service->read(['parent_uuid' => false]);
        $replies = $this->service->read(['parent_uuid' => $root->uuid]);

        $this->assertCount(1, $roots);
        $this->assertCount(1, $replies);
    }

    public function testReadWithReviewNotFound(): void
    {
        $this->expectException(ReviewNotFoundException::class);

        $this->service->read(['uuid' => $this->getFaker()->uuid]);
    }

    public function testUpdateSuccess(): void
    {
        $review = $this->service->create($this->payload());

        $review = $this->service->update($review, [
            'message' => 'edited',
            'status' => ReviewStatus::WORK,
        ]);

        $this->assertEquals('edited', $review->message);
        $this->assertEquals(ReviewStatus::WORK, $review->status);
    }

    public function testUpdateWithReviewNotFound(): void
    {
        $this->expectException(ReviewNotFoundException::class);

        $this->service->update(null);
    }

    public function testDeleteAlsoDropsReplies(): void
    {
        $root = $this->service->create($this->payload());
        $this->service->create($this->payload(['parent_uuid' => $root->uuid]));

        $result = $this->service->delete($root);

        $this->assertTrue($result);
        $this->assertCount(0, $this->service->read([]));
    }

    public function testDeleteWithReviewNotFound(): void
    {
        $this->expectException(ReviewNotFoundException::class);

        $this->service->delete(null);
    }
}
