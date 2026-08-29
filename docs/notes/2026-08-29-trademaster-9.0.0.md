# TradeMaster plugin 9.0.0 — sync rewrite

`plugin/TradeMaster` is not in this repo (see CLAUDE.md), so this is the only
record of what changed. Version 8.1.0 → 9.0.0.

## What was slow

Per product, the download task did: a `ProductService::read(['external_id' => ..])`
(no index on `external_id`, and the service eager-loads `category, attributes,
files, relations, relations.*`), then **two** `update()` calls (data, then address),
each re-running the uniqueness check and a full `attributes()->sync()`. Related
products cost two more of the same reads per relation row. Every page of the API
was preceded by `sleep(3)`, and every page waited for the one before it.

Two more things dominated wall clock:

- every sync marked the whole catalog `status = delete` up front and rebuilt it,
  so every row was written twice even when nothing had changed — and the
  storefront served an empty catalog for the duration;
- every product with a `foto` was queued for download on every sync, so the
  entire image library was re-fetched each time.

## What it does now

The sync is a diff. Existing rows, attribute pivots, relations and attached file
names are read once into memory with the query builder (raw rows, no model
hydration). Each remote item is turned into the exact database row it would
become — by filling a model and taking `getAttributes()`, so the casts still
decide the stored representation — and compared against what is stored.
Only rows that differ are written, in `upsert()` batches of 50 inside short
per-page transactions.

Measured on a 600 product fixture (`tests` were not touched; this was a throwaway
harness against sqlite):

| run | queries | catalog writes |
| --- | --- | --- |
| first import | 76 | 57 statements |
| re-sync, nothing changed | 16 | 0 |
| re-sync, half the prices changed | 23 | 7 |

The old code issued several thousand queries for the same fixture, on every run.

Other changes:

- `TradeMasterPlugin::api()` moved from `file_get_contents` to curl, with
  connect/read timeouts, gzip and 3 retries on transport errors, 429 and 5xx.
  New `apiBatch()` runs requests through `curl_multi` — catalog pages, related
  product pages and the six config lookups now go out four (or six) at a time
  instead of one by one. All `sleep(3)` pauses are gone.
- Nothing is marked deleted up front. Rows the API stops returning are set to
  `delete` at the end, from the diff. An empty `catalog/list` answer now fails
  the task instead of wiping the catalog.
- Only the columns the sync owns are written (`PRODUCT_COLUMNS` /
  `CATEGORY_COLUMNS`). Admin-managed fields — `tax`, `discount`, `special`,
  `quantity`, `type`, `tags` — survive a sync; before, the second `update()`
  path could reset them.
- Product address is computed once (`category/title`), not written and rewritten.
- Images are queued only when the remote photo list differs from the file names
  already attached, and `DownloadImageTask` fetches each unique file once, eight
  at a time via `curl_multi`, then attaches everything with two bulk statements.
- Category → attribute links are now added if missing rather than replaced, so
  filters an admin attached by hand are no longer dropped on each sync.
- Relations are a real sync now: they used to be write-only (the import passed
  `relation`, not `relations`, so nothing was ever cleared).

## Bugs fixed along the way

- `TradeMasterPluginTwigExt::tm_order_external()` declared return type
  `App\Domain\Entities\Catalog\Order` and parameter `App\Domain\Entities\User`.
  That namespace no longer exists — any theme calling it hit a fatal error.
  Now `App\Domain\Models\CatalogOrder` / `App\Domain\Models\User`.
- The upload XML was concatenated unescaped, so a `&` or `<` in a product title,
  description or manufacturer produced a malformed document that TradeMaster
  silently rejected. Everything goes through `htmlspecialchars(ENT_XML1)` now.
- `CatalogUploadTask` mapped `ind1..ind4` by the *position* of whatever
  `address like 'field%'` returned; it now matches `field1..field4` by name.
- `CartConfirm` read `smtp_from` for the bcc — no such parameter exists in this
  codebase, so the admin copy went nowhere. Now `mail_from`, like everywhere else.
- `only_updated` on the upload task filtered in PHP after loading the whole
  catalog; it is a `where` on `date` now.

## Left alone, worth a look

`/api/tm/proxy` (`Actions/APIProxy`) forwards an arbitrary `endpoint` and
`params` to TradeMaster using the shop's API key, and the route is public.
Anyone can reach any TradeMaster endpoint the key is good for. Behaviour was
kept as it is because themes may depend on it, but it wants either an allowlist
of endpoints or an auth check.
