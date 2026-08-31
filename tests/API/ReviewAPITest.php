<?php declare(strict_types=1);

namespace tests\API;

use App\Domain\Service\Catalog\CategoryService as CatalogCategoryService;
use App\Domain\Service\Catalog\ProductService as CatalogProductService;
use App\Domain\Service\Parameter\ParameterService;
use App\Domain\Service\Publication\CategoryService as PublicationCategoryService;
use App\Domain\Service\Publication\PublicationService;
use App\Domain\Service\Review\ReviewService;
use App\Domain\Service\User\UserService;
use tests\TestCase;

/**
 * @internal
 *
 * #[CoversNothing]
 */
class ReviewAPITest extends TestCase
{
    use \App\Domain\Traits\UseSecurity;

    private function enableReviews(): void
    {
        $parameters = $this->getService(ParameterService::class);
        $parameters->create(['name' => 'review_publication_is_enabled', 'value' => 'yes']);
        $parameters->create(['name' => 'review_product_is_enabled', 'value' => 'yes']);
        $parameters->create(['name' => 'review_question_product_is_enabled', 'value' => 'yes']);
    }

    private function makePublication()
    {
        $category = $this->getService(PublicationCategoryService::class)->create([
            'title' => implode(' ', $this->getFaker()->words(3)),
            'address' => implode('-', $this->getFaker()->words(4)),
        ]);

        return $this->getService(PublicationService::class)->create([
            'title' => implode(' ', $this->getFaker()->words(3)),
            'address' => implode('-', $this->getFaker()->words(4)),
            'category_uuid' => $category->uuid,
            'content' => ['short' => 'x', 'full' => 'x'],
        ]);
    }

    private function makeProduct()
    {
        $category = $this->getService(CatalogCategoryService::class)->create([
            'title' => implode(' ', $this->getFaker()->words(3)),
            'address' => implode('-', $this->getFaker()->words(4)),
        ]);

        return $this->getService(CatalogProductService::class)->create([
            'title' => implode(' ', $this->getFaker()->words(3)),
            'address' => implode('-', $this->getFaker()->words(4)),
            'category_uuid' => $category->uuid,
        ]);
    }

    /**
     * A signed-in browser session is an `access_token` cookie holding a
     * `sub: user` JWT - mint one straight away instead of driving /auth/login
     *
     * @return array{0: string, 1: \App\Domain\Models\User}
     */
    private function login(): array
    {
        $user = $this->getService(UserService::class)->create([
            'username' => $this->getFaker()->userName,
            'password' => $this->getFaker()->password(8),
            'email' => $this->getFaker()->email,
        ]);

        return [$this->encodeJWT('user', $user->uuid), $user];
    }

    public function testDisabledByDefault(): void
    {
        $publication = $this->makePublication();

        $response = $this->createRequest()->post("/publication/{$publication->uuid}/review", [
            'form_params' => ['message' => 'hello'],
        ]);

        $this->assertEquals(403, $response->getStatusCode());
        $this->assertStringContainsString('disabled', (string) $response->getBody());
    }

    public function testRequiresAuthenticatedUser(): void
    {
        $this->enableReviews();
        $publication = $this->makePublication();

        $response = $this->createRequest()->post("/publication/{$publication->uuid}/review", [
            'form_params' => ['message' => 'hello'],
        ]);

        $this->assertEquals(403, $response->getStatusCode());
        $this->assertStringContainsString('Authorization', (string) $response->getBody());
    }

    public function testCreateReviewForPublication(): void
    {
        $this->enableReviews();
        $publication = $this->makePublication();
        [$token] = $this->login();

        $response = $this->createRequest()->post("/publication/{$publication->uuid}/review", [
            'headers' => ['Cookie' => 'access_token=' . $token],
            'form_params' => ['message' => 'Great read', 'rating' => 5],
        ]);

        $this->assertEquals(201, $response->getStatusCode());
        $json = json_decode((string) $response->getBody(), true);
        $this->assertSame('moderate', $json['data']['status']);
        $this->assertSame('review', $json['data']['type']);
        $this->assertSame('publication', $json['data']['entity_type']);
        $this->assertSame($publication->uuid, $json['data']['entity_uuid']);
        $this->assertSame(5, $json['data']['rating']);

        // it is really persisted, in moderation, not visible to an anonymous list
        $stored = $this->getService(ReviewService::class)->read([
            'entity_type' => 'publication',
            'entity_uuid' => $publication->uuid,
        ]);
        $this->assertCount(1, $stored);
    }

    public function testCreateQuestionForProduct(): void
    {
        $this->enableReviews();
        $product = $this->makeProduct();
        [$token] = $this->login();

        $response = $this->createRequest()->post("/catalog/{$product->uuid}/question", [
            'headers' => ['Cookie' => 'access_token=' . $token],
            'form_params' => ['message' => 'Is it in stock?'],
        ]);

        $this->assertEquals(201, $response->getStatusCode());
        $json = json_decode((string) $response->getBody(), true);
        $this->assertSame('question', $json['data']['type']);
        $this->assertSame('catalog_product', $json['data']['entity_type']);
        $this->assertSame($product->uuid, $json['data']['entity_uuid']);
    }

    public function testRejectsMissingMessage(): void
    {
        $this->enableReviews();
        $publication = $this->makePublication();
        [$token] = $this->login();

        $response = $this->createRequest()->post("/publication/{$publication->uuid}/review", [
            'headers' => ['Cookie' => 'access_token=' . $token],
            'form_params' => ['message' => ''],
        ]);

        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testUnknownEntityIs404(): void
    {
        $this->enableReviews();
        [$token] = $this->login();

        $response = $this->createRequest()->post('/publication/' . $this->getFaker()->uuid . '/review', [
            'headers' => ['Cookie' => 'access_token=' . $token],
            'form_params' => ['message' => 'hello'],
        ]);

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testEntityEndpointReachableWithApiKey(): void
    {
        $this->getService(ParameterService::class)->create(['name' => 'entity_access', 'value' => 'key']);
        $apikey = $this->createApiKeyToken();

        $response = $this->createRequest()->get('/api/v1/review', ['headers' => ['key' => $apikey]]);
        $this->assertEquals(200, $response->getStatusCode());
    }
}
