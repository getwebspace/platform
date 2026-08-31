<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\Review;

use App\Domain\Casts\Review\Status as ReviewStatus;
use App\Domain\Models\Review;
use App\Domain\Service\Review\Exception\ReviewNotFoundException;

class ReviewUpdateAction extends ReviewAction
{
    protected function action(): \Slim\Psr7\Response
    {
        $context = $this->context();

        if ($this->resolveArg('uuid') && \Ramsey\Uuid\Uuid::isValid($this->resolveArg('uuid'))) {
            try {
                /** @var Review $entry */
                $entry = $this->reviewService->read([
                    'uuid' => $this->resolveArg('uuid'),
                    'type' => $context['type'],
                    'entity_type' => $context['entity_type'],
                ]);

                /** @var Review $reply */
                $reply = $entry->getRelationValue('children')->first();

                if ($this->isPost()) {
                    $entry = $this->reviewService->update($entry, [
                        'message' => $this->getParam('message', $entry->message),
                        'rating' => (int) $this->getParam('rating', $entry->rating),
                        'status' => $this->getParam('status', $entry->status),
                    ]);
                    $entry = $this->processEntityFiles($entry);

                    $this->syncReply($entry, $reply, trim((string) $this->getParam('response', '')));

                    $this->container->get(\App\Application\PubSub::class)->publish('cup:review:edit', $entry);

                    switch (true) {
                        case $this->getParam('save', 'exit') === 'exit':
                            return $this->respondWithRedirect($context['path']);

                        default:
                            return $this->respondWithRedirect($context['path'] . '/' . $entry->uuid . '/edit');
                    }
                }

                return $this->respondWithTemplate('cup/review/form.twig', [
                    'context' => $context,
                    'item' => $entry,
                    'reply' => $reply,
                ]);
            } catch (ReviewNotFoundException $e) {
                // nothing
            }
        }

        return $this->respondWithRedirect($context['path']);
    }

    /**
     * The admin answer is stored as a single child row (parent_uuid = $entry)
     */
    private function syncReply(Review $entry, ?Review $reply, string $message): void
    {
        $user = $this->request->getAttribute('user', false);

        if ($message === '') {
            if ($reply !== null) {
                $this->reviewService->delete($reply);
            }

            return;
        }

        if ($reply !== null) {
            $this->reviewService->update($reply, ['message' => $message, 'status' => ReviewStatus::WORK]);

            return;
        }

        $this->reviewService->create([
            'parent_uuid' => $entry->uuid,
            'user_uuid' => $user ? $user->uuid : null,
            'type' => $entry->type,
            'entity_type' => $entry->entity_type,
            'entity_uuid' => $entry->entity_uuid,
            'message' => $message,
            'status' => ReviewStatus::WORK,
        ]);
    }
}
