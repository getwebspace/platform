<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\ApiKey;

use App\Domain\AbstractAction;
use App\Domain\Service\ApiKey\ApiKeyService;
use Psr\Container\ContainerInterface;

abstract class ApiKeyAction extends AbstractAction
{
    protected ApiKeyService $apiKeyService;

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);

        $this->apiKeyService = $container->get(ApiKeyService::class);
    }

    /**
     * Scope checkboxes are posted as `scopes[read][]` / `scopes[write][]`,
     * which PHP already nests correctly - this only fills in the empty side
     * when a mode has nothing checked, so `create()`/`update()` always see both
     */
    protected function getScopes(): array
    {
        $scopes = (array) $this->getParam('scopes', []);

        return [
            'read' => (array) ($scopes['read'] ?? []),
            'write' => (array) ($scopes['write'] ?? []),
        ];
    }
}
