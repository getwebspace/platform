# Static analysis (PHPStan) and nginx hardening

## PHPStan

`phpstan/phpstan` as a dev dependency, `phpstan.neon.dist`, level 5, paths `src` and `bin`
(`plugin/` and `theme/` are outside this repo). Run: `make run-stan` or `./phpstan`. CI runs it in the
`test` job after phpunit. Known findings live in `phpstan-baseline.neon` (196 at the start): only new ones fail.
When you fix an old one, regenerate with
`./vendor/bin/phpstan analyse --generate-baseline=phpstan-baseline.neon` inside the container.

Larastan was tried and dropped: it boots a Laravel app (`bootstrap/app.php`, `config_path()`,
`LARAVEL_VERSION`) and the workers die silently without one, so the run hangs. Instead `phpstan.neon.dist`
silences exactly two kinds of Eloquent magic (static calls forwarded to the builder on `App\Domain\Models\*`,
properties read off a generic `Model`). That took the first run from 349 to 196 findings.

### Real bugs it found (fixed)

- `FormService` read the title from an undefined `$page` (copy from `PageService`), so with
  `common_auto_generate_address` a form address was always a random `uniqid()`.
- `ProductExportAction` called the missing `CatalogProduct::getDate()`: the product export with a `date` column crashed.
- `SendMailTask` called a private static through `static::`.

### Found, not fixed (in the baseline)

- `CatalogProduct::price()` / the match at ~line 280 have no default arm: an unknown type is an `UnhandledMatchError`.
- `SystemPageAction::setup_user()`: `$group` may be undefined if `create()` throws something other than
  `TitleAlreadyExistsException`.
- `TaskRunAction`: `getHeaderLine('Referer') ?? '/cup'` never falls back, an empty Referer gives an empty redirect.
- Model PHPDoc says `@property Status $status` while the cast returns a string, so PHPStan reports the
  `=== UserStatus::WORK` checks as always false. They work (login is covered by tests); the PHPDoc is wrong.
- `PluginMiddleware` calls `getHandledRoute()`, `before()`, `after()` that `AbstractPlugin` does not declare.

## nginx

- `server_tokens off`, `X-Content-Type-Options: nosniff`, `Referrer-Policy: strict-origin-when-cross-origin`.
- `send_timeout` 2 s -> 60 s: it is the time between two writes to the client, 2 s cut slow downloads.
- Removed the dead `text/html epoch` entry of the `$expires` map (the key never matched `text/html; charset=UTF-8`).
  Do not "fix" it: `expires epoch` would add a `no-cache` next to the app's own `Cache-Control`.
