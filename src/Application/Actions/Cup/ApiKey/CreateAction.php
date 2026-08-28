<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\ApiKey;

use App\Domain\References\ApiEntity;
use App\Domain\Service\ApiKey\Exception\MissingTitleValueException;

class CreateAction extends ApiKeyAction
{
    protected function action(): \Slim\Psr7\Response
    {
        if ($this->isPost()) {
            try {
                $apiKey = $this->apiKeyService->create([
                    'title' => $this->getParam('title'),
                    'scopes' => $this->getScopes(),
                    'is_full_access' => $this->getParam('is_full_access', false),
                ]);

                $this->container->get(\App\Application\PubSub::class)->publish('cup:api-key:create', $apiKey);

                return $this->respondWithRedirect('/cup/api-key/' . $apiKey->uuid . '/edit');
            } catch (MissingTitleValueException $e) {
                $this->addError('title', $e->getMessage());
            }
        }

        return $this->respondWithTemplate('cup/api-key/form.twig', [
            'entities' => ApiEntity::options(),
        ]);
    }
}
