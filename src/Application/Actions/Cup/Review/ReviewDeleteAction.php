<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\Review;

use App\Domain\Service\Review\Exception\ReviewNotFoundException;

class ReviewDeleteAction extends ReviewAction
{
    protected function action(): \Slim\Psr7\Response
    {
        $context = $this->context();

        if ($this->resolveArg('uuid') && \Ramsey\Uuid\Uuid::isValid($this->resolveArg('uuid'))) {
            try {
                $entry = $this->reviewService->read([
                    'uuid' => $this->resolveArg('uuid'),
                    'type' => $context['type'],
                    'entity_type' => $context['entity_type'],
                ]);

                if ($entry) {
                    $this->reviewService->delete($entry);

                    $this->container->get(\App\Application\PubSub::class)->publish('cup:review:delete', $entry);
                }
            } catch (ReviewNotFoundException $e) {
                // nothing
            }
        }

        return $this->respondWithRedirect($context['path']);
    }
}
