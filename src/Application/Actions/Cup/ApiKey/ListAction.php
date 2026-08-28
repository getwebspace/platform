<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\ApiKey;

use App\Domain\References\ApiEntity;

class ListAction extends ApiKeyAction
{
    protected function action(): \Slim\Psr7\Response
    {
        return $this->respondWithTemplate('cup/api-key/index.twig', [
            'list' => $this->apiKeyService->read(['order' => ['date' => 'desc']]),
            'entities' => ApiEntity::options(),
        ]);
    }
}
