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

## Done: batch 4 (2026-11-18)

`tests/Cup/CupUsersTest.php` (34): list states, search (non-Latin, phone, case), group filter; add, edit, view, delete of accounts; subscribers; newsletter; group deletion.

Found and fixed: the view page of an unknown account was a 500; an account could be edited into one with no username, e-mail or phone
(nobody can sign in to it); an empty or malformed subscriber address was a 500 or an error lost by the redirect; a newsletter without a subject, a text
or a known audience was queued anyway.

Pinned, to be decided (nothing stops them today): an administrator can delete or block himself and the panel closes at the next request;
deleting a group leaves its members in a group that does not exist (no access at all).

## Decisions taken (2026-11-18) and done

1. **Deletes are POST-only and every change in the panel needs a security token.** The UI already deleted through `method="post"` forms, only the routes
   also allowed GET. Now: `cup:*:delete` routes are `post`, `App\Application\Csrf` derives the token from the account and the key of the instance
   (no storage; stops working with a password change), `CsrfMiddleware` checks `_csrf` or `X-CSRF-Token` on every non-read request of the protected
   `/cup` group, `cup/layout.twig` puts it in `<meta name="csrf-token">`, `public/assets/js/cup/csrf.js` adds it to forms, `fetch` and jQuery ajax.
   The login page and the installer are outside the group and need none.
2. **Referer redirects** of the import, exports, task runner and reference delete go through `AbstractAction::backTo()`: same host, a local path, otherwise a fixed page.
3. **`TaskRunAction`** builds only subclasses of `AbstractTask`.
4. **Form data preview** escapes the visitor's message and fields (Twig does it for the form's own template, the JSON fallback is escaped by hand); an unknown record is a 404, not a 500.
5. **An administrator cannot delete the account he is signed in with**, and **a group with members (working, blocked or waiting) cannot be deleted**; the group list counts the same members.

Tests: `tests/Cup/CupSecurityTest.php` (24). Other Cup tests send the token like the page's script does (`UsesCup::cup()`), `testNothingIsDeletedByAGetRequest` covers 19 delete routes.

## Done: batch 5 (2026-11-18)

`tests/Cup/CupCatalogTest.php` (27: categories, attributes, products), `tests/Cup/CupOrdersTest.php` (29: list, form, documents, dispatch, statistics, Excel in and out),
`tests/Cup/CupReviewsTest.php` (30: the three kinds through one set of questions), three more in `ServiceEdgeCasesTest`.

Found and fixed:
- an order serial was "orders today + 1": after one of the day's orders was deleted the next order got a taken serial and failed with a unique-key error (500); it is now the last serial of the day + 1
- the order export filtered by a column that does not exist (`status`, it is `status_uuid`: a 500 with any status), and dropped the orders of the last day of the period when only a date was given
- the product export of a category that is not there was a fatal error; the list of products of an unknown category was a 500
- a category could be put under itself or under its own descendant: the tree became a loop (`nested()` never ended)
- category and product `update()` accepted an empty title; a product could be moved to a category that does not exist
- nulls from a form replaced the defaults of a new category or product (`external_id` NULL instead of ''), so the "address is taken" check never matched: two categories with the same address
- select lists wrote their labels as they are (a category title with markup ran in the product form): `TwigExtension::form()` escapes the options
- a document or the dispatch page of an unknown order was a 500; a custom period of the order list with a bad date was a 500
- reviews: an edit could empty the text, set a rating of 99 or -3, or an unknown status (now: text required, rating 0..5, status kept)

Pinned as it is: deleting a product category is a soft delete of its whole branch and products; an attribute delete also detaches it from products.

## Done: batch 6 (2026-11-18)

`tests/Cup/CupEditorTest.php` (27), `tests/Cup/CupToolsTest.php` (29: settings, task list and runner, log viewer, dashboard numbers computed by `App\Application\Dashboard` and checked exactly).

Found and fixed:
- the file editor could make and change any kind of file in the theme and in `public/resource` (served by the web server); it now writes only the kinds a theme has (`ThemeFiles::EDITABLE`, the same list as the MCP tools) and refuses names with hidden parts, odd characters or more than 120 letters. A ".php" address is answered by php-fpm itself and never reaches the editor, so this is defence in depth
- the settings page followed `?return=` to any address, and choked on the security token field (a string where a group of settings was expected)
- the task runner failed on a `params` that was not a list

Observed and fixed later: `ThemeFiles::clearTemplateCache()` removed every two-hex-digit folder of `var/cache`, which is also where the file cache keeps its entries (login throttle counters, form guard counters), so saving a template reset them. It removes only the compiled `.php` files now.

## Done: batch 7 (2026-11-18) - the plan of the panel is finished

`tests/Cup/CupFilesTest.php` (19: the library, the picture manager, rows that are not real files), `tests/Cup/CupFormDataTest.php` (15: lists, spam, view, delete, restore, from the site to the panel).

Found and fixed:
- **deleting a file ran `rm -rf <upload folder>/<salt>` through the shell.** The salt is whatever the row says: the API takes any salt on create, so a key with write access to `file` could make a delete run a command (`x; touch ...`), and a row with an empty salt (the default) made the delete remove the folder of every upload. The delete now removes the folder in PHP, only for a plain token salt, never following links; `File::info()` quotes the path with `escapeshellarg`
- the picture manager of the text editor found the file by name and extension alone: two pictures with the same name got mixed up, and any document could be deleted through it. It now reads the folder and the name from the address and deletes pictures only
- form submissions were read by their own id under any form's address (a submission shown, deleted or restored through the wrong form); an unknown form or submission was a 500

The tests that cover the first two were run without the fixes to make sure they fail: they do, and the run removed the upload folder of the test instance - that is the bug.

## Done: McpPrompts and OAuth (2026-11-18)

`tests/Domain/McpPromptsTest.php` (one set of questions for each of the ten scenarios plus the periods, the price parser and the options), `tests/API/Mcp/McpPromptsTest.php` (through the endpoint, for a key), `tests/Application/OpenAuthProviderTest.php` (a fake plugin drives the provider).

Found and fixed:
- an account that was blocked, removed or waiting for confirmation could sign in through an OAuth provider (the lookup by provider and identity does not look at the status); it is refused now, and no second account is made for it
- a provider that answered without an identity made the lookup list every user and return a collection instead of a user; refused now
- a prompt argument sent as a list or an object turned into "Array" in the text and a PHP warning (`AbstractMcpPrompt::text()`)
- an unknown `provider` in the request ended in "Invalid HTTP status code" (a 500): `WrongAuthProviderException` has the code 400, and any exception without a status of its own is answered with a 500 instead of an invalid response

Pinned as it is: an e-mail that belongs to an existing account is not linked to the provider's identity (the sign in fails, nobody is taken over).

## Done: models and traits (2026-11-18)

`tests/Domain/ModelsTest.php` (the arithmetic of prices, taxes, discounts and order lines, typed attribute values, category trees, names and avatars of users, files of a record), `tests/Domain/TraitsTest.php` (parameters, storage, JWT, confirmation links, throttle, MCP registration, the renderer and the form guard).

Found and fixed:
- an attribute value of "0" was dropped (`CatalogAttribute::value()`)
- the lowest price of the last days subtracted the negatively stored discount (the price went up) and ignored the tax (`CatalogProduct::price_lowest()`)
- `HasFiles::documents()` returned the text files of every record (a bare `orWhere`)
- `HasStorage::hasStorage()` searched the values, not the keys
- `HasParameters::parameter_set()` worked on a stale cache: a second call for a new name failed
- a throttle limit of 0 (meant to switch it off) blocked everybody (`UseThrottle::isThrottled()`)

## Done: MCP tools, API search and telemetry (2026-11-18)

`tests/API/Mcp/McpToolsSweepTest.php` (every tool: its schema, a call with nothing, wrong types; batches, answers of the client, invalid messages), `tests/API/ApiSearchTelemetryTest.php`.

Found and fixed:
- a list where a tool's schema asks for one value (`search`, `uuid`, `status`) turned into "Array" and a PHP warning written into the JSON-RPC answer; `McpAction` refuses it now and the tools read text through `AbstractMcpTool::textArgument()`

Observed and fixed later: `/api/v1/telemetry` needed no key (PHP version, OS, commit, counts, plugin names); it needs an API key now (`AuthorizationAPIMiddleware`) and can still be switched off with `telemetry_is_enabled=no`.

The coverage report (`tests/Coverage/report.php`) counts a multi-line array literal and a multi-line call chain as run when pcov saw one of their lines run: pcov reports them as one operation, PHPUnit counts every line. Before that the schemas of the tools and the chains of the exports were listed as uncovered (about 800 lines). `--list=<regex>` prints the uncovered lines of the matching files.

## Done: the rest of the panel and of the public site (2026-11-18)

`tests/Cup/CupRemainsTest.php`, `tests/API/CommonRemainsTest.php`, two general tests in `CupContentTest` ("save and continue" and a taken value on the edit form for every entity).

Found and fixed:
- `FormService::update()` looked the address up by the title: a taken address was a 500 (unique constraint)
- the guest book and the cart answered a post with 301, which a browser remembers; 302 now
- `ProductService` took values of attributes that do not exist (the actions waited for `AttributeNotFoundException`, nothing threw it); it throws now
- an order could be made or saved for a customer that does not exist (`OrderService::assertUserExists()`, the actions already reported `UserNotFoundException` on the field)
- a dead `catch (WrongPasswordException)` of the user form was removed, nothing throws it

The repository theme `theme/default` has no pages of the visitor's account (they live in the separate default theme project), so `CommonRemainsTest` makes a small theme of its own for the tests of form errors and removes it after.

Left alone: the payment through a plugin in the cart (the plugin's business), and the "cannot write" branches of the file editor (the web server of the dev container is root, so a folder cannot be made unwritable for it).

## Done: base classes of Domain and the small services (2026-11-18)

`tests/Domain/AbstractClassesTest.php` (exceptions, extension, middleware, cron matching, a plugin over a container of its own, the events of `PubSub` and the letters they queue), `tests/Domain/AbstractActionTest.php` (the request, the answer by format, errors of an action as answers, the files of a record), `tests/Application/SmallPiecesTest.php` (Csrf, FormGuard tokens, Auth with an unknown provider, the dashboard), more in `TwigExtensionTest`.

Found and fixed:
- `PubSub` called `$order->getEmail()`, which the model does not have: with the letter to the customer switched on every new order ended in an error
- `plugin/TinkoffPayment` was written for the old entities (`getSerial()`, `getTotalPrice()`, `getProducts()`, classes of `App\Domain\Entities` that are gone); it uses the model now (the plugin is outside this repository)
- `current_path()` of Twig returned the whole address with the host, not the path its description promises

Left alone: the branches that need a worker started from the shell (`AbstractTask::worker` without `TEST`), the non-sqlite branches of `AbstractService::lowerFunction`, the OAuth plugin registration in `Auth` (it would put a plugin into the container the tests share), and the "cannot write" branches of `ThemeFiles` and `EntityFiles` (the dev container runs as root).
