<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\Review;

use App\Domain\AbstractAction;
use App\Domain\Casts\Review\EntityType as ReviewEntityType;
use App\Domain\Casts\Review\Type as ReviewType;
use App\Domain\Service\Review\ReviewService;
use Psr\Container\ContainerInterface;
use Slim\Routing\RouteContext;

abstract class ReviewAction extends AbstractAction
{
    protected ReviewService $reviewService;

    /**
     * Every screen (publication reviews, product reviews, product questions) is
     * the same list/form over the `review` table, told apart by the route it
     * was reached through
     */
    protected const CONTEXT = [
        'cup:publication:review' => [
            'type' => ReviewType::REVIEW,
            'entity_type' => ReviewEntityType::PUBLICATION,
            'path' => '/cup/publication/review',
            'title' => 'Reviews for publications',
        ],
        'cup:catalog:review' => [
            'type' => ReviewType::REVIEW,
            'entity_type' => ReviewEntityType::CATALOG_PRODUCT,
            'path' => '/cup/catalog/review',
            'title' => 'Reviews for products',
        ],
        'cup:catalog:question' => [
            'type' => ReviewType::QUESTION,
            'entity_type' => ReviewEntityType::CATALOG_PRODUCT,
            'path' => '/cup/catalog/question',
            'title' => 'Questions for products',
        ],
    ];

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);

        $this->reviewService = $container->get(ReviewService::class);
    }

    /**
     * @return array{type: string, entity_type: string, path: string, title: string}
     */
    protected function context(): array
    {
        $name = RouteContext::fromRequest($this->request)->getRoute()->getName() ?? '';
        // strip the trailing :list / :edit / :delete
        $key = implode(':', array_slice(explode(':', $name), 0, 3));

        return self::CONTEXT[$key] ?? self::CONTEXT['cup:publication:review'];
    }
}
