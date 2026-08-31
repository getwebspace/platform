<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\Review;

class ReviewListAction extends ReviewAction
{
    protected function action(): \Slim\Psr7\Response
    {
        $context = $this->context();

        return $this->respondWithTemplate('cup/review/index.twig', [
            'context' => $context,
            'list' => $this->reviewService->read([
                'type' => $context['type'],
                'entity_type' => $context['entity_type'],
                'parent_uuid' => false,
                'with' => ['user', 'children'],
                'order' => ['date' => 'desc'],
            ]),
        ]);
    }
}
