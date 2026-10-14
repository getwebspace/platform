# Page routes answered JSON to `Accept: */*`

Report (from a site on 3.2.1): `curl -H 'Accept: */*' /` returned `application/json`
`{"errors":[],"params":{},"data":[]}` with `Cache-Control: public, max-age=3600` and no `Vary`,
so a shared cache could store JSON under the page URL. Reproduced in the code: `respond()` and
`AuthAction::isRequestJson()` both treated `$accept === '*/*'` as JSON, and `?format=json` was checked
before `Accept`.

## Fix

- `AbstractAction::getResponseFormat()`: html by default; `text/html` anywhere in `Accept` wins;
  json only for explicit `application/json` or an XHR; text for `text/plain`.
- `respond()` adds `Vary: Accept, X-Requested-With` to every branch.
- `?format=` is honoured only with `DEBUG` on.
- `AuthAction::isRequestJson()` uses the same method.

## Why the XHR marker

`$.ajax` without `dataType` sends `Accept: */*`. The shipped `mixin/catalog.twig` (cart) and
`mixin/quiz.twig` POST that way and read `res.errors` from the `respond()` JSON. Plain "`*/*` is
html" would break validation errors there. jQuery adds `X-Requested-With` on same-origin requests,
bots and link previews do not.

Behaviour change for themes: `fetch()` without an explicit `Accept: application/json` now gets HTML.

## Same class of bug, found while checking

Public pages were stored by shared caches in cases where they should not be:

- `Slim\HttpCache\Cache('public', 3600)` marked every status, so 404/400 pages and redirects were cached for an hour.
  A page created in the admin kept answering 404 until the cache expired.
- The page language depends on the `language` cookie, `?lang=` and `Accept-Language`, without `Vary`.
- `public/index.php` called `session_start()` on every request: each page view sent `Set-Cookie: PHPSESSID`
  next to `public, max-age=3600`, plus PHP's own `Expires: 1981` / `Pragma: no-cache`.

Fix:

- `PublicCacheMiddleware` replaces the Slim one on the two public groups. `public, max-age` only for an anonymous
  GET/HEAD answered with 200 and no cookie handed out; everything else `private, no-store`. Public answers get
  `Vary: Accept-Language, Cookie`.
- The session starts only when the visitor already has a `PHPSESSID` cookie or code calls `session_ensure()`
  (`catalog_product_view()` when it writes, `OpenAuthProvider::createState()`). `session_cache_limiter('')` stops PHP
  adding its own cache headers. A response that opens a new session is not cacheable.
- `/guestbook[/{page}}]` had a stray `}`, so `/guestbook/2` never matched and fell into the catch-all page route.
- `/xml/{name}` is limited to `[\w-]+`; the response uses `withHeader('Content-Type')` instead of adding a second one.
  Path traversal through `%2f` was not reproducible (nginx/Slim answered 400), the pattern is hardening.

Not changed: exceptions in `AbstractAction::__invoke` always answer JSON. In public routes only `resolveArg()` and a
failed fallback template can reach it, so it is not worth code.

Open: a theme that prints the signed-in user on a cached page. Anonymous responses are cached, signed-in ones are not,
so a user block cannot leak, but a theme must not render per-visitor data for anonymous visitors.

Tests: `tests/API/CommonAPITest.php` (format negotiation, cache headers, cookies, guestbook, xml). Run in the dev container;
note that it starts with `DEBUG=1`, where `?format=` is honoured, `testPageFormatQueryIsDebugOnly` reads that flag.
