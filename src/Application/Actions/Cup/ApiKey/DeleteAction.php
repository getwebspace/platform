<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\ApiKey;

use App\Domain\Service\ApiKey\Exception\ApiKeyNotFoundException;

class DeleteAction extends ApiKeyAction
{
    protected function action(): \Slim\Psr7\Response
    {
        if ($this->resolveArg('uuid') && \Ramsey\Uuid\Uuid::isValid($this->resolveArg('uuid'))) {
            try {
                $apiKey = $this->apiKeyService->read(['uuid' => $this->resolveArg('uuid')]);

                $this->apiKeyService->delete($apiKey);

                $this->container->get(\App\Application\PubSub::class)->publish('cup:api-key:delete', $apiKey);
            } catch (ApiKeyNotFoundException $e) {
                // nothing
            }
        }

        return $this->respondWithRedirect('/cup/api-key');
    }
}
