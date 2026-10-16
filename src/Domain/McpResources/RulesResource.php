<?php declare(strict_types=1);

namespace App\Domain\McpResources;

use App\Domain\AbstractMcpResource;

class RulesResource extends AbstractMcpResource
{
    public const URI = 'platform://guide/rules';
    public const NAME = 'rules';
    public const TITLE = 'How the tools behave';
    public const DESCRIPTION = 'What the tools of this server do and do not do: drafts, addresses, what is never deleted, who can not be edited.';
    public const MIME = 'text/markdown';

    public function read(): string
    {
        return <<<'MD'
            # How the tools of this site behave

            - **Nothing is deleted.** "Hide" or "block" is a status, the record stays and can be restored in the admin panel.
            - **Nothing is sent to people.** Tools never send e-mail or messages; when a reply is needed they return a draft text.
            - **Changes to the live site** (pages, published publications, prices, product data) are visible at once. Show the operator what will change and wait for a yes.
            - **Drafts.** A publication has no status: it is shown on the site when its date has come. A draft is a publication dated far in the future; `publish=true` shows it now, `publish_at` schedules it.
            - **Addresses (URLs) are kept** when a title changes, so existing links do not break. Pass `address` only when the operator asks for it.
            - **HTML** written through the tools is cleaned: scripts, iframes, forms, event handlers and `javascript:` links are removed.
            - **Bulk price change** is a preview until `apply=true` with the previewed `expected_count`.
            - **Staff accounts** (groups with admin panel access) can not be edited or assigned, user status can be only `work` or `block`, passwords are never touched.
            - **Dates** in filters: `YYYY-MM-DD` covers the whole day, `YYYY-MM-DD HH:MM` is exact.
            - Lists are paged: `limit`, `offset`, and `has_more` tells if there is a next page.
            MD;
    }
}
