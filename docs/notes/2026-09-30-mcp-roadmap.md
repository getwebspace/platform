# MCP server: roadmap by waves

Endpoint `POST /api/v1/mcp` (Streamable HTTP, no SSE), tools live in `src/Domain/McpTools/`,
registered in `src/dependencies.php` (container entry `'mcp'`). Plugins add tools with
`$this->addMcpTool(...)` (trait `HasMcpTools` in `AbstractPlugin`). Tests: `tests/API/McpTest.php`.

Goal: gradually cover the platform functions so an AI assistant can help the person who handles the site data.

## Rules for every tool

- Extend `AbstractMcpTool`; set `SCOPE` to an `ApiEntity` name. Access = same as `EntityAction`:
  an API key needs read (or write for `READ_ONLY = false`) scope for it. Without `SCOPE` only
  full-access keys may use private/writing tools. No key = only public read-only tools.
- Lists: use `paginate()` / `paginationSchema()` (`items`, `limit`, `offset`, `has_more`), compact
  cards, full record in a separate `*_get` tool. Search through the services' `search` key
  (case-insensitive, works for Cyrillic on SQLite), not raw `LIKE`.
- Do not declare `LIMIT_DEFAULT` / `LIMIT_MAX` in plugin tools as `private` - they are public constants
  of the base class (this broke a plugin once).
- Writing tools: mark `READ_ONLY = false`, describe in `DESCRIPTION` that explicit operator
  confirmation is needed for irreversible steps. Tools never send anything to people
  (mail/Telegram) - they return a draft text instead.
- Every new tool gets a test in `McpTest` (visibility by scopes + behaviour).

## Done

- Foundation: tests, search via services, pagination helpers, API key in tool (`canRead`).
- Wave 0 (first tools): `catalog_category_list`, `catalog_product_search`, `catalog_product_get`, `catalog_order_get`.
- Wave 2: see below (order status, review / guestbook moderation, tasks).
- Wave 3: see below (product / category update, bulk prices, publications, pages).
- Wave 4: see below (users, groups, attributes, files, subscribers).
- Wave 1 (read only): `site_overview`, `catalog_order_list` (status, search, `new_only`, date range
  `date_from`/`date_to`), `reference_list`, `form_data_list`, `form_data_get`.

## Wave 2 - moderation and handling (writes, point-wise) - DONE

- `catalog_order_update_status` - status by title/uuid; publishes `api:catalog:order:edit` like REST does
- `review_list`, `review_moderate` - default = waiting for moderation; `publish` / `hide` (status only, nothing is deleted)
  and the public answer (child row, same as in the admin panel; empty string removes it)
- `guestbook_list`, `guestbook_moderate` - same model, answer is the `response` field
- `task_list` (default = failed, with output) , `task_retry` (only failed/cancelled; e-mail tasks are refused
  so people do not get duplicates)
- Shared helper: `McpTools/Concerns/FindsOrderStatus`
- No `review_get` / `guestbook_get` / `task_get`: list cards carry the text (truncated to 600) and the task output (500).
  Add them if a full text is ever needed.

## Wave 3 - content - DONE

- `catalog_product_update` - title, prices, stock, HTML descriptions, SEO, flags, `status=delete` (hide), move to a category.
  The page address is kept unless `address` is passed (the site's address auto-generation on update would otherwise move the page).
- `catalog_category_update` - title, description, `is_hidden`, `status`, SEO; same address rule.
- `catalog_price_bulk_update` - by percent or amount, filter by category (+nested) and/or search; PREVIEW by default,
  applied only with `apply=true` AND `expected_count` == previewed total; max 500 products, visible ones only, never below 0,
  at least one of category/search is required (no "whole shop" change).
- `publication_list`, `publication_get`, `publication_create`, `publication_update`. A publication has NO status: it is
  shown when `date` <= now. So: draft = date 2099-12-31 (`DescribesPublications::DRAFT_DATE`), `publish` = date now,
  `publish_at` = scheduled, `unpublish` = back to the draft date. Create is a draft unless `publish_at` is given.
  The address is generated as `<category address>/<title>` (otherwise it would be empty and the page unreachable).
- `page_list`, `page_get`, `page_update` - pages have no draft state: the change is live at once (stated in the tool description).
- All HTML written through tools goes through `Concerns/SanitizesHtml` (DOM based): `script`, `style`, `iframe`, `object`,
  `form`, `svg`... are removed, `on*` attributes and `javascript:` / `vbscript:` / `data:` links too (only `data:image/*` except svg is
  kept in `src`). Everything else (headings, lists, tables, images, links) stays.
- `AbstractService` got generic `date_from` / `date_to` for any model with a `date` column (moved from `OrderService`).
- Decisions taken without the operator's answer (can be changed): dangerous tags stripped, address kept, publication created as draft.

## Wave 4 - users and the rest - DONE

- `user_list` (default = active; status, group title, `without_group`, search), `user_get` (by uuid / e-mail / phone / username),
  `user_update`, `user_group_list`. Cards never contain the password, tokens or integrations.
- `user_update` rules: **staff accounts** (a group with non-empty `access` = admin panel entry) can neither be edited nor assigned
  (`DescribesUsers::isStaffGroup`); `status` only `work` / `block` (never delete, never touches `confirmation`); password and username
  are not editable; `company` / `legal` / `messenger` are merged with the current value, not replaced.
- `catalog_attribute_list`, `file_list` (database paging; the `kind` filter by mime prefix is applied in PHP),
  `subscriber_list` (not an API entity -> full access keys only).
- Newsletters: nothing. There is no draft concept and sending must stay with a human.
- Shared helper: `McpTools/Concerns/DescribesUsers`.

## Prompts and resources - DONE

- Base classes `AbstractMcpPrompt` / `AbstractMcpResource`; registry `'mcp'` got `registerPrompt` / `registerResource`; plugins use
  `addMcpPrompt()` / `addMcpResource()` (trait `HasMcpTools`), same as `addMcpTool()`.
- Prompts (`src/Domain/McpPrompts/`): `handle_new_orders`, `moderate_content`, `site_report` (days), `change_prices` (category, change),
  `write_publication` (topic, category), `check_failed_tasks`. A prompt lists the tools it needs in `TOOLS` and is offered only to a
  caller who may use all of them. They are instructions only; every one ends with the rules (confirm, no deletes, no messages to people).
- Resources (`src/Domain/McpResources/`): `platform://site`, `platform://guide/rules` (markdown, how the tools behave),
  `platform://catalog/categories` (tree), `platform://reference/order_statuses`. Access follows `SCOPE` like for tools
  (`Traits/ChecksMcpAccess` is shared by tools and resources).
- Protocol: `prompts/list`, `prompts/get`, `resources/list`, `resources/read`, `resources/templates/list` (empty); unknown or not
  allowed resource = error -32002, unknown prompt / missing argument = -32602.

## Review follow-up (6.4.1) - DONE

Found by an external review of the 6.4.0 server; all valid:

- **Anonymous access** (site setting `entity_access=all`): only data that is public on the site stays open (products, categories without hidden ones,
  attributes, pages). `review_list` (it shows reviews waiting for moderation), `file_list`, `reference_list`, `publication_list` and the
  resources `catalog/categories`, `reference/order_statuses` are `PRIVATE`. `PRIVATE` matters only without a key - with a key the scope decides.
  Rule for a new tool: if it can return something the public site does not show (drafts, moderation queues, internals) - `PRIVATE = true`.
- **A key that was sent but does not work = 401**, also in `entity_access=all` mode (a revoked or garbage key used to turn into anonymous access
  and the client silently saw fewer tools). Only `Authorization: Bearer`, `key`, `apikey` headers and the `apikey` / `access_token` query
  parameters count; foreign `Authorization: Basic` is ignored.
- **Limits are validated, not cut:** `limit` outside 1..100 or a non-integer = tool error (`AbstractMcpTool::intArgument()`).
- **`page_list` / `publication_list`:** `total`, SEO `meta` (title, description, keywords; empty ones stay empty) and `date` in every item.
  Pages and publications have no "changed" date in the database, only `date`. `PageService::count()`, `PublicationService::count()`.
- **`dry_run`** for every writing tool (announced in its schema): runs in a transaction that is rolled back, events are muted
  (`PubSub::silently()`), result is marked `dry_run: true`. `DRY_RUN = false` for tools with effects outside of the database (`task_retry`).
- **Journal of writes:** every writing call is logged (`MCP write` in `var/log/app.log`): tool, API key uuid + title, arguments (long texts
  shortened), outcome, `dry_run`, IP. A table with a UI is not done - add it if the log file is not enough.

## Batch 6.5.0 - reports, creating, search, service tools - DONE

- **Reports (read, `PRIVATE`):** `catalog_sales_report` (period, group by day / week / month / status / category, optional status filter),
  `catalog_top_products` (revenue / quantity / orders), `catalog_stock_report` (out of stock, below the minimum, no price / image / description),
  `seo_audit` (empty / too long / repeated SEO title and description of pages, published publications, visible products and categories;
  per entity filtered by the key's scopes).
- **Creating and editing:** `catalog_product_create`, `catalog_category_create` (both HIDDEN by default - the operator shows them),
  `catalog_order_update` (contacts, delivery, shipping date, comment; not the products and not the status), `publication_category_list`.
  Product fields are shared by create / update in `Concerns/ReadsProductFields`.
- **Addresses:** `Concerns/BuildsAddress` makes the page address like the site does and REFUSES a title it cannot make an address of
  (Cyrillic without the transliteration of the language plugin gives an empty path) - "pass address" instead of a broken page.
  `publication_create` uses it too (it used to create `news/` for such titles).
- **Search and service:** `site_search` (TNTSearch index, drafts not shown), `site_reindex` (starts `SearchIndexTask`), `form_list`, `logs_tail`
  (tail of `var/log/app-*.log`, level / text filter, tokens and passwords hidden; reads the last 2 MB), `site_health`.
- **Prompts:** `seo_check`, `sales_analysis`, `catalog_check`.
- **Money bug found and fixed:** the order line is stored with a NEGATIVE discount; the model counts `max(0, price - |discount|)` and adds the tax on
  top, but the SQL of the admin main page and of the catalog statistics did `price * (1 + tax) - discount`, i.e. ADDED the discount and put it after the tax.
  Revenue was too high for every order with a discount. One formula now: `Dashboard::lineSql()` (main page, statistics, MCP reports); tests compare
  the report with `CatalogOrder::totalSum()`.
- PHP 8.5: `PDO::sqliteCreateFunction()` deprecation silenced in `AbstractService` (a plain PDO has no other way).
- Not done on purpose: order creation by a tool (a phone order should be entered in the admin panel), product import / export tasks, resource templates,
  site settings and plugins (see "Deliberately NOT covered").

## Batch 6.5.1 - settings and the files of the design - DONE

- **`settings_get`** (full access key): the site and plugin settings, read only. A secret is not shown: `hidden: true` + `filled`. Recognised by a word of the
  name (`pass`, `password`, `secret`, `token`, `key`, `private`, `salt`, `hash`, `auth`..., or the name ends with `login`) and by the look of the value
  (JWT, PEM, bot token, a 28+ run of letters / digits without spaces). `notepad_*` (private notes of the administrators) is not shown at all. Long values
  are cut to 500 characters, by `name` the whole text. Settings are still changed only in the admin panel.
- **Design files** (`App\Application\ThemeFiles`, tools `theme_file_list / read / write / history / restore`), paths like the admin editor:
  `theme/...` (active theme) and `resource/...`. Editable: `twig css js html txt md`, up to 300 KB, UTF-8.
  Safety: no `..`, hidden names, links out of the folders, new folders; the write needs `expected_version` (sha256 from `theme_file_read`) so a file
  changed meanwhile is never overwritten; a `.twig` that does not compile is refused; every write keeps the previous text in
  `var/theme-backups/<path>/` (last 20), `theme_file_restore` puts one back (and keeps the replaced text, so a restore is undoable); own `dry_run`
  (`DRY_RUN_SELF`: files are not in the database, the argument reaches the tool); writes are journaled.
  **Write and restore need the site setting `mcp_theme_edit = yes`** (API tab of the settings, OFF by default) and a full access key. Reading does not.
- **Compiled templates:** a working site (no DEBUG) does not compile Twig on the fly - a changed file is not seen until its compiled copy is gone
  (checked: a plain file change keeps showing the old text). `ThemeFiles::clearTemplateCache()` removes the compiled copies (the folders `xx` of two hex digits
  in `var/cache`, nothing else) after every `.twig` write; the admin panel editor (`/cup/editor`) does it now too - it did not.
- Prompt `edit_template` (read -> smallest change -> dry run -> confirm -> apply, backup id, how to undo).
- `AbstractMcpTool::DRY_RUN_SELF` for tools that do the dry run themselves.

## Search index fix (found by a red test)

- `McpTest::testSearchLogsFormsAndHealth` assumed that the cache folder has no search index. A developer's machine has the `*.index` files, CI has not,
  so the test was green on one and red on the other. The tests that depend on the index now put the existing ones aside and put them back
  (`withSearchIndexAside`). Rule: a test must not depend on what the machine has in `var/cache`.
- That test, written properly (`testSiteSearch`), showed a real bug of the platform: `SearchIndexTask` made all three indexes with ONE engine object, whose
  prepared statements stay with the first index it made - the words of publications and pages were written into the index of PRODUCTS, the page / publication
  indexes stayed empty. So the search of the site (and `site_search`) did not find pages and publications on any site that has products. Now an own engine per index.
  After deploying, rebuild the index once (`site_reindex` or the task in the admin panel).

## Deliberately NOT covered

- Deleting anything
- Changing site parameters (`parameter`, secrets; `CUP_ONLY_MAP` in the REST API too). Reading them is `settings_get`, secrets hidden
- API keys management, password changes
- Plugins (they are code in `plugin/` registered in `plugin/installed.php`: enabling, installing and removing is a deployment, not data). A plugin can only ADD tools,
  prompts and resources to the server; the plugin settings are site parameters (`<PluginName>_<field>`) - same as the site settings

## Decisions

- Compact cards instead of full models (cheaper to read, fewer personal data).
- No server-side `dry_run` for everything; clients ask for confirmation of `readOnlyHint = false` tools.
  Bulk operations get an explicit preview step.

## Known gaps / ideas

- `FileService::createFromPath` with a URL: FIXED - only http(s) hosts that resolve to public addresses (`FileService::isPublicUrl`),
  redirects are followed by hand and every hop is checked. The check is skipped under `TEST` (the tests download from 127.0.0.1).
  A DNS change between the check and the download is not covered.
- `OrderService` had no date filter (added: `date_from`, `date_to` in `read()`).
- `FileService::createFromPath` downloads any URL incl. internal hosts (SSRF) - to check callers.
- Project plugins (tochkarb `ExpertModeration`) extend this server; keep the base API stable.
