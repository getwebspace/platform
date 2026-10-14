<?php declare(strict_types=1);

namespace tests\API;

use App\Domain\Service\Publication\CategoryService as PublicationCategoryService;
use App\Domain\Service\Publication\PublicationService;
use App\Domain\Service\User\UserService;
use tests\TestCase;

/**
 * @internal
 *
 * #[CoversNothing]
 */
class PublicationTest extends TestCase
{
    /**
     * The single publication page must be rendered with the category's `full` template
     */
    public function testFullUsesCategoryTemplate(): void
    {
        $category = $this->getService(PublicationCategoryService::class)->create([
            'title' => implode(' ', $this->getFaker()->words(3)),
            'address' => 'news-' . $this->getFaker()->unique()->numberBetween(1000, 9999),
            'is_public' => true,
            'template' => [
                'list' => 'p404.twig',
                'short' => '',
                // any template present in the default theme, publication.full.twig is not
                'full' => 'p404.twig',
            ],
        ]);

        $user = $this->getService(UserService::class)->create([
            'username' => $this->getFaker()->userName,
            'password' => $this->getFaker()->password,
            'email' => $this->getFaker()->email,
        ]);

        $publication = $this->getService(PublicationService::class)->create([
            'title' => implode(' ', $this->getFaker()->words(3)),
            'address' => $category->address . '/' . implode('-', $this->getFaker()->words(3)),
            'category_uuid' => $category->uuid,
            'user_uuid' => $user->uuid,
            'date' => new \DateTime('-1 day'),
            'content' => [
                'short' => $this->getFaker()->text(200),
                'full' => $this->getFaker()->text(200),
            ],
        ]);

        $response = $this->createRequest()->get('/' . $publication->address);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('Not found', (string) $response->getBody());
    }
}
