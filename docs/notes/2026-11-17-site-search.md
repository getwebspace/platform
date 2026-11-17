# Site search rework (2026-11-17)

One `App\Domain\Service\Search\SearchService` instead of three copies of the same code
(site action, API action, MCP tool, indexing task).

- Kinds: `catalog_category`, `catalog_product`, `publication_category`, `publication`, `page` - one TNTSearch
  index per kind (`var/cache/{kind}.index`). Add a kind in `SearchService::definitions()`.
- Visibility is one rule for the index and for the result (checked again on search, so a stale index
  cannot show a hidden product, a scheduled publication or a closed category).
- Products are also found by the title of their category (`category` column of the index).
- Reindex builds `*.index.tmp` and renames it over the old file: search works during the rebuild.
- Ranking: scores of different indexes are not comparable, every kind is scaled to its best hit, then merged.
- Site: `/search?q=..&type=page,catalog_product&limit=..&page=..`; theme gets `result` (models, as before),
  `items`, `count` (total), `counts` (per kind), `page`, `pages`, `types`, `available_types`.
- API `/api/v1/search`: returns plain items (type, uuid, title, address, url, excerpt, score), not raw models;
  unknown `type` is 400.
- The result page keeps `X-Robots-Tag: noindex, nofollow` (search results are not for crawlers).
- Not verified when written: no PHP/docker on the machine, tests were not run.
