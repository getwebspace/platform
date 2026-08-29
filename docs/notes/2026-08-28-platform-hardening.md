# Session notes — 2026-08-28: correctness pass, CI, search/N+1, auth hardening, API keys

Personal reference for a long session of work on `getwebspace/platform`. Not a
user-facing changelog — this is for future-me: what changed, why, what broke
along the way, and what's still open. Read the "Gotchas" section first if
something in this area is misbehaving; it's the highest-value part.

Commits so far: `e86ad490` (correctness pass), `ea0ba43c` (search + service
refactor), `1eafb5d3` (db optimization / N+1), `0d921203` (auth hardening +
recovery + confirmation), `9a3b727c` (CI cache fix). The API-key redesign
(last section below) is **still uncommitted** as of this writing — 30 files
changed/added in the working tree.

---

## 1. Correctness pass (first review)

Read through `src/` cold and fixed a batch of independent small bugs. Worth
skimming the list once — several are the kind that silently do the wrong
thing forever without ever throwing:

- `FileService::createFromPath()` — `chmod($dir, 444)` was decimal, not
  octal (`0o444`). Uploaded files ended up group-writable.
- `User::avatar()` cached its result in a **method-static** (`static $path`),
  so the first user rendered in a list poisoned the avatar for every other
  user in that same PHP-FPM worker's remaining requests. Also a stray `?`
  in the URL silently dropped `background`/`color` from the ui-avatars URL.
- `CORSMiddleware` — missing parens: `$origin && in_array(...) || $value === '*'`
  meant `entity_cors_origin=*` with no `Origin` header sent back an **empty**
  `Access-Control-Allow-Origin`.
- Email ban `whitelist` mode was inverted — with more than one domain listed,
  every address failed at least one domain and got banned.
- `UseSecurity::getPrivateKey()` returned before caching into the static
  var, so the PEM was re-read from disk on every JWT issue (functional bug,
  not just perf — see PEM-keys gotcha below, this trait comes up a lot).
- `UserService::read()` — `lastname` search queried the `firstname` column
  (copy-paste).
- Remote file download (`FileService::getFileFromRemote`) only followed
  302, and concatenated `Location` onto the current host even when
  `Location` was already absolute — broke on CDN redirects. Now follows
  301/303/307/308, resolves relative/protocol-relative locations, caps at 5
  hops.
- Two dead code paths removed: a Twig function (`catalog_order_status`)
  registered with no method behind it, and `isCurrentPath()` still calling
  Slim 3's `$routeCollector->pathFor()`, which doesn't exist in Slim 4.

## 2. CI pipeline

`.github/workflows/docker.yml` had no test gate at all — it just built and
pushed on every tag. Added a `test` job that `build` now `needs:`.

Getting the suite to actually pass in CI surfaced two real bugs, not just
CI plumbing:

- **Migration `20250226101251` used `change()` with `changeColumn`**, which
  phinx cannot auto-reverse. `TestCase::setUp()` does `rollback -t 0 -f` then
  `migrate` before every test; the rollback died on this migration's first
  step and left the schema (and data) in place, cascading into
  `ParameterAlreadyExistsException` and friends across a dozen unrelated
  tests. Fixed by splitting into explicit `up()`/`down()`.
- **No SQLite busy timeout.** Once the migration above was fixed, the suite
  went *flaky* instead of reliably broken — 2 failures out of 5 runs, always
  `database is locked`. The API tests drive the app over real HTTP
  (nginx+php-fpm), so phpunit's migration step and a live request can touch
  the same `.sqlite` file concurrently. `PDO::ATTR_TIMEOUT` wasn't set
  anywhere. Set it to 15s in both `src/dependencies.php` (app connection) and
  `phinx.php` (`attr_timeout`). This isn't test-only — the same lock error is
  reachable in production under any two concurrent writes, since SQLite is
  the default DB driver for this project.
- **Docker buildx cache-to fails on the default driver.** Added
  `cache-from/to: type=gha` to speed up the `build` job, forgot
  `docker/setup-buildx-action` on that job (the `test` job had it, `build`
  didn't). The default `docker` driver can't export a cache —
  `ERROR: Cache export is not supported for the docker driver`. Reproduced
  locally with `docker buildx create --driver docker-container` vs the
  default builder before fixing; one-line fix (add the setup-buildx step).

## 3. Search, N+1, and the service layer shape

All 18 services (`App\Domain\Service\*\*Service`) follow the identical
`create/read/update/delete` shape, each with its own hand-rolled
"apply criteria → order → limit → offset" block. That uniformity is what
made this refactor safe to do in one pass — see `AbstractService` for the
result:

```php
// per-service, opt-in
protected static array $search_columns = ['title', 'address'];   // non-strict `search`
protected static array $eager = ['category', 'attributes', 'files']; // default preload
```

- `buildQuery(Builder $query, array $criteria, array $data)` — the one call
  every `read()`/`count()` now ends with. Applies eager-loading (`$eager`
  unless `$data['with']` overrides it), strict criteria (`applyCriteria`),
  non-strict search (`applySearch`), then order/limit/offset.
- `applySearch()` is the interesting one: **SQLite's own `lower()` only
  folds ASCII** — `lower('Ёлка')` comes back unchanged. On SQLite,
  `lowerFunction()` registers a PHP-backed `mb_lower` via
  `PDO::sqliteCreateFunction` (lazily, once per connection); MySQL just uses
  native `lower()`. The needle's own `%`/`_`/`\` are escaped so user input
  can't inject wildcards. Verified Cyrillic case-insensitivity end to end
  before shipping this — it's the one part of this refactor that doesn't
  work by accident on MySQL and needs the SQLite-specific path.
- `limitByList()` replaced ~7 copies of the same
  `is_array($x) ? array_intersect(...) : in_array(...)` dance for
  enum-like criteria (status, type).
- Net effect: **-257 lines** across the service layer while adding the
  `search` parameter to 16 services.
- **Breaking change**: two services had a fuzzy match hiding inside what
  looked like a strict filter — `ProductService::read(['title' => ...])`
  did a `LIKE %...%`, `UserService::read(['firstname' => ...])` did
  `LIKE 'x%'`. Both are strict equality now; the fuzzy behaviour moved to
  `search`. This bit the admin UI (see below) and would bite any external
  API consumer relying on the old `?title=partial` behaviour.

### N+1 via eager loading

`toArray()` on several models reached into relations through
`->getResults()`, which **always issues a fresh query** — bypasses whatever
was eager-loaded, so `->with([...])` barely helped. Necessary because
`attributes`/`relations` as relation *names* collide with Eloquent's own
model properties, so `$this->attributes` is not what you think it is. Fixed
by switching to `getRelationValue('attributes')` etc. Measured on 10
products × 2 attributes × 2 related products each:

| | before | after |
|---|---|---|
| serialize product list, no explicit `with` | 101 queries | 12 (via `$eager`) |
| single product page | 11 | 4 |

`read(['with' => [...]])` overrides the default set; `with: []` disables
preloading for lists that never get serialized.

### Recursion guard

`CatalogProduct` can reference other products via `relations` (self
join). `toArray()` used to recurse into `$item->toArray()` unconditionally
— two products referencing each other reproduces as
`Allowed memory size exhausted`. Two independent recursion paths existed:
the explicit call, and Eloquent's own automatic serialization of loaded
relations inside `parent::toArray()`. Both needed closing — a depth flag for
the explicit path, `'relations'` added to `$hidden` for the automatic one.
Verified: top-level `relations` still serializes one level deep, nested
level comes back empty rather than recursing.

Also added null guards for `category`/`user` on `CatalogProduct::toArray()`
/`Publication::toArray()` — a deleted category/author used to fatal on
`$this->category->uuid`.

### Admin autocomplete regression (self-inflicted, found later)

`public/assets/js/cup/script.js` had three `keyup`-driven autocomplete
widgets (product picker for an order, related-product picker, user picker
for an order) that relied on the *old* fuzzy `title`/`firstname` behaviour
described above. Once that became strict, typing into those fields silently
returned nothing. Fixed by switching the three `$.get(...)` calls to send
`search` instead of `title`/`firstname`. **Lesson: when a service parameter
changes semantics, grep the admin JS for it too — it calls the same
`/cup/api/v1/...` endpoints directly, not through any PHP layer that would
have caught this.**

## 4. Auth review: security fixes + password recovery + email confirmation

### Security fixes (each reproduced before/after, not just read-through)

- **IDOR in session revocation.** `BasicAuthProvider::revoke($token, $uuid)`
  deleted the token matching `$uuid` without checking it belonged to the
  caller's own account — any authenticated user could pass another user's
  token UUID and kill their session. Reproduced: Alice's own refresh token,
  Mallory's token UUID → Mallory's session count dropped. Fixed by resolving
  the owner from the *presented* token first, then scoping the query to
  `$owner->tokens()`.
- **Open redirect** — `?redirect=` flowed unvalidated into `Location` across
  six actions (login/logout/register/refresh/revoke, both API and common).
  Added `AbstractAction::getRedirectParam()` / `isLocalPath()`: must start
  with `/`, not `//`, no backslash (browsers fold `\` to `/`, so
  `/\evil.tld` is also an open redirect), no control characters.
- **Auth cookies had no `HttpOnly`/`SameSite`/`Secure`** — nine call sites
  set `access_token`/`refresh_token` with raw `setcookie()`. Centralized
  into `AbstractAction::setAuthCookies()` / `clearAuthCookies()`; `secure`
  auto-detects HTTPS including behind `X-Forwarded-Proto`.
- **No login throttling.** Added `UseThrottle` trait (file-cache backed,
  keyed by `login + ip` so one attacked account doesn't lock out everyone
  behind the same NAT). Configurable in admin (`user_login_attempts`,
  `user_login_block_time`), `0` disables. Applied to both the front-end
  login and the cup admin login.
- **`password_again` was collected by every registration form and silently
  discarded** — not an Eloquent-fillable field, so a typo in the
  confirmation field produced an account with an unknown password. Added
  `PasswordsNotMatchException`, checked in `UserService::create()`.
- Two functional side-fixes found while in this code: `UserRegisterAction`
  appended a bogus "wrong captcha" error on *every* validation failure
  (missing `else`); `/cup/login` on plain `GET` showed the same captcha
  error to every visitor, same root cause.

### Password recovery (`/user/recovery`)

Didn't exist at all, despite an admin setting
(`user_lostpassword_template`) implying it should. Built end to end:

- Link is a **JWT bound to the current password hash**
  (`hash('sha256', $uuid . '|' . $password_hash)` embedded as
  `fingerprint`), not a stored code — so changing the password (including
  via the recovery flow itself) invalidates every outstanding link for that
  account with no cleanup job needed. TTL 1 hour via
  `encodeJWT($sub, $uuid, $data, $ttl)`.
- Response to "does this account exist" is identical either way (no
  enumeration).
- Completing recovery kills every existing session (`$user->tokens()->delete()`)
  and clears the requester's own cookies.
- Mail templates added under `theme/shop/` (that theme isn't committed to
  this repo — see below): `user.mail.recovery.twig`,
  `user.mail.register.twig`, `user.mail.recovery-done.twig`,
  `user.lostpassword.twig`. Three new mail toggles + template-name settings
  added to `cup/parameters/index.twig`.

### Email confirmation (opt-in, `user_confirmation` setting, default off)

Same JWT-without-storage pattern, factored into a shared trait
(`UseConfirmation`) since both `UserRecoveryAction` and the new
`UserConfirmationAction` needed it. New `User::status` value:
`Status::CONFIRMATION` (added to the enum, not a new column). Registration
sets that status when confirmation is required instead of `WORK`; login
throws `UserNotConfirmedException` for accounts stuck there
(distinct from "wrong password" — doesn't count against the login
throttle, since the credentials were actually correct).

Resend flow (`/user/confirm` with no token) exists specifically because an
expired confirmation link would otherwise strand an account: can't log in
(not confirmed), can't re-register (address already taken).

Welcome mail now fires on `common:user:confirmed` instead of
`auth:user:register` when confirmation is on, so it doesn't go out before
the address is proven.

## 5. Full codebase review + Docker buildx fix (separate ask)

User reported the tag-triggered `build` job failing with
`Cache export is not supported for the docker driver` — see the CI section
above, same root cause fixed the same way (`docker/setup-buildx-action` was
missing from *this* job even though `test` had it).

Did a fuller inventory pass afterward and published findings as an
artifact. Two more real holes found (both fixed during the later API-key
work, section 6):

- `GET /api/v1/parameter` returned the *entire settings table* — SMTP
  password, `entity_keys` (at the time, the API write credential itself),
  payment gateway keys — to anyone the access policy let through, which at
  the default `entity_access=user` setting means any logged-in customer.
- `GET /api/v1/user` serialized the Argon2id password hash for every user —
  no model except `UserGroup` declared `$hidden`.

Also flagged (not yet acted on): `twig/twig` and its two extras are pinned
to *exact* versions (`v3.11.2`, `v3.8.0`) rather than a caret range, so
`composer update` will never move them — looked like an accident, not a
decision, when I found it. **Note (added later):** a concurrent session
bumped the base image to PHP 8.4 and explicitly disabled the opcache JIT,
citing "timing-sensitive miscompilations on this codebase under 8.4" — that
may well be the same issue that made touching Twig's version feel
dangerous. Worth reading that Dockerfile diff before assuming Twig itself
is the problem.

## 6. API keys — full redesign (uncommitted as of this note)

The old system: `entity_keys` was a newline-separated textarea of
client-generated pseudo-UUIDs, compared with `hash_equals` in a loop, and
any valid key got **full CRUD on every entity**. Replaced with scoped,
revocable, JWT-backed keys.

### Shape

- New table `api_key` (migration `20260828140000`):
  `uuid, title, scopes (json), is_full_access (bool), status, date`.
  `scopes` = `{"read": ["catalog/product", ...], "write": [...]}`.
- The credential handed to an integration is a JWT (`sub: 'api-key'`,
  `uuid: <row uuid>`, **no `exp` claim** — added a `$ttl === 0` sentinel to
  `UseSecurity::encodeJWT()` meaning "doesn't expire by clock", used only
  here). Nothing secret is stored at rest beyond the same private key every
  other signed link in the app depends on — the token is *re-derivable* from
  the row's uuid at any time, so the admin "view token" button just
  re-signs it on demand.
- Every request still does a DB lookup by uuid (`ApiKeyService::read`,
  filtered to `status = work`) rather than trusting the token's embedded
  claims for anything beyond identity — so revoking a key (or narrowing its
  scopes) takes effect on the **very next request**, no re-issue needed.
  Verified live: same token, `status → revoke` via the admin form, next
  call → 401.
- `App\Domain\References\ApiEntity` is now the single source of truth for
  what `/api/v1/{entity}` maps to (`MAP`), what's cup-only
  (`CUP_ONLY_MAP` — currently just `parameter`), and the human labels for
  the scope picker (`LABEL`/`options()`). `EntityAction::getService()` and
  the scope-picker `<select>` both read from here now instead of each
  having their own hardcoded list.
- `EntityAction::resolvePermissions()`: cup requests (`/cup/api/v1/...`)
  get full read+write unconditionally (already gated by session auth
  upstream); an API-key request is checked against `ApiKey::can($entity,
  $mode)`; anything else (plain user session, or `entity_access=all` with no
  auth at all) keeps the old blanket behaviour — read open, write always
  needs a key. `is_full_access` skips the scope arrays entirely (the
  equivalent of the old flat key) but still **cannot** reach
  `CUP_ONLY_MAP` entities — `parameter` is unreachable from `/api/v1` no
  matter what a key is scoped to.
- Admin UI: `/cup/api-key` (list/create/edit/delete), following the same
  page-based CRUD pattern as every other admin section (`cup/user/group` was
  the template) rather than a modal — matches the rest of the codebase, and
  there's no existing modal-based CRUD anywhere to be consistent with.
  Scopes are two multi-selects (`scopes[read][]`, `scopes[write][]`)
  against `ApiEntity::options()`. Old textarea + the client-side random-key
  JS generator in `cup/script.js` removed outright.

### Breaking change

Old flat keys stop working the moment this ships — format changed from a
plain string to a JWT. Any existing integration (this repo's own
`plugin/TradeMaster` config field included, though that plugin isn't
tracked here) needs a freshly issued key from `/cup/api-key`.

### Bugs found *while* building this (all fixed, all in the same batch)

1. **`with[]` relation-injection leaking refresh tokens** — my own
   `$eager`/`with` mechanism from section 3, reachable through the public
   API's query string. `GET /api/v1/user?with[]=tokens` forced eager-load
   +serialize of the `tokens` relation, which includes `unique` — the raw
   refresh-token secret — for every user in the result, regardless of who's
   asking. Fixed in `EntityAction::getParamsQuery()`: `with` is now stripped
   from the query string unconditionally. `with` is an internal `read()`
   knob for PHP callers, never a caller-facing filter — **if a new relation
   ever needs default preloading, add it to `$eager`, don't rely on `with`
   staying un-exploitable from HTTP.**
2. `User` missing `$hidden = ['password']` (section 5, fixed here).
3. `parameter` removed from the public entity map (section 5, fixed here).
4. **Missing PEM keys caused a 500, not a 401.** `UseSecurity::decodeJWT()`
   throws a plain `\RuntimeException('Not exist PEM keys files')` when the
   key pair doesn't exist yet (fresh install, before `/cup/system` has ever
   been visited — see the open item below). Neither `checkAPIKey()` nor the
   pre-existing `findUser()` caught it, so any JWT verification attempt on
   a keyless install bubbled up as an uncaught exception → Slim's error
   handler → 500 with a stack trace, instead of the "not authenticated" 401
   that should obviously be behind any failed credential check. Caught
   `\RuntimeException` alongside the JWT library's own exceptions in both
   places. **This was a pre-existing bug in `findUser()`, not something I
   introduced** — just never triggered before because the old key check was
   a plain string comparison, not a JWT decode.
5. Admin autocomplete regression — see section 3, actually surfaced and
   fixed while writing test scenarios for this feature, not the earlier one.

### Test infrastructure changes

`tests/TestCase.php` now auto-generates a throwaway RSA key pair in
`setUpBeforeClass()` if `var/*.secret.key` don't exist — the old flat-key
tests never needed real JWT verification, since a plain string comparison
doesn't. Also added `createApiKeyToken(array $scopes = [], bool
$fullAccess = true)` helper. Rewrote the three tests that hardcoded
`entity_keys` (`CatalogCategoryAPITest`, `CatalogProductAPITest`,
`CommonAPITest::testAPIModeKeySuccess`) to use it, and added
`testAPIKeyScopeIsEnforced` / `testAPIKeyRevocationTakesEffectImmediately`.

---

## 7. Dependency update — why Twig was pinned (follow-up session)

Every direct dependency was moved to its current release except one
deliberate hold. `composer audit` went from **41 advisories across 9
packages** to **zero**.

### The Twig pin: cause found, one line

`twig/twig` had been pinned to an exact `v3.11.2` (and the two extras to
`v3.8.0`) because "some extension stops working when Twig is updated". The
actual cause was neither an extension nor a dependency conflict — **no
package in the tree constrains Twig at all**, they all accept `^3`.

`App\Domain\AbstractExtension` implements `Twig\Extension\ExtensionInterface`
directly rather than extending Twig's own `AbstractExtension`, and its
`getOperators()` returned `[]`. Twig's own base class returns `[[], []]`.
Bisected the behaviour change to **Twig 3.21.0**, which removed the guard in
`ExtensionSet::addExtension()`:

```php
// <= 3.20 — an empty array is falsy, so the whole validation is skipped
if ($operators = $extension->getOperators()) { ...check count === 2... }

// >= 3.21 — validated unconditionally, count([]) === 0 now throws
$operators = $extension->getOperators();
if (2 !== \count($operators)) { throw ... }
```

The symptom is maximally misleading: **every single page** 400s with
`An exception has been thrown during the compilation of a template
("...getOperators() must return an array of 2 elements, got 0.") in
"p400.twig"` — the error page itself can't compile either, so nothing in the
message points at operators being the problem, and it looks like a template
bug. Fix is one line in `src/Domain/AbstractExtension.php`: return
`[[], []]`. All three plugin Twig extensions inherit from this class, so
that single change covers them too.

**Verification that mattered here:** the test suite passed on Twig 3.28
*before* the fix — the 198 tests barely render templates. The breakage only
appears when actually requesting pages. Rendered all 28 admin + public pages
over HTTP as the real check.

### TNTSearch held at ^4.4 deliberately

The only dependency not updated. `teamtnt/tntsearch` 5.x tightened
`SqliteEngine::saveToIndex(Collection $stems, int $docId)` to an **int**
doc id and declares `doc_id INTEGER` in its schema; this project indexes by
**UUID string** (`$indexer->setPrimaryKey('uuid')`). SQLite's type affinity
let 4.x store UUID strings in that column happily; 5.x rejects them at the
PHP type level. Supporting 5.x means adding an int↔UUID mapping layer to
the search index — a feature project, not a version bump.

Cost of holding: **none security-wise** — `composer audit` reports no
advisories for 4.4. Also note 5.x moved `Support\Tokenizer` to
`Tokenizer\Tokenizer` (leaving only the abstract + interface behind as
deprecated shims), so a future migration needs that import changed too.

### What moved

| package | from | to |
|---|---|---|
| illuminate/* (5 pkgs) | 11.51 | **13.29** |
| phpunit/phpunit | 11.5 | **13.3** |
| twig/twig | 3.11.2 (pinned) | **^3.28** |
| twig/intl-extra, string-extra | 3.8.0 (pinned) | **^3.26 / ^3.24** |
| guzzlehttp/guzzle (dev) | 7.10 | **8.1** |
| symfony/var-dumper | 7.4 | **8.1** |
| firebase/php-jwt | 6.11 | **7.1** |
| phpmailer/phpmailer | 6.12 | **7.1** |
| sendpulse/rest-api | 2.0 | **3.0** |
| bacon, monolog, ramsey/uuid, phinx, slim, php-cs-fixer, phpspreadsheet | — | latest patch/minor |

Illuminate 11 → 13 (two majors) and PHPUnit 11 → 13 both went through with
**no code changes at all** — worth knowing, since that was the change I
expected to be painful.

### Verified beyond the test suite

Tests alone were not enough (see the Twig note above). Also exercised by
hand on the built image: all 28 pages render; TNTSearch indexing task runs
to `done` and a real search returns UUID hits; search over HTTP; QR code
generation (bacon 3.1); mail template rendering; Monolog; Eloquent queries +
the `search` parameter + catalog serialization on Illuminate 13. Full suite
run 6× consecutively on a clean CI image, 198/198 each time.

### Incidental find, not fixed

`bin/task_worker.php` reads `$action = $_SERVER['argv'][1] ?? null;` then
passes it to `AbstractTask::workerHasPidFile(string $action = '')` — running
that script with no argument is an instant `TypeError` under
`declare(strict_types=1)`. Pre-existing, unrelated to the update, and not on
any normal path (`AbstractTask::worker()` always passes a class name; cron
runs `cron_worker.php`, not this one). Only bites someone running the worker
by hand.

---

## Gotchas worth remembering

- **`php-fpm` does not persist anything across requests** by default —
  each HTTP request is a fresh PHP process/bootstrap. Twice this session I
  mutated a `params` row directly via SQL mid-debugging and got confused
  when the change "didn't take" — the actual cause both times was that I'd
  wiped the SQLite file *after* setting the param (via a `rm -f
  database-test.sqlite && phinx migrate` reset) and forgot to reapply it,
  not any kind of process-level caching. There *is* an `ArrayStore`
  (`HasParameters::from_cache()`), but it's constructed fresh per-request
  along with the rest of the DI container — it doesn't survive between
  `curl` calls in dev the way you'd expect an in-memory cache to.
- **SQLite migrations using `change()` with `changeColumn` are a trap.**
  Phinx can't auto-reverse them; `rollback` throws partway through and
  leaves the schema half-migrated. Always write explicit `up()`/`down()`
  for `changeColumn`.
- **SQLite's `lower()` is ASCII-only.** Any future case-insensitive
  comparison needs the same `sqliteCreateFunction('mb_lower', ...)` pattern
  `AbstractService::lowerFunction()` uses, or it'll silently misbehave only
  in non-Latin locales.
- **`docker buildx build --cache-to` needs a non-default driver.** The
  `docker` driver (what you get without `docker/setup-buildx-action` or a
  local `docker buildx create`) cannot export a cache at all — fails the
  whole build, not just skips caching.
- **`opcache.validate_timestamps` being On doesn't mean instant pickup** —
  bind-mounted file edits inside a long-running dev container sometimes
  needed an explicit `docker restart` to show up during this session's live
  debugging. If a code change "isn't taking effect" in a running container,
  restart before assuming the bug is elsewhere.
- **Model relation names can shadow Eloquent internals.** `attributes` and
  `relations` as relation method names collide with the base `Model`
  class's own properties — `$this->attributes` is not the relation.
  `getRelationValue('name')` is the safe way to read a relation that might
  have this problem; `->getResults()` works but always re-queries.
- **Any `read()` parameter that's a "PHP-internal knob" (like `with`) must
  be stripped before it reaches an HTTP-facing query string**, not just
  documented as internal. `EntityAction::getParamsQuery()` merges
  `$_GET` wholesale into service `read()` calls — treat that merge as
  hostile input, not a convenience.
- **`plugin/` and `theme/shop` are both outside this git repo** (gitignored
  / kept in a separate project the user maintains and copies in). Changes
  to either only exist in the working tree here — they need to be manually
  carried over, they won't show up in `git diff`/`git log` for this repo,
  and a `grep`-based audit of "does anything else use this" must include
  them by hand since they're invisible to normal repo-wide search
  assumptions like "no callers found in `git grep`".

## Still open

1. **PEM signing keys only get generated on first visit to
   `/cup/system`.** A fresh install can create a user via `/user/register`
   and then 500 (well — now cleanly 401, see fix #4 above, but still can't
   actually log in) the moment anything tries to issue or verify a JWT,
   until an admin happens to open the install page. Filed as its own task
   (`task_3f4d14a6`): generate at container startup
   (`docker-entrypoint-init.d`) with a lazy, atomic-write fallback for
   non-Docker installs. **Note for future-self:** section 6's fix #4 makes
   the *symptom* less alarming (401 instead of 500) but does not fix the
   underlying gap — a key-less install still can't complete registration
   end-to-end.
2. ~~**`twig/twig` and its extras pinned to exact versions.**~~ **RESOLVED** —
   see section 7 below.
3. **A concurrent session modified `docker/Dockerfile`,
   `docker-compose*.yml`, `.dockerignore`, and
   `docker/rootfs/usr/local/etc/php/conf.d/custom.ini`** partway through
   this one — multi-stage build, PHP 8.3 → 8.4, JIT disabled. Not reviewed
   or tested by me beyond confirming the full test suite still passes on
   top of it (6/6 clean runs). Worth a deliberate look before it ships,
   independent of everything else in this note.
4. HTTP-layer test coverage is still thin outside what this session added —
   119 actions, a handful of API test files. Everything built in sections 4
   and 6 was verified by hand over real HTTP during the session; only the
   API-key pieces have actual PHPUnit coverage now.
5. No static analysis (PHPStan/Psalm) in CI — several of the correctness-pass
   bugs (dead Twig function, Slim 3 leftover call, method-static cache bug)
   are exactly what a level-5 baseline catches for free.
