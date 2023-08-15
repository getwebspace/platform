<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\Reference;

use App\Domain\Exceptions\HttpBadRequestException;
use App\Domain\Service\Reference\Exception\ReferenceNotFoundException;

class ReferenceDeleteAction extends ReferenceAction
{
    protected function action(): \Slim\Psr7\Response
    {
        if ($this->resolveArg('uuid') && \Ramsey\Uuid\Uuid::isValid($this->resolveArg('uuid'))) {
            try {
                $ref = $this->referenceService->read([
                    'uuid' => $this->resolveArg('uuid'),
                ]);
                $this->referenceService->delete($ref);

                $this->container->get(\App\Application\PubSub::class)->publish('cup:reference:delete', $ref);
            } catch (ReferenceNotFoundException $e) {
                // nothing
            }
        }

        return $this->respondWithRedirect($_SERVER['HTTP_REFERER']);
    }
}
