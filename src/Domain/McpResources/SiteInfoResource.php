<?php declare(strict_types=1);

namespace App\Domain\McpResources;

use App\Application\Dashboard;
use App\Domain\AbstractMcpResource;

class SiteInfoResource extends AbstractMcpResource
{
    public const URI = 'platform://site';
    public const NAME = 'site';
    public const TITLE = 'About the site';
    public const DESCRIPTION = 'Title, address, description, language and the sections turned on (catalog, publications, forms...).';

    public function read(): string
    {
        return $this->json([
            'title' => $this->parameter('common_title', ''),
            'homepage' => $this->parameter('common_homepage', ''),
            'description' => $this->parameter('common_description', ''),
            'language' => $this->parameter('common_language', ''),
            'sections_enabled' => array_keys(array_filter((new Dashboard($this->container))->enabled())),
        ]);
    }
}
