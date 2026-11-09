<?php declare(strict_types=1);

namespace App\Domain\McpTools;

use App\Domain\AbstractMcpTool;
use App\Domain\AbstractTask;
use App\Domain\Tasks\SearchIndexTask;

class SiteReindexTool extends AbstractMcpTool
{
    public const NAME = 'site_reindex';
    public const TITLE = 'Rebuild the search index';
    public const DESCRIPTION = 'Starts the background task that builds the search index of the site again (products, publications, pages). Takes from seconds to minutes, the old index works meanwhile. '
        . 'Follow the progress with task_list status=all. Use it after a mass change of the catalog or when site_search finds nothing. Requires a full access API key.';
    public const READ_ONLY = false;
    public const PRIVATE = true;

    /**
     * The worker is a separate process, it cannot be rolled back
     */
    public const DRY_RUN = false;

    public function execute(array $args = []): mixed
    {
        $task = (new SearchIndexTask($this->container))->execute();

        AbstractTask::worker($task->action);

        $this->logger->notice('Search reindex started via MCP', ['task' => (string) $task->uuid]);

        return ['task' => (string) $task->uuid, 'status' => $task->status, 'note' => 'Started. Check task_list status=all in a minute.'];
    }
}
