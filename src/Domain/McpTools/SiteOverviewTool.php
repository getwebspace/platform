<?php declare(strict_types=1);

namespace App\Domain\McpTools;

use App\Application\Dashboard;
use App\Domain\AbstractMcpTool;
use App\Domain\Models\ApiKey;

class SiteOverviewTool extends AbstractMcpTool
{
    public const NAME = 'site_overview';
    public const TITLE = 'Site overview';
    public const DESCRIPTION = 'What needs attention on the site and key numbers: new orders (no status or the first order status), reviews and guestbook entries waiting for moderation, users not confirmed by e-mail, failed and running background tasks; '
        . 'registrations and form submissions for the last 7 days, revenue for 30 days. Good first call to answer "what is going on / what should I handle". '
        . 'Only sections the API key may read are returned.';
    public const PRIVATE = true;

    /**
     * Section => API entity the key must be able to read
     */
    private const ATTENTION_SCOPE = [
        'orders' => 'catalog/order',
        'review' => 'review',
        'guestbook' => 'guestbook',
        'users' => 'user',
        'tasks_error' => 'task',
        'tasks_active' => 'task',
    ];

    private const KPI_SCOPE = [
        'users_week' => 'user',
        'users_total' => 'user',
        'form_data_week' => 'form',
        'form_data_total' => 'form',
        'guestbook_week' => 'guestbook',
        'files_total' => 'file',
        'files_size' => 'file',
    ];

    /**
     * Any valid key, sections are filtered by its scopes in execute()
     */
    public function isAllowed(?ApiKey $apiKey): bool
    {
        return $apiKey !== null;
    }

    public function execute(array $args = []): mixed
    {
        $dashboard = new Dashboard($this->container);
        $enabled = $dashboard->enabled();

        $filter = fn (array $values, array $scopes) => array_filter(
            $values,
            fn ($key) => $this->canRead($scopes[$key] ?? '*'),
            ARRAY_FILTER_USE_KEY
        );

        $revenue = null;

        if ($enabled['catalog'] && $this->canRead('catalog/order')) {
            $revenue = $dashboard->revenue();
            // days without orders only add noise for the model
            $revenue['daily'] = array_values(array_filter($revenue['daily'], fn ($day) => $day['order_count'] > 0));
        }

        return array_filter([
            'sections_enabled' => array_keys(array_filter($enabled)),
            'attention' => $filter($dashboard->attention($enabled), self::ATTENTION_SCOPE),
            'last_7_days' => $filter($dashboard->kpi($enabled, datetime()->subDays(7)), self::KPI_SCOPE),
            'revenue_30_days' => $revenue,
        ], fn ($value) => $value !== null);
    }
}
