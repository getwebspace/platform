<?php declare(strict_types=1);

namespace App\Application\Actions\Common;

use App\Domain\AbstractAction;
use App\Domain\Casts\Review\EntityType as ReviewEntityType;
use App\Domain\Casts\Review\Status as ReviewStatus;
use App\Domain\Casts\Review\Type as ReviewType;
use App\Domain\Models\Review;
use App\Domain\Service\Catalog\ProductService as CatalogProductService;
use App\Domain\Service\Publication\PublicationService;
use App\Domain\Service\Review\Exception\MissingEntityValueException;
use App\Domain\Service\Review\Exception\MissingMessageValueException;
use App\Domain\Service\Review\Exception\ReviewNotFoundException;
use App\Domain\Service\Review\ReviewService;
use Slim\Routing\RouteContext;

/**
 * Accept a review or a question for a publication / catalog product from the
 * public site. Only a signed-in user may post; everything lands in moderation
 */
class ReviewAction extends AbstractAction
{
    private const CONTEXT = [
        'common:publication:review' => [
            'type' => ReviewType::REVIEW,
            'entity_type' => ReviewEntityType::PUBLICATION,
            'parameter' => 'review_publication_is_enabled',
            'service' => PublicationService::class,
        ],
        'common:catalog:review' => [
            'type' => ReviewType::REVIEW,
            'entity_type' => ReviewEntityType::CATALOG_PRODUCT,
            'parameter' => 'review_product_is_enabled',
            'service' => CatalogProductService::class,
        ],
        'common:catalog:question' => [
            'type' => ReviewType::QUESTION,
            'entity_type' => ReviewEntityType::CATALOG_PRODUCT,
            'parameter' => 'review_question_product_is_enabled',
            'service' => CatalogProductService::class,
        ],
    ];

    protected function action(): \Slim\Psr7\Response
    {
        $name = RouteContext::fromRequest($this->request)->getRoute()->getName() ?? '';
        $context = self::CONTEXT[$name] ?? null;

        if ($context === null) {
            return $this->respondWithJson(['error' => 'Unknown review context'])->withStatus(404);
        }

        // feature switch, off by default
        if ($this->parameter($context['parameter'], 'no') !== 'yes') {
            return $this->respondWithJson(['error' => 'Reviews are disabled'])->withStatus(403);
        }

        // registered users only
        $user = $this->request->getAttribute('user', false);

        if ($user === false) {
            return $this->respondWithJson(['error' => 'Authorization required'])->withStatus(403);
        }

        if (!$this->isRecaptchaChecked()) {
            return $this->respondWithJson(['error' => 'EXCEPTION_WRONG_GRECAPTCHA'])->withStatus(400);
        }

        $entityUuid = $this->resolveArg('uuid');

        if (!\Ramsey\Uuid\Uuid::isValid($entityUuid)) {
            return $this->respondWithJson(['error' => 'Entity not found'])->withStatus(404);
        }

        // the publication / product must exist
        try {
            $this->container->get($context['service'])->read(['uuid' => $entityUuid]);
        } catch (\Exception $e) {
            return $this->respondWithJson(['error' => 'Entity not found'])->withStatus(404);
        }

        /** @var ReviewService $reviewService */
        $reviewService = $this->container->get(ReviewService::class);

        // optional reply to an existing entry of the same entity
        $parentUuid = null;

        if (($parent = $this->getParam('parent_uuid')) && \Ramsey\Uuid\Uuid::isValid($parent)) {
            try {
                $reviewService->read([
                    'uuid' => $parent,
                    'entity_type' => $context['entity_type'],
                    'entity_uuid' => $entityUuid,
                ]);
                $parentUuid = $parent;
            } catch (ReviewNotFoundException $e) {
                return $this->respondWithJson(['error' => 'Parent not found'])->withStatus(404);
            }
        }

        try {
            /** @var Review $review */
            $review = $reviewService->create([
                'parent_uuid' => $parentUuid,
                'user_uuid' => $user->uuid,
                'type' => $context['type'],
                'entity_type' => $context['entity_type'],
                'entity_uuid' => $entityUuid,
                'rating' => max(0, min(5, (int) $this->getParam('rating', 0))),
                'message' => $this->getParam('message'),
                'status' => ReviewStatus::MODERATE,
            ]);
            $review = $this->processEntityFiles($review);

            $this->container->get(\App\Application\PubSub::class)->publish('common:review:create', $review);

            return $this->respondWithJson(['description' => 'Review added', 'data' => $review])->withStatus(201);
        } catch (MissingMessageValueException $e) {
            return $this->respondWithJson(['error' => $e->getMessage()])->withStatus(400);
        } catch (MissingEntityValueException $e) {
            return $this->respondWithJson(['error' => $e->getMessage()])->withStatus(400);
        }
    }
}
