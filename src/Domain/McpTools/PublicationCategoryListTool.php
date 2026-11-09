<?php declare(strict_types=1);

namespace App\Domain\McpTools;

use App\Domain\AbstractMcpTool;
use App\Domain\Models\PublicationCategory;

class PublicationCategoryListTool extends AbstractMcpTool
{
    public const NAME = 'publication_category_list';
    public const TITLE = 'Publication categories';
    public const DESCRIPTION = 'Lists the categories of publications (news, articles...) with uuid, address, whether it is public and the number of publications. The uuid is needed for publication_create.';
    public const PRIVATE = true;
    public const SCOPE = 'publication/category';

    public function execute(array $args = []): mixed
    {
        return PublicationCategory::all()
            ->sortBy('title')
            ->map(fn (PublicationCategory $category) => [
                'uuid' => (string) $category->uuid,
                'title' => $category->title,
                'address' => $category->address,
                'parent_uuid' => $category->parent_uuid ? (string) $category->parent_uuid : null,
                'public' => (bool) $category->is_public,
                'publications' => $category->publications()->count(),
            ])
            ->values()
            ->all();
    }
}
