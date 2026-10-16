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

## Deliberately NOT covered

- Deleting anything
- Site parameters (`parameter`, secrets; `CUP_ONLY_MAP` in the REST API too)
- API keys management, password changes

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
