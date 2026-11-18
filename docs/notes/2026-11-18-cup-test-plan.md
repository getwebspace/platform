# Cup (admin panel) tests: plan

State on 2026-11-18: 650 tests, 64.4% of lines covered, `Application/Actions/Cup` at 3%
(94 action classes, ~2000 statements, 91 named routes, 98 Twig templates under `src/Template/cup`).
Nothing here is written yet.

## What the existing tests already give

- Access rules for Cup routes (`MiddlewareTest`): guest redirect, a user without a group, per-route group access.
- `/cup/api/v1/*` (`ApiEntityTest`): the cup-only `parameter` entity, `with` stripping.
- `ThemeFiles` through the MCP tools; the editor *page* (`EditorPageAction`) has no test of its own.
- Helpers: `MakesAuthRequests` (`http`, `makeUser`, `loginAs`, `cookie`), `FakesSmtp`, `RunsTasks`, snapshot database.

## Infrastructure to build first (batch 0, small)

1. `tests/Concerns/UsesCup.php`
   - `adminCookie()`: a group whose `access` is **every `cup:*` route name**, taken by parsing `setName('cup:...')`
     in `src/routes.php` (routes are not loaded in the test container, only in php-fpm), a user in that group,
     a login through `/auth/login`, the `access_token` cookie. Cached per test (the database is reset for each).
   - `cup(method, path, options)`: `http()` with the cookie and `redirects=false`.
   - `assertRedirect($response, $location)`, `assertRenders($path)` (200, a closing `</html>`, no "Slim Application Error").
2. `tests/Cup/CupTestCase.php` (abstract, extends `TestCase`): `setUp` makes the admin; `form()` helper to post `form_params`.
3. A route table test that **fails when a new `cup:*` route has no test** (see batch 1): the list of route names
   taken from `routes.php` minus the list of names the Cup tests mention.

## Batches

Each batch is one test class under `tests/Cup/`, ordered by value. "Smoke" means: GET renders 200 with the admin
layout for an admin, redirects a guest, and POST with nothing changes nothing.

### 1. Route smoke and access (the cheap, wide net) - ~1 class, ~100 assertions
- Every GET-able `cup:*` route as admin: 200 (or the documented redirect), body has the layout and no Twig error.
  This is where broken templates show up (98 of them, none rendered by a test today).
- Every route as guest: 307 to `/cup/login?redirect=<path>`; as a user without access: 307 to `/cup/forbidden`.
- The route-table guard from batch 0.
- Findings to turn into explicit tests: **there is no CSRF protection anywhere** (no token in any form, delete is a plain
  GET link `/cup/{x}/{uuid}/delete`), so a link or an image tag on any page can delete a record while an admin is logged in.
  Test pins the current behaviour; fixing it is a product decision (tokens, or POST-only deletes).

### 2. Login, forbidden, system (installer) - `LoginPageAction`, `ForbiddenPageAction`, `SystemPageAction`
- Login: right/wrong password, unknown user, unconfirmed account, blocked account (known bug: `UserService` fix covers
  `/auth/login`; check the Cup login separately), throttle after N attempts and reset on success, `redirect` kept and
  limited to local paths, cookies set (`access_token`, `refresh_token` path `/auth`), already logged in.
- No lock file: `/cup/login` sends to `/cup/system`.
- Installer (needs `var/.lock` moved away and put back in `tearDown`, **the one test that touches shared state**):
  GET shows the three steps and the health check, POST creates the key pair if missing, the `Administrators` group with
  every route, the first user in it, writes the lock file, redirects to `/cup`; a second POST after install does nothing
  and redirects; missing/duplicate username or e-mail show per-field errors and do not write the lock.
  An existing `Administrators` group is reused.

### 3. Content CRUD, the template of all the others - Page, Publication (+Category), GuestBook, Reference, Form, ApiKey, Group
One parametrised test class driven by a table: `[list path, add path, field set, a bad field set -> error field]`.
For each entity: list renders and shows the row; add (GET form, POST creates, `save=exit` goes to the list, `save=continue`
to the edit page); required field missing / duplicate title / duplicate address -> 200 with the error on that field and
nothing stored; edit (GET form filled, POST changes, unknown uuid and a non-uuid redirect to the list); delete (removes,
unknown uuid is a quiet redirect); the `cup:<entity>:create|edit|delete` PubSub events are published once.
- Known weak spot to cover and fix: `GuestBookUpdateAction` does not catch the service exceptions (an empty name or an
  invalid e-mail is a 500), and it redirects with 301.
- Publication preview (`PublicationPreviewAction`) and the page `type`, `template`, `meta` fields.
- File attachments through `processEntityFiles` on pages, publications, guestbook, reviews (multipart upload in the form).

### 4. Users - `User/*`
- List with filters, search; add (password + `password_again`, group, status), view, edit (change password only when
  filled; cannot leave the account without any identifier; e-mail/phone/username taken), delete (soft: status `delete`).
- Group CRUD with the access list (checkboxes of route names), changing a group takes effect on the next request.
- Subscribers: add, delete, list, duplicates. Newsletter: POST queues a `SendNewsLetterMailTask` with subject/body/type,
  GET shows the form.
- Self-lockout: an administrator removing the access of his own group or deleting himself - decide and pin the behaviour.

### 5. Catalog (28 routes, the biggest block) - `Catalog/*`
- Category: CRUD, nesting (`parent_uuid`, `is_allow_nested`), address generation, template and sort settings, a category
  with children cannot become its own parent (check), delete with products (what happens to them), statistics page
  (`CategoryStatisticAction`: revenue, top products and buyers - seed orders and compare with `Dashboard` numbers).
- Product: CRUD with attributes, relations, dimensions, files; `price`/`priceWholesale` rules, `type`, `stock`; duplicate
  address in a category; delete.
- Attribute: CRUD, the `is_filter` flag, types.
- Order: list with status/date filter, create, update (status change publishes events, e-mails), documents
  (`OrderDocumentAction`, templates from `Reference` documents), dispatch (`OrderDispatchAction`), delete.
- Export: `ProductExportAction` and `OrderExportAction` send an Excel file through the global `header()` function
  (not a PSR response) - over HTTP read the body with PhpSpreadsheet and compare cells with the database; filters
  (`category`, `status`, `from`, `to`).
- Import: `ProductImportAction` queues `ProductImportTask` (the `fields` it passes are ignored by the task: a `todo`), redirects
  with 301 to the Referer (**open redirect**: the Referer header is trusted as is - pin and fix with `isLocalPath`).
- Reviews and questions (`Review/*`): the same three contexts as the public side, reply as a child row, moderation status.

### 6. Settings and tools - `ParametersPageAction`, `RefreshAction`, `TaskRunAction`, `LogPageAction`, `EditorPageAction`, `MainPageAction`
- Parameters: GET shows every group; POST saves `group[key]=value`, an array value is joined by commas, **an empty value
  deletes the parameter** (pin it), a name that did not exist is created, unknown groups are accepted (check what that
  allows: any parameter name can be written, the cup-only `parameter` entity rules apply here too).
- Refresh: JSON diff of the task list (`new`, `update`, `delete`) for known/unknown uuids and for status or progress changes.
- TaskRun: runs only for a POST, the `task` must be a task class. Today `class_exists()` is the only check and the class is
  then instantiated with the container - anything that exists can be built by an admin (a non-task class is a 500 or worse);
  fix with `is_subclass_of(AbstractTask::class)`. Params are passed through; the 301 Referer redirect has the same open-redirect issue.
- Logs: files newest first, severity buckets, tail limit of 1000 lines, a log with a very long line.
- Editor (new, 205 lines, no tests): the JSON api (`list`, `read`, `save`, `create`, `delete`, `history`), paths confined to the
  theme and resource folders (`..`, absolute paths, symlinks, NUL byte, other themes), backup files, compiled-template cache
  dropped after a save, binary and very large files, concurrent change detection. It shares `ThemeFiles` with the MCP tools,
  which are already tested - the page needs the HTTP-level checks and the error statuses.
- Main page (`MainPageAction`, `Dashboard`): KPI, revenue, attention list, activity feed for an empty database, a database with
  every kind of record, and with some modules switched off (`enabled()`); the notepad parameter per user.

### 7. Files and forms data - `File/*`, `Form/Data/*`
- File list/delete, `Image/GetAction` (only images, `small` thumbnails), `Image/DeleteAction` (by `src`: found by name+ext only, so
  two files with the same name and extension in different folders delete the wrong one - check).
- Form data: list (spam filter `?spam=1`), view, delete, restore from spam, **preview writes the visitor's message into the
  response unescaped** (stored XSS in the admin's session: pin it and escape or send `Content-Type: text/plain`), an unknown
  uuid is a 500 (no `FormDataNotFoundException` handling in `DataPreviewAction`).

## Order of work and size

| Batch | Contents | New tests (est.) | Real code risk |
|---|---|---|---|
| 0 | helpers, route guard | 3 | none |
| 1 | route smoke + access | 6 data-driven | low, finds template errors |
| 2 | login, installer | 18 | the lock file |
| 3 | content CRUD table | 45 | GuestBook 500s |
| 4 | users, groups, newsletter | 25 | self-lockout |
| 5 | catalog, exports, import | 55 | open redirect, export format |
| 6 | settings, task run, editor, dashboard | 40 | arbitrary class, path confinement |
| 7 | files, form data | 15 | stored XSS, wrong file deleted |

About 200 tests; expected Cup coverage from 3% to roughly 75-80% of its lines (the rest is template-side branches
and the Excel export columns). Total coverage 64% -> ~72%.

## Decisions needed from the owner before the tests pin behaviour

1. CSRF: tokens on every form and POST-only deletes, or leave as is for an admin tool on a trusted network.
2. Open redirects from `Referer` (`ProductImportAction`, `TaskRunAction`, a few others): fix with `isLocalPath` - yes/no.
3. `TaskRunAction`: restrict to `AbstractTask` subclasses - yes/no (a plugin may register tasks that do not extend it).
4. Form data preview: escape or serve as text.
5. Whether an admin may delete or lock himself out.

## Practical notes

- Reset the in-process cache per test (done in `TestCase::setUp`); Cup actions read parameters through it.
- The installer test is the only one that must not run in parallel with anything else.
- Excel responses: write the body to a temp file and load it with PhpSpreadsheet.
- Run the Cup suite alone with `phpunit tests/Cup`; the coverage script already merges php-fpm runs.

## Done: batches 0 and 1 (2026-11-18)

`tests/Concerns/UsesCup.php`, `tests/Cup/CupTestCase.php`, `tests/Cup/CupRoutesTest.php`, `TestCase::allRoutes()` (the route table
collected into an application of its own, because `public/index.php` is the only place that registers routes).

Every `cup:*` route opens as the administrator (the smoke test calls 58 of the 91 with real records in place of the placeholders),
sends a stranger to `/cup/login?redirect=...` and a user without a group to `/cup/forbidden`, and none of those calls changes a record.
A route that is neither opened by the smoke test nor listed in `NOT_BY_SMOKE` fails `testEveryRouteIsAccountedFor`.

Found on the way, to be tested in their batches:
- `cup:user:subscriber:add` catches only two exceptions: an empty address is a 500, and the error added by `addError()` is lost
  by the redirect that follows (batch 4).
- The menu (`navigation.twig`) only checks `cup:main`; every link is shown to everybody who may open the dashboard. Pinned (batch 1).
- `cup:catalog:order:export` without `from` and `to` answers a 301 to nowhere in particular (batch 5).
- `cup:reference:*` builds the template name from the address (`cup/reference/{entity}/index.twig`); an unknown kind is a 400 with the
  template search path in the page when `DEBUG=1` (batch 3).

## Done: batches 2 and 3 (2026-11-18)

`tests/Cup/CupLoginTest.php` (16), `tests/Cup/CupInstallerTest.php` (11; moves `var/.lock` aside and puts it back, heals a killed run),
`tests/Cup/CupContentTest.php` (104: seven entities through one table of questions, plus API keys, groups, forms, guest book, references, files).

Found and fixed on the way:
- a failed login printed the submitted password back into the page (`value="..."`); `cup/form.twig` now writes password inputs by hand
- the editing forms did not check the title (page, publication, category, form, group, reference could be saved without one) - `update()` now does what `create()` does
- a publication or a product could be created with a category that does not exist (SQLite does not enforce the key)
- taking the last file off a page, publication or review was ignored (`processEntityFiles` only saved a non-empty list)
- the status of an API key was taken from the request as is; an unknown value emptied it
- a guest book entry could be saved without name, e-mail or message, and a bad address was dropped silently: the service validates on update, the action reports it
- `Meta`, `Sort`, `Json`, `Dimension`, `Company`, `Legal`, `Messenger` casts and the form `origin`/`mailto` accessors failed on null, so a record saved from a partial form could not be opened again (500)
- reference delete redirected to the `Referer` as is, and was a 500 without one
- publication, category, guest book and user actions answered a form post with a permanent 301

Still open from the list of decisions: CSRF, `TaskRunAction`, Referer redirects of import/export, form data preview.
