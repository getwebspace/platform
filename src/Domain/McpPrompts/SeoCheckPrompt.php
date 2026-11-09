<?php declare(strict_types=1);

namespace App\Domain\McpPrompts;

use App\Domain\AbstractMcpPrompt;

class SeoCheckPrompt extends AbstractMcpPrompt
{
    public const NAME = 'seo_check';
    public const TITLE = 'SEO check and fix';
    public const DESCRIPTION = 'Find records with empty, too long or repeated SEO titles and descriptions and write better ones.';
    public const TOOLS = ['seo_audit'];

    public function getArguments(): array
    {
        return [
            ['name' => 'entity', 'description' => 'page, publication, catalog_product or catalog_category; all if empty', 'required' => false],
        ];
    }

    public function render(array $args = []): string
    {
        $entity = trim((string) ($args['entity'] ?? ''));

        return 'Check the SEO fields of the site' . ($entity !== '' ? " (only: {$entity})" : '') . " and help me fix them.\n\n"
            . '1. Call seo_audit' . ($entity !== '' ? " with entity={$entity}" : '') . ". Give me a short summary: how many records of every kind have each problem.\n"
            . "2. Remember that an empty SEO title can be fine when the template uses the title of the record - say it, do not call every empty field a mistake.\n"
            . "3. Take the problem I choose. For its examples read the record (page_get, publication_get, catalog_product_get) and propose a title (up to 70 characters) and a description (up to 160) written from the real content, no invented facts.\n"
            . "4. Show the proposals as a table: record, was, will be. Apply them one record at a time with the update tool (page_update, publication_update, catalog_product_update, catalog_category_update) only after my confirmation; use dry_run=true first for the first record.\n"
            . $this->rules();
    }
}
