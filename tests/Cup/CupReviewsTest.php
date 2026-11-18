<?php declare(strict_types=1);

namespace tests\Cup;

use App\Domain\Models\Review;
use App\Domain\Service\Review\ReviewService;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Reviews of publications and of products, and questions about products: one list and one form
 * for the three, told apart by the address
 *
 * @internal
 *
 * #[CoversNothing]
 */
class CupReviewsTest extends CupTestCase
{
    public static function contexts(): array
    {
        return [
            'publication reviews' => ['/cup/publication/review', 'review', 'publication', 'publication'],
            'product reviews' => ['/cup/catalog/review', 'review', 'catalog_product', 'product'],
            'product questions' => ['/cup/catalog/question', 'question', 'catalog_product', 'product'],
        ];
    }

    private function entry(string $type, string $entityType, string $entity, string $message, array $extra = []): Review
    {
        $f = $this->fixtures();

        return $this->getService(ReviewService::class)->create($extra + [
            'type' => $type, 'entity_type' => $entityType, 'entity_uuid' => $f[$entity], 'message' => $message, 'rating' => 4,
        ]);
    }

    // -------------------------------------------------------------------- lists

    public function testEachListShowsOnlyItsOwnKind(): void
    {
        $this->entry('review', 'publication', 'publication', 'About the post');
        $this->entry('review', 'catalog_product', 'product', 'About the product');
        $this->entry('question', 'catalog_product', 'product', 'About the size');

        $this->assertSame(['About the post'], $this->shown('/cup/publication/review'));
        $this->assertSame(['About the product'], $this->shown('/cup/catalog/review'));
        $this->assertSame(['About the size'], $this->shown('/cup/catalog/question'));
    }

    /**
     * @return string[] which of the three messages the page shows
     */
    private function shown(string $path): array
    {
        $body = (string) $this->cup('GET', $path)->getBody();

        return array_values(array_filter(['About the post', 'About the product', 'About the size'], fn (string $m) => str_contains($body, $m)));
    }

    public function testAnAnswerIsNotListedAsAReviewOfItsOwn(): void
    {
        $review = $this->entry('review', 'publication', 'publication', 'The question part');
        $this->getService(ReviewService::class)->create([
            'parent_uuid' => $review->uuid, 'type' => 'review', 'entity_type' => 'publication', 'entity_uuid' => $review->entity_uuid, 'message' => 'The answer part',
        ]);

        $body = (string) $this->cup('GET', '/cup/publication/review')->getBody();

        $this->assertStringContainsString('The question part', $body);
        $this->assertSame(1, substr_count($body, 'data-bulk-row='), 'one row');
    }

    // --------------------------------------------------------------------- edit

    #[DataProvider('contexts')]
    public function testAnEntryIsEditedWithItsRatingAndStatus(string $path, string $type, string $entityType, string $entity): void
    {
        $entry = $this->entry($type, $entityType, $entity, 'Before');

        $response = $this->cup('POST', "{$path}/{$entry->uuid}/edit", ['form_params' => ['message' => 'After', 'rating' => '2', 'status' => 'work']]);

        $this->assertRedirectsTo($response, $path);
        $fresh = Review::find($entry->uuid);
        $this->assertSame('After', $fresh->message);
        $this->assertSame(2, $fresh->rating);
        $this->assertSame('work', $fresh->status);
    }

    #[DataProvider('contexts')]
    public function testSaveAndContinueStaysOnTheEntry(string $path, string $type, string $entityType, string $entity): void
    {
        $entry = $this->entry($type, $entityType, $entity, 'Text');

        $response = $this->cup('POST', "{$path}/{$entry->uuid}/edit", ['form_params' => ['save' => 'continue', 'message' => 'Text']]);

        $this->assertRedirectsTo($response, "{$path}/{$entry->uuid}/edit");
    }

    #[DataProvider('contexts')]
    public function testTheEditFormShowsTheEntry(string $path, string $type, string $entityType, string $entity): void
    {
        $entry = $this->entry($type, $entityType, $entity, 'What the customer wrote');

        $body = (string) $this->cup('GET', "{$path}/{$entry->uuid}/edit")->getBody();

        $this->assertStringContainsString('What the customer wrote', $body);
    }

    #[DataProvider('contexts')]
    public function testAnAnswerIsWrittenChangedAndTakenBack(string $path, string $type, string $entityType, string $entity): void
    {
        $entry = $this->entry($type, $entityType, $entity, 'Question or review');

        $this->cup('POST', "{$path}/{$entry->uuid}/edit", ['form_params' => ['message' => 'Question or review', 'response' => 'Thank you']]);
        $reply = Review::where('parent_uuid', $entry->uuid)->first();
        $this->assertNotNull($reply);
        $this->assertSame('Thank you', $reply->message);
        $this->assertSame($type, $reply->type);
        $this->assertSame($entityType, $reply->entity_type);
        $this->assertSame($entry->entity_uuid, $reply->entity_uuid);
        $this->assertSame(\App\Domain\Models\User::where('username', 'admin')->value('uuid'), $reply->user_uuid, 'the answer is by the administrator');
        $this->assertSame('work', $reply->status, 'an answer is published at once');

        $this->cup('POST', "{$path}/{$entry->uuid}/edit", ['form_params' => ['message' => 'Question or review', 'response' => 'Better answer']]);
        $this->assertSame(1, Review::where('parent_uuid', $entry->uuid)->count(), 'changed, not added');
        $this->assertSame('Better answer', Review::where('parent_uuid', $entry->uuid)->value('message'));

        $this->cup('POST', "{$path}/{$entry->uuid}/edit", ['form_params' => ['message' => 'Question or review', 'response' => '  ']]);
        $this->assertSame(0, Review::where('parent_uuid', $entry->uuid)->count(), 'an empty answer takes it back');
    }

    public function testAnEntryOfAnotherKindCannotBeOpenedThroughThisList(): void
    {
        $review = $this->entry('review', 'publication', 'publication', 'A publication review');

        foreach (['/cup/catalog/review', '/cup/catalog/question'] as $path) {
            $this->assertRedirectsTo($this->cup('GET', "{$path}/{$review->uuid}/edit"), $path, $path);

            $this->cup('POST', "{$path}/{$review->uuid}/edit", ['form_params' => ['message' => 'Hijacked']]);
            $this->cup('POST', "{$path}/{$review->uuid}/delete");
        }

        $this->assertSame('A publication review', Review::find($review->uuid)->message);
    }

    #[DataProvider('contexts')]
    public function testEditingAnUnknownEntryGoesBackToTheList(string $path, mixed ...$ignored): void
    {
        $this->admin();

        foreach ([\Ramsey\Uuid\Uuid::uuid4()->toString(), 'not-a-uuid'] as $uuid) {
            foreach (['GET', 'POST'] as $method) {
                $this->assertRedirectsTo($this->cup($method, "{$path}/{$uuid}/edit", ['form_params' => ['message' => 'x']]), $path, "{$method} {$uuid}");
            }
        }
    }

    #[DataProvider('contexts')]
    public function testAnEntryCannotBeEmptiedOrGiveAnImpossibleRating(string $path, string $type, string $entityType, string $entity): void
    {
        $entry = $this->entry($type, $entityType, $entity, 'Keep me');

        foreach ([['message' => ''], ['message' => 'Keep me', 'rating' => '99'], ['message' => 'Keep me', 'rating' => '-3'], ['message' => 'Keep me', 'status' => 'whatever']] as $form) {
            $response = $this->cup('POST', "{$path}/{$entry->uuid}/edit", ['form_params' => $form]);

            $this->assertContains($response->getStatusCode(), [200, 302], json_encode($form) . ' ' . $this->why($response));
        }

        $fresh = Review::find($entry->uuid);
        $this->assertSame('Keep me', $fresh->message);
        $this->assertGreaterThanOrEqual(0, $fresh->rating);
        $this->assertLessThanOrEqual(5, $fresh->rating);
        $this->assertContains($fresh->status, ['work', 'moderate', 'delete', 'queue', 'hidden', 'moderation'], 'the status is one the site knows');
    }

    // ------------------------------------------------------------------- delete

    #[DataProvider('contexts')]
    public function testAnEntryAndItsAnswerAreDeleted(string $path, string $type, string $entityType, string $entity): void
    {
        $entry = $this->entry($type, $entityType, $entity, 'Going');
        $this->cup('POST', "{$path}/{$entry->uuid}/edit", ['form_params' => ['message' => 'Going', 'response' => 'Answered']]);
        $before = Review::count();

        $response = $this->cup('POST', "{$path}/{$entry->uuid}/delete");

        $this->assertRedirectsTo($response, $path);
        $this->assertNull(Review::find($entry->uuid));
        $this->assertSame($before - 2, Review::count(), 'no answer is left to a thing that is gone');
    }

    #[DataProvider('contexts')]
    public function testDeletingAnUnknownEntryChangesNothing(string $path, string $type, string $entityType, string $entity): void
    {
        $this->entry($type, $entityType, $entity, 'Staying');
        $before = Review::count();

        foreach ([\Ramsey\Uuid\Uuid::uuid4()->toString(), 'not-a-uuid'] as $uuid) {
            $this->assertRedirectsTo($this->cup('POST', "{$path}/{$uuid}/delete"), $path, $uuid);
        }

        $this->assertSame($before, Review::count());
    }

    #[DataProvider('contexts')]
    public function testWhatACustomerWroteIsEscaped(string $path, string $type, string $entityType, string $entity): void
    {
        $hostile = '<script>alert("r")</script>';
        $entry = $this->entry($type, $entityType, $entity, $hostile);

        foreach ([$path, "{$path}/{$entry->uuid}/edit"] as $page) {
            $this->assertStringNotContainsString($hostile, (string) $this->cup('GET', $page)->getBody(), $page);
        }
    }
}
