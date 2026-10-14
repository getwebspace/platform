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

### Second pass: baseline 196 -> 108

Real bugs fixed:

- `FormAction` CORS: `mb_strpos($origin, $remote) >= 0` is always true (`false >= 0`), so any caller got its own
  origin echoed in `Access-Control-Allow-Origin` as soon as the form had one origin listed. Now a strict
  comparison with `isSameOrigin()`, accepting `https://site.tld` or a bare `site.tld`; covered by `testFormCorsOrigin`.
- `TokenService::delete()` by UUID called a non-existent `$this->service->findOneByUuid()`.
- `SystemPageAction`: `$group` was read in `finally`, undefined if `create()` threw another exception.
- `TaskRunAction`: `?? '/cup'` never applied to an empty Referer (now `?:`).
- `array_flatten()` had `int $depth = INF`.

Types and docs: middleware `__invoke()` return `ResponseInterface` (also in `AbstractMiddleware`, a child may still
declare the narrower Slim response), `@var X|null` before `->first()` in the services, nullable `date`/`status`/`user`
in the model PHPDoc, enum cast properties documented as `string`, `Dashboard::revenue()` shape, `AbstractTask` params.

### Still in the baseline

- ~60 "always true" checks: `read()` throws on a miss, so `if ($entity = $service->read(...))` is dead code.
  Harmless, removing them is a separate cleanup.
- Eloquent relations returning a builder where the method declares `HasMany` (`products()`, `reviews()`, `orders()`...).
- `CatalogProduct::price()` and the match at ~line 280 have no default arm (`UnhandledMatchError`).
- `ProductExportAction` reads `$model->volume`, which the model does not have.
- `ProductImportTask:296` compares an int column index to `'empty'`.
- `PluginMiddleware` and `AbstractPlugin::$container->set()` rely on `DI\Container` behind `ContainerInterface`.

## nginx

- `server_tokens off`, `X-Content-Type-Options: nosniff`, `Referrer-Policy: strict-origin-when-cross-origin`.
- `send_timeout` 2 s -> 60 s: it is the time between two writes to the client, 2 s cut slow downloads.
- Removed the dead `text/html epoch` entry of the `$expires` map (the key never matched `text/html; charset=UTF-8`).
  Do not "fix" it: `expires epoch` would add a `no-cache` next to the app's own `Cache-Control`.
