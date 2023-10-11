<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\Reference;

use App\Domain\Service\Reference\Exception\ReferenceNotFoundException;

class ReferenceDeleteAction extends ReferenceAction
{
    protected function action(): \Slim\Psr7\Response
    {
        if ($this->resolveArg('uuid') && \Ramsey\Uuid\Uuid::isValid($this->resolveArg('uuid'))) {
            try {
                $reference = $this->referenceService->read([
                    'uuid' => $this->resolveArg('uuid'),
                ]);
                $this->referenceService->delete($reference);

                $this->container->get(\App\Application\PubSub::class)->publish('cup:reference:delete', $reference);
            } catch (ReferenceNotFoundException $e) {
                // nothing
            }
        }

        return $this->respondWithRedirect($_SERVER['HTTP_REFERER']);
    }
}
