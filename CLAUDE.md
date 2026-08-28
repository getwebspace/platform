# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

WebSpace Platform (WSE) — a multi-tenant CMS/shop engine: pages, publications,
a product catalog with orders, dynamic forms, a guestbook, a file store with
image conversion, and a plugin/theme system. PHP 8.3+, Slim 4, Eloquent
(via `illuminate/database`) outside Laravel, Twig 3. Ships as a single Docker
image (nginx + php-fpm + cron via runit).

`plugin/` and `theme/*` (except `theme/default`) are **not part of this git
repo** — they're gitignored and maintained in separate projects, copied in
locally. `git grep`/`git log` over them will find nothing; check the working
tree directly if you need to know what's actually installed.

## Commands

All commands run inside the dev container (`make up` first, or point at
whatever's already running):

```bash
make up              # docker-compose up + composer install + phinx migrate
make run-test         # ./vendor/bin/phpunit --configuration phpunit.xml
make run-lint         # ./vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.dist.php
make migrate-up       # phinx migrate
make migrate-down     # phinx rollback
make migrate-create   # phinx create (new empty migration)
make migrate-status   # phinx status
```

Single test / single file, from inside the container (or via
`docker-compose exec platform ...`):

```bash
vendor/bin/phpunit --configuration phpunit.xml --filter testMethodName
vendor/bin/phpunit --configuration phpunit.xml tests/Domain/Service/User/UserServiceTest.php
```

`./phpunit`, `./phpcs`, `./phinx`, `./composer` at the repo root are thin
wrappers around the same commands via `docker-compose exec`, for running
from the host without `make`.

Building the image directly (no compose), e.g. for CI-equivalent testing:

```bash
docker build -f docker/Dockerfile -t platform:local .
```

The image needs `nginx`+`php-fpm` actually running to serve `/api/*` and
other HTTP-level behavior — start it via its normal entrypoint (`CMD
["/bin/docker-entrypoint.sh"]`), not `--entrypoint sh`, when a test depends
on real HTTP. `tests/API/*Test.php` do exactly this (Guzzle against
`http://127.0.0.1:80`), and `TestCase::setUp()` re-migrates the SQLite
database fresh before every test.

## Bootstrap flow

`public/index.php` → `src/bootstrap.php`: builds a PHP-DI container from
`src/settings.php` (env-driven config) → `src/dependencies.php` (service
factories — DB connection, cache stores, Twig `'view'`, etc.) →
`src/services.php`, includes `plugin/installed.php` (where plugins register
themselves against the `'plugin'` container entry), then builds the Slim
`App`. `src/middleware.php` and `src/routes.php` are loaded in
`public/index.php` after that. `config/vars.php` defines the path constants
used everywhere (`SRC_DIR`, `VAR_DIR`, `THEME_DIR`, `PLUGIN_DIR`, ...).

## Domain layer shape

Every domain concept (`User`, `CatalogProduct`, `Page`, ...) follows the same
three-part pattern — read one to understand the others:

- **Model** (`src/Domain/Models/*.php`) — Eloquent, UUID primary keys via
  `HasUuids`, custom `Casts\*` classes for enum-like columns (`Status`,
  etc.) and JSON columns. `$hidden` must be set explicitly per model — it is
  *not* inherited from anywhere, and several models historically didn't set
  it. Check before assuming a field is safe to serialize.
- **Service** (`src/Domain/Service/*/*Service.php`, extends
  `AbstractService`) — the *only* place business rules and queries live.
  Every one implements `create/read/update/delete`. `read()` takes a single
  associative array: some keys are strict-equality criteria, and (since this
  session) two more are handled uniformly by `AbstractService::buildQuery()`:
  - `search` — non-strict, case-insensitive substring match across the
    columns a service opts into via `protected static array $search_columns`.
    Case-insensitivity on non-ASCII text needs SQLite-specific handling — see
    Gotchas.
  - `with` — overrides which relations get eager-loaded (service default is
    `protected static array $eager`). **This is a PHP-internal knob, not a
    caller-facing filter** — anything that forwards raw HTTP query params
    into `read()` (`EntityAction` does) must strip `with` first, or a caller
    can force-serialize an arbitrary relation (this exact hole existed and
    was fixed — see `docs/notes/`).
  A scalar value for a unique-ish field (`uuid`, `title`, ...) routes `read()`
  to `firstWhere`-style single-result lookup and throws a `*NotFoundException`
  on a miss; wrapping it in an array (`['uuid' => [...]]`) instead does
  `whereIn` and returns a `Collection`. This distinction matters when writing
  a caller — don't pass a scalar when you want "give me a possibly-empty
  list".
- **Action** (`src/Application/Actions/**/*Action.php`, extends
  `AbstractAction`) — one per route, `protected function action()`. Three
  route trees mirror three audiences: `Actions/Cup/*` (admin panel, session
  auth), `Actions/Common/*` (public site), `Actions/Api/v1/*` (REST). Actions
  never touch models directly, only services.

`App\Domain\References\ApiEntity::MAP` is the single source of truth for
what `/api/v1/{entity}` and `/cup/api/v1/{entity}` resolve to — check there
before assuming an entity isn't API-accessible. `CUP_ONLY_MAP` entities
(currently just `parameter` — raw settings, including secrets) are reachable
only from `/cup/api/v1/*`, never the public API, regardless of any API key's
scopes.

## Auth

Two independent JWT-based systems, both signed with the same RS256 key pair
(`var/private.secret.key` / `var/public.secret.key`, generated on first
visit to `/cup/system` — **a fresh install has neither file until then**, so
registration/login can fail until an admin opens that page once):

- **User sessions** — short-lived access token (JWT, ~10 min) + long-lived
  opaque refresh token (`UserToken.unique`, a plain sha256, stored server-side)
  for `/auth/refresh-token`. `App\Domain\Traits\UseSecurity::encodeJWT()`
  takes an optional `$ttl`; passing `0` means "no `exp` claim at all" —
  used for tokens whose validity is checked by a DB row's status instead of
  a clock (API keys, see below).
- **API keys** (`App\Domain\Models\ApiKey` / `ApiKeyService`) — a key *is* a
  JWT (`sub: 'api-key'`, `uuid: <row uuid>`, no expiry), but nothing about
  its authority is trusted from the token itself: every request re-reads the
  `api_key` row by uuid and checks `status` + `scopes` fresh, so revoking or
  re-scoping a key takes effect on the very next request with no re-issue
  needed. `ApiKey::can($entity, 'read'|'write')` is the scope check;
  `is_full_access` bypasses it (but never reaches `CUP_ONLY_MAP`). Managed at
  `/cup/api-key`.

Password recovery and e-mail confirmation (`UserRecoveryAction`,
`UserConfirmationAction`, `UseConfirmation` trait) use the same
sign-a-JWT-instead-of-storing-a-code pattern, each bound to something that
changes when the flow completes (password hash, e-mail address) so the link
self-invalidates without a cleanup job.

## Twig / theming

Two independent tree roots feed the same Twig loader: `src/Template/`
(admin panel `cup/*`, shared `mixin/*` includes, error pages — always
available) and `theme/{name}/` (front-end site templates, selected by the
`common_theme` parameter, added to the loader per-request in
`HasRenderer::render()`). `App\Application\TwigExtension` is where all
custom Twig functions/filters live (`parameter()`, `page()`, `catalog_product()`,
`df`/date-format, etc.) — most of them are thin wrappers calling straight
into a domain service.

## Plugins

`plugin/{Name}/{Name}Plugin.php` extends `AbstractPlugin` (or one of the
more specific `src/Domain/Plugin/Abstract*Plugin.php` — OAuth, Payment,
Delivery, Language, Mail, Legacy), registered in `plugin/installed.php`.
Which base class it extends is what determines its kind, not a config flag.
Locale packs (Russian, Ukrainian) are shipped as `AbstractLanguagePlugin`
plugins, each with its own `Locale/*.php` files — a missing translation key
falls back to displaying the raw key string, never an error.

## Gotchas

- **SQLite is the default DB** (`sqlite://./var/database.sqlite`,
  `database-test.sqlite` under `TEST=1`) unless `DATABASE` env is set. Two
  consequences that have caused real bugs: its `lower()` only folds ASCII
  (`AbstractService` registers a `mb_lower` PDO function to work around
  this for `search`), and it allows exactly one writer — `PDO::ATTR_TIMEOUT`
  is set in `src/dependencies.php` and `phinx.php` specifically because
  concurrent writes otherwise fail outright with "database is locked"
  rather than queuing.
- **Phinx migrations using `change()` with `changeColumn` cannot be
  auto-reversed** — write explicit `up()`/`down()` for those, or `rollback`
  dies partway through and leaves the schema half-migrated.
- **Relation names can collide with Eloquent's own `Model` properties** —
  a model with an `attributes()` or `relations()` relation must be read via
  `getRelationValue('name')`, not `$this->attributes`/`$this->relations`,
  or you get the base class's internal array instead of the relation (and
  `->getResults()` "works" but always re-queries, silently defeating
  eager-loading).
- `docker buildx build --cache-to` requires a non-default builder driver
  (`docker/setup-buildx-action` in CI, or `docker buildx create` locally) —
  the plain `docker` driver fails the whole build rather than skipping the
  cache.
- Each request is a fresh PHP-FPM process/bootstrap — there is no
  request-to-request in-memory state to reason about (the `ArrayStore`
  cache included). If a change to the DB "isn't taking effect", the DB
  itself is almost always the answer, not caching.

## More detail

`docs/notes/` holds dated write-ups from past work sessions — narrower and
more detailed than this file (specific bugs found/fixed, before/after
numbers, verification steps). Check there for the history behind a
particular area before re-deriving it from scratch.
