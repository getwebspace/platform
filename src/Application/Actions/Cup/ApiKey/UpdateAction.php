<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\ApiKey;

use App\Domain\References\ApiEntity;
use App\Domain\Service\ApiKey\Exception\ApiKeyNotFoundException;
use App\Domain\Service\ApiKey\Exception\MissingTitleValueException;

class UpdateAction extends ApiKeyAction
{
    protected function action(): \Slim\Psr7\Response
    {
        if ($this->resolveArg('uuid')) {
            try {
                $apiKey = $this->apiKeyService->read(['uuid' => $this->resolveArg('uuid')]);

                if ($this->isPost()) {
                    try {
                        $apiKey = $this->apiKeyService->update($apiKey, [
                            'title' => $this->getParam('title'),
                            'scopes' => $this->getScopes(),
                            'is_full_access' => $this->getParam('is_full_access', false),
                            // an unrecognised value would otherwise cast to an
                            // empty string, silently landing outside both
                            // "work" and "revoke" - keep the current one instead
                            'status' => $this->getParam('status', $apiKey->status),
                        ]);

                        $this->container->get(\App\Application\PubSub::class)->publish('cup:api-key:edit', $apiKey);

                        return $this->respondWithRedirect('/cup/api-key/' . $apiKey->uuid . '/edit');
                    } catch (MissingTitleValueException $e) {
                        $this->addError('title', $e->getMessage());
                    }
                }

                return $this->respondWithTemplate('cup/api-key/form.twig', [
                    'item' => $apiKey,
                    // re-derived from the row's uuid on every view - nothing
                    // to store, so nothing extra to protect at rest
                    'token' => $this->apiKeyService->issueToken($apiKey),
                    'entities' => ApiEntity::options(),
                ]);
            } catch (ApiKeyNotFoundException $e) {
                // nothing
            }
        }

        return $this->respondWithRedirect('/cup/api-key');
    }
}
