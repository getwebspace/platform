<?php declare(strict_types=1);

namespace App\Domain\Tasks;

use App\Domain\AbstractTask;
use App\Domain\Service\Search\SearchService;

class SearchIndexTask extends AbstractTask
{
    public const TITLE = 'Search indexing';

    public function execute(array $params = []): \App\Domain\Models\Task
    {
        return parent::execute([]);
    }

    protected function action(array $args = []): void
    {
        $total = $this->container->get(SearchService::class)->reindex();

        $this->logger->info('SearchIndex: result', ['total' => $total]);

        $this->container->get(\App\Application\PubSub::class)->publish('task:search:indexed');

        $this->setStatusDone();
    }
}
