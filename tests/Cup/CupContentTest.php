<?php declare(strict_types=1);

namespace tests\Cup;

use App\Domain\Models\ApiKey;
use App\Domain\Models\File;
use App\Domain\Models\Form;
use App\Domain\Models\FormData;
use App\Domain\Models\GuestBook;
use App\Domain\Models\Page;
use App\Domain\Models\Publication;
use App\Domain\Models\PublicationCategory;
use App\Domain\Models\Reference;
use App\Domain\Models\UserGroup;
use App\Domain\Service\GuestBook\GuestBookService;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Pages, publications and their categories, forms, references, API keys and groups: they are all
 * a list, an add form, an edit form and a delete link, and are tested by the same questions
 *
 * @internal
 *
 * #[CoversNothing]
 */
class CupContentTest extends CupTestCase
{
    public static function entities(): array
    {
        return array_map(fn (string $name): array => [$name], ['page', 'publication', 'publication-category', 'form', 'reference', 'api-key', 'group']);
    }

    /**
     * What the form of an entity looks like. Built per test, some of it needs records
     *
     * @return array{
     *     model: class-string, count: \Closure, list: string, add: string, edit: string, delete: string,
     *     valid: array, changed: array, column: string, required: string[], unique: string[], after: string, post_delete: bool
     * }
     */
    private function spec(string $name): array
    {
        // the administrator is a user and a group of their own: they exist before anything is counted
        $this->admin();

        $model = fn (string $class): \Closure => fn (): int => $class::count();

        return match ($name) {
            'page' => [
                'model' => Page::class, 'count' => $model(Page::class),
                'list' => '/cup/page', 'add' => '/cup/page/add', 'edit' => '/cup/page/%s/edit', 'delete' => '/cup/page/%s/delete',
                'valid' => ['title' => 'About us', 'address' => 'about-us', 'content' => '<p>Hello</p>'],
                'changed' => ['title' => 'About the company'], 'column' => 'title',
                'required' => ['title'], 'unique' => ['title', 'address'], 'after' => 'list', 'post_delete' => false,
            ],
            'publication' => [
                'model' => Publication::class, 'count' => $model(Publication::class),
                'list' => '/cup/publication', 'add' => '/cup/publication/add', 'edit' => '/cup/publication/%s/edit', 'delete' => '/cup/publication/%s/delete',
                'valid' => [
                    'title' => 'First post', 'address' => 'blog/first-post', 'content' => ['short' => 'short', 'full' => 'full'],
                    'category' => $this->fixtures()['publication_category'],
                ],
                'changed' => ['title' => 'Second post'], 'column' => 'title',
                'required' => ['title', 'category'], 'unique' => ['title', 'address'], 'after' => 'list', 'post_delete' => false,
            ],
            'publication-category' => [
                'model' => PublicationCategory::class, 'count' => $model(PublicationCategory::class),
                'list' => '/cup/publication/category', 'add' => '/cup/publication/category/add',
                'edit' => '/cup/publication/category/%s/edit', 'delete' => '/cup/publication/category/%s/delete',
                'valid' => ['title' => 'News', 'address' => 'news'],
                'changed' => ['title' => 'Press'], 'column' => 'title',
                'required' => ['title'], 'unique' => ['title', 'address'], 'after' => 'list', 'post_delete' => false,
            ],
            'form' => [
                'model' => Form::class, 'count' => $model(Form::class),
                'list' => '/cup/form', 'add' => '/cup/form/add', 'edit' => '/cup/form/%s/edit', 'delete' => '/cup/form/%s/delete',
                'valid' => ['title' => 'Callback', 'address' => 'callback', 'mailto' => 'boss@example.com'],
                'changed' => ['title' => 'Call me back'], 'column' => 'title',
                'required' => ['title'], 'unique' => ['title', 'address'], 'after' => 'list', 'post_delete' => false,
            ],
            'reference' => [
                'model' => Reference::class, 'count' => fn (): int => Reference::where('type', 'currency')->count(),
                'list' => '/cup/reference/currencies', 'add' => '/cup/reference/currencies/add',
                'edit' => '/cup/reference/currencies/%s/edit', 'delete' => '/cup/reference/%s/delete',
                'valid' => ['title' => 'EUR', 'value' => ['code' => 'EUR']],
                'changed' => ['title' => 'Euro'], 'column' => 'title',
                'required' => ['title'], 'unique' => [], 'after' => 'list', 'post_delete' => true,
            ],
            'api-key' => [
                'model' => ApiKey::class, 'count' => $model(ApiKey::class),
                'list' => '/cup/api-key', 'add' => '/cup/api-key/add', 'edit' => '/cup/api-key/%s/edit', 'delete' => '/cup/api-key/%s/delete',
                'valid' => ['title' => 'Partner', 'is_full_access' => '1'],
                'changed' => ['title' => 'Partner two'], 'column' => 'title',
                'required' => ['title'], 'unique' => [], 'after' => 'edit', 'post_delete' => false,
            ],
            'group' => [
                'model' => UserGroup::class, 'count' => $model(UserGroup::class),
                'list' => '/cup/user/group', 'add' => '/cup/user/group/add', 'edit' => '/cup/user/group/%s/edit', 'delete' => '/cup/user/group/%s/delete',
                'valid' => ['title' => 'Staff', 'description' => 'the staff', 'access' => ['cup:main']],
                'changed' => ['title' => 'Employees'], 'column' => 'title',
                'required' => ['title'], 'unique' => ['title'], 'after' => 'list', 'post_delete' => false,
            ],
        };
    }

    /**
     * Adds a record through the form and returns its uuid
     */
    private function add(array $spec, array $override = []): string
    {
        $before = $spec['count']();
        $response = $this->cup('POST', $spec['add'], ['form_params' => $override + $spec['valid']]);

        $this->assertSame(302, $response->getStatusCode(), $this->why($response));
        $this->assertSame($before + 1, $spec['count']());

        return $this->uuidIn($spec, $response);
    }

    private function uuidIn(array $spec, \Psr\Http\Message\ResponseInterface $response): string
    {
        // the new record is the last one: its uuid is in the address of the edit page, or in the table
        if (preg_match('~/([0-9a-f-]{36})/edit~', $response->getHeaderLine('Location'), $m)) {
            return $m[1];
        }

        return (string) $spec['model']::orderByDesc('uuid')->value('uuid');
    }

    private function path(array $spec, string $which, string $uuid): string
    {
        return sprintf($spec[$which], $uuid);
    }

    // ----------------------------------------------------------------------- add

    #[DataProvider('entities')]
    public function testTheAddFormCreatesTheRecord(string $name): void
    {
        $spec = $this->spec($name);
        $before = $spec['count']();

        $response = $this->cup('POST', $spec['add'], ['form_params' => $spec['valid']]);

        $this->assertSame($before + 1, $spec['count']());
        if ($spec['after'] === 'list') {
            $this->assertRedirectsTo($response, $spec['list']);
        } else {
            $this->assertMatchesRegularExpression('~^' . str_replace('%s', '[0-9a-f-]{36}', $spec['edit']) . '$~', $response->getHeaderLine('Location'));
        }
        $this->assertSame($spec['valid'][$spec['column']], $spec['model']::orderByDesc('uuid')->first()->{$spec['column']});
    }

    #[DataProvider('entities')]
    public function testSaveAndContinueGoesToTheEditPageOfTheNewRecord(string $name): void
    {
        $spec = $this->spec($name);

        $response = $this->cup('POST', $spec['add'], ['form_params' => ['save' => 'continue'] + $spec['valid']]);

        $uuid = $spec['model']::orderByDesc('uuid')->value('uuid');
        $this->assertRedirectsTo($response, $this->path($spec, 'edit', (string) $uuid));
    }

    #[DataProvider('entities')]
    public function testTheAddFormWithoutARequiredValueShowsTheErrorAndSavesNothing(string $name): void
    {
        $spec = $this->spec($name);
        $before = $spec['count']();

        foreach ($spec['required'] as $field) {
            $response = $this->cup('POST', $spec['add'], ['form_params' => [$field => ''] + $spec['valid']]);

            $this->assertSame(200, $response->getStatusCode(), $field . ' ' . $this->why($response));
            $this->assertStringContainsString('has-error', (string) $response->getBody(), $field);
        }

        $this->assertSame($before, $spec['count']());
    }

    #[DataProvider('entities')]
    public function testAValueThatMustBeUniqueIsReportedWhenTaken(string $name): void
    {
        $spec = $this->spec($name);
        if ($spec['unique'] === []) {
            $this->addToAssertionCount(1);

            return;
        }
        $this->add($spec);
        $before = $spec['count']();

        foreach ($spec['unique'] as $field) {
            // the other unique value is made different, so only this one repeats
            $other = array_map(fn ($value) => is_string($value) && !in_array($value, [$spec['valid']['category'] ?? null], true) ? $value . ' ' . uniqid() : $value, $spec['valid']);
            $response = $this->cup('POST', $spec['add'], ['form_params' => [$field => $spec['valid'][$field]] + $other]);

            $this->assertSame(200, $response->getStatusCode(), $field . ' ' . $this->why($response));
            $this->assertStringContainsString('has-error', (string) $response->getBody(), $field);
        }

        $this->assertSame($before, $spec['count']());
    }

    // ---------------------------------------------------------------------- edit

    #[DataProvider('entities')]
    public function testTheEditFormShowsTheRecord(string $name): void
    {
        $spec = $this->spec($name);
        $uuid = $this->add($spec);

        $response = $this->cup('GET', $this->path($spec, 'edit', $uuid));

        $this->assertRenders($response);
        $this->assertStringContainsString($spec['valid'][$spec['column']], (string) $response->getBody());
    }

    #[DataProvider('entities')]
    public function testTheEditFormChangesTheRecord(string $name): void
    {
        $spec = $this->spec($name);
        $uuid = $this->add($spec);

        $response = $this->cup('POST', $this->path($spec, 'edit', $uuid), ['form_params' => $spec['changed'] + $spec['valid']]);

        if ($spec['after'] === 'list') {
            $this->assertRedirectsTo($response, $spec['list']);
        } else {
            $this->assertRedirectsTo($response, $this->path($spec, 'edit', $uuid));
        }
        $this->assertSame($spec['changed'][$spec['column']], $spec['model']::find($uuid)->{$spec['column']});
    }

    #[DataProvider('entities')]
    public function testSaveAndContinueOnTheEditFormStaysOnTheEditPage(string $name): void
    {
        $spec = $this->spec($name);
        $uuid = $this->add($spec);

        $response = $this->cup('POST', $this->path($spec, 'edit', $uuid), ['form_params' => ['save' => 'continue'] + $spec['changed'] + $spec['valid']]);

        $this->assertRedirectsTo($response, $this->path($spec, 'edit', $uuid));
        $this->assertSame($spec['changed'][$spec['column']], $spec['model']::find($uuid)->{$spec['column']});
    }

    #[DataProvider('entities')]
    public function testAValueThatIsTakenByAnotherRecordIsRefusedOnTheEditFormToo(string $name): void
    {
        $spec = $this->spec($name);
        if ($spec['unique'] === []) {
            $this->addToAssertionCount(1);

            return;
        }
        $first = $this->add($spec);

        foreach ($spec['unique'] as $field) {
            $other = array_map(fn ($value) => is_string($value) && !in_array($value, [$spec['valid']['category'] ?? null], true) ? $value . ' ' . uniqid() : $value, $spec['valid']);
            $second = $this->add($spec, $other);

            // the second one asks for what the first one has
            $response = $this->cup('POST', $this->path($spec, 'edit', $second), ['form_params' => [$field => $spec['valid'][$field]] + $other]);

            $this->assertSame(200, $response->getStatusCode(), $field . ' ' . $this->why($response));
            $this->assertStringContainsString('has-error', (string) $response->getBody(), $field);
            $this->assertNotSame($spec['valid'][$field], $spec['model']::find($second)->{$field}, $field);
            $spec['model']::where('uuid', $second)->forceDelete();
        }

        $this->assertNotNull($spec['model']::find($first));
    }

    #[DataProvider('entities')]
    public function testTheEditFormKeepsTheRecordWhenARequiredValueIsMissing(string $name): void
    {
        $spec = $this->spec($name);
        $uuid = $this->add($spec);

        foreach ($spec['required'] as $field) {
            $response = $this->cup('POST', $this->path($spec, 'edit', $uuid), ['form_params' => [$field => ''] + $spec['valid']]);

            $this->assertSame(200, $response->getStatusCode(), $field . ' ' . $this->why($response));
            $this->assertStringContainsString('has-error', (string) $response->getBody(), $field);
        }

        $this->assertSame($spec['valid'][$spec['column']], $spec['model']::find($uuid)->{$spec['column']});
    }

    #[DataProvider('entities')]
    public function testSavingTheEditFormAgainWithoutChangesIsNotAConflict(string $name): void
    {
        $spec = $this->spec($name);
        $uuid = $this->add($spec);

        $response = $this->cup('POST', $this->path($spec, 'edit', $uuid), ['form_params' => $spec['valid']]);

        $this->assertSame(302, $response->getStatusCode(), $this->why($response));
    }

    #[DataProvider('entities')]
    public function testEditingAnUnknownRecordGoesBackToTheList(string $name): void
    {
        $spec = $this->spec($name);
        $this->add($spec);

        foreach ([\Ramsey\Uuid\Uuid::uuid4()->toString(), 'not-a-uuid'] as $uuid) {
            foreach (['GET', 'POST'] as $method) {
                $response = $this->cup($method, $this->path($spec, 'edit', $uuid), ['form_params' => $spec['changed']]);

                $this->assertRedirectsTo($response, $spec['list'], "{$method} {$uuid}");
            }
        }
    }

    // -------------------------------------------------------------------- delete

    #[DataProvider('entities')]
    public function testDeleteRemovesTheRecord(string $name): void
    {
        $spec = $this->spec($name);
        $uuid = $this->add($spec);
        $before = $spec['count']();

        $response = $this->cup('POST', $this->path($spec, 'delete', $uuid), ['headers' => ['Referer' => 'http://127.0.0.1' . $spec['list']]]);

        $this->assertSame(302, $response->getStatusCode(), $this->why($response));
        $this->assertSame($before - 1, $spec['count']());
        $this->assertNull($spec['model']::find($uuid));
    }

    #[DataProvider('entities')]
    public function testDeletingAnUnknownRecordRemovesNothing(string $name): void
    {
        $spec = $this->spec($name);
        $this->add($spec);
        $before = $spec['count']();

        foreach ([\Ramsey\Uuid\Uuid::uuid4()->toString(), 'not-a-uuid'] as $uuid) {
            $response = $this->cup('POST', $this->path($spec, 'delete', $uuid), ['headers' => ['Referer' => 'http://127.0.0.1' . $spec['list']]]);

            $this->assertSame(302, $response->getStatusCode(), $uuid . ' ' . $this->why($response));
        }

        $this->assertSame($before, $spec['count']());
    }

    // --------------------------------------------------------------------- lists

    #[DataProvider('entities')]
    public function testTheListShowsTheRecordsAndEscapesWhatWasTyped(string $name): void
    {
        $spec = $this->spec($name);
        $hostile = '<script>alert("x")</script>';
        $uuid = $this->add($spec, [$spec['column'] => $hostile]);
        $this->add($spec, [$spec['column'] => 'Second ' . uniqid(), 'address' => 'second-' . uniqid()]);

        $list = (string) $this->cup('GET', $spec['list'])->getBody();
        $edit = (string) $this->cup('GET', $this->path($spec, 'edit', $uuid))->getBody();

        $this->assertStringContainsString('Second', $list);
        foreach (['list' => $list, 'edit form' => $edit] as $where => $body) {
            $this->assertStringNotContainsString($hostile, $body, $where);
        }
    }

    // ------------------------------------------------------------- the specifics

    public function testAPublicationRemembersWhoWroteIt(): void
    {
        $spec = $this->spec('publication');

        $uuid = $this->add($spec);

        $this->assertSame(\App\Domain\Models\User::where('username', 'admin')->value('uuid'), Publication::find($uuid)->user_uuid);
    }

    public function testAPublicationNeedsAnExistingCategory(): void
    {
        $spec = $this->spec('publication');
        $before = Publication::count();

        $response = $this->cup('POST', $spec['add'], ['form_params' => ['category' => \Ramsey\Uuid\Uuid::uuid4()->toString()] + $spec['valid']]);

        $this->assertSame(200, $response->getStatusCode(), $this->why($response));
        $this->assertStringContainsString('has-error', (string) $response->getBody());
        $this->assertSame($before, Publication::count());
    }

    public function testAPageKeepsTheFilesAttachedToIt(): void
    {
        $spec = $this->spec('page');

        $response = $this->cup('POST', $spec['add'], ['multipart' => [
            ['name' => 'title', 'contents' => 'With files'],
            ['name' => 'address', 'contents' => 'with-files'],
            ['name' => 'content', 'contents' => 'x'],
            ['name' => 'files[]', 'contents' => 'attached ' . uniqid(), 'filename' => 'one.txt'],
        ]]);

        $this->assertSame(302, $response->getStatusCode(), $this->why($response));
        $page = Page::first();
        $this->assertCount(1, $page->files);
        $this->cupFiles[] = $page->files->first();

        // ticking "delete" detaches it
        $file = $page->files->first();
        $this->cup('POST', "/cup/page/{$page->uuid}/edit", ['form_params' => ['title' => 'With files', 'address' => 'with-files', 'content' => 'x', 'files' => [$file->uuid => ['delete' => '1']]]]);
        $this->assertCount(0, Page::first()->files()->get());
    }

    // ------------------------------------------------------------------ api keys

    public function testAnApiKeyOfTheFormWorksOnTheApi(): void
    {
        $this->setApiAccess('key');
        $uuid = $this->add($this->spec('api-key'));

        $page = (string) $this->cup('GET', "/cup/api-key/{$uuid}/edit")->getBody();
        $this->assertSame(1, preg_match('/eyJ[\w-]+\.[\w-]+\.[\w-]+/', $page, $token), 'the page shows the key');

        $this->assertSame(200, $this->http('GET', '/api/v1/page', ['headers' => ['key' => $token[0]]])->getStatusCode());
    }

    public function testDeletingAnApiKeyStopsItAtOnce(): void
    {
        $this->setApiAccess('key');
        $uuid = $this->add($this->spec('api-key'));
        preg_match('/eyJ[\w-]+\.[\w-]+\.[\w-]+/', (string) $this->cup('GET', "/cup/api-key/{$uuid}/edit")->getBody(), $token);

        $this->cup('POST', "/cup/api-key/{$uuid}/delete");

        $this->assertSame(401, $this->http('GET', '/api/v1/page', ['headers' => ['key' => $token[0]]])->getStatusCode());
    }

    public function testRevokingAnApiKeyInTheFormStopsItAtOnce(): void
    {
        $this->setApiAccess('key');
        $spec = $this->spec('api-key');
        $uuid = $this->add($spec);
        preg_match('/eyJ[\w-]+\.[\w-]+\.[\w-]+/', (string) $this->cup('GET', "/cup/api-key/{$uuid}/edit")->getBody(), $token);

        $this->cup('POST', "/cup/api-key/{$uuid}/edit", ['form_params' => $spec['valid'] + ['status' => 'revoke']]);

        $this->assertSame(401, $this->http('GET', '/api/v1/page', ['headers' => ['key' => $token[0]]])->getStatusCode());
        $this->assertSame('revoke', ApiKey::find($uuid)->status);
    }

    public function testAnUnknownStatusDoesNotRevokeNorActivateAKey(): void
    {
        $spec = $this->spec('api-key');
        $uuid = $this->add($spec);

        $this->cup('POST', "/cup/api-key/{$uuid}/edit", ['form_params' => $spec['valid'] + ['status' => 'whatever']]);

        $this->assertSame('work', ApiKey::find($uuid)->status, 'the current status is kept');
    }

    public function testTheScopesOfAKeyAreSavedAndEnforced(): void
    {
        $this->setApiAccess('key');
        $spec = $this->spec('api-key');
        $this->cup('POST', $spec['add'], ['form_params' => ['title' => 'Reader', 'scopes' => ['read' => ['page', 'no-such-entity']]]]);
        $key = ApiKey::first();

        $this->assertSame(['page'], $key->scopes['read'], 'an entity that does not exist is dropped');
        $this->assertSame([], $key->scopes['write']);
        $this->assertFalse((bool) $key->is_full_access);

        preg_match('/eyJ[\w-]+\.[\w-]+\.[\w-]+/', (string) $this->cup('GET', "/cup/api-key/{$key->uuid}/edit")->getBody(), $token);
        $this->assertSame(200, $this->http('GET', '/api/v1/page', ['headers' => ['key' => $token[0]]])->getStatusCode());
        $this->assertSame(403, $this->http('GET', '/api/v1/user', ['headers' => ['key' => $token[0]]])->getStatusCode());
        $this->assertSame(405, $this->http('POST', '/api/v1/page', ['headers' => ['key' => $token[0]], 'json' => ['title' => 'x']])->getStatusCode());
    }

    private function setApiAccess(string $mode): void
    {
        $this->getService(\App\Domain\Service\Parameter\ParameterService::class)->create(['name' => 'entity_access', 'value' => $mode]);
        $this->getService(\Illuminate\Cache\ArrayStore::class)->flush();
    }

    // -------------------------------------------------------------------- groups

    public function testChangingTheAccessOfAGroupTakesEffectOnTheNextRequest(): void
    {
        $this->fixtures();
        $group = $this->getService(\App\Domain\Service\User\GroupService::class)->create(['title' => 'Editors', 'access' => ['cup:main', 'cup:page:list']]);
        $user = $this->makeUser(['username' => 'editor', 'email' => 'editor@example.com', 'group_uuid' => $group->uuid]);
        $cookie = $this->cookie($this->loginAs($user)['access_token']);

        $this->assertSame(200, $this->http('GET', '/cup/page', ['headers' => $cookie])->getStatusCode());

        $this->cup('POST', "/cup/user/group/{$group->uuid}/edit", ['form_params' => ['title' => 'Editors', 'access' => ['cup:main']]]);

        $this->assertSame(['cup:main'], UserGroup::find($group->uuid)->access);
        $denied = $this->http('GET', '/cup/page', ['headers' => $cookie]);
        $this->assertSame(307, $denied->getStatusCode());
        $this->assertSame('/cup/forbidden', $denied->getHeaderLine('Location'));
    }

    public function testAGroupWithNoAccessChosenIsSavedEmpty(): void
    {
        $spec = $this->spec('group');

        $this->cup('POST', $spec['add'], ['form_params' => ['title' => 'Nobody']]);

        $this->assertSame([], UserGroup::firstWhere(['title' => 'Nobody'])->access);
    }

    // --------------------------------------------------------------------- forms

    public function testDeletingAFormDeletesItsSubmissionsSpamIncluded(): void
    {
        $uuid = $this->fixtures()['form'];
        FormData::create(['form_uuid' => $uuid, 'data' => ['a' => 'b'], 'message' => 'spam', 'is_spam' => true, 'spam_reason' => 'test']);
        $this->assertSame(2, FormData::count());

        $this->cup('POST', "/cup/form/{$uuid}/delete");

        $this->assertSame(0, FormData::count());
        $this->assertNull(Form::find($uuid));
    }

    // ----------------------------------------------------------------- guest book

    public function testAGuestBookEntryCanBeEditedAndAnsweredAndApproved(): void
    {
        $uuid = $this->fixtures()['guestbook'];

        $response = $this->cup('POST', "/cup/guestbook/{$uuid}/edit", ['form_params' => [
            'name' => 'Ann', 'email' => 'ann@example.com', 'message' => 'Hi there', 'response' => 'Thanks!', 'status' => 'work',
        ]]);

        $this->assertRedirectsTo($response, '/cup/guestbook');
        $entry = GuestBook::find($uuid);
        $this->assertSame('Hi there', $entry->message);
        $this->assertSame('Thanks!', $entry->response);
        $this->assertSame('work', $entry->status);

        // and the site shows it, the address masked
        $site = (string) $this->http('GET', '/guestbook?format=json')->getBody();
        $this->assertStringContainsString('Hi there', $site);
        $this->assertStringContainsString('Thanks!', $site);
    }

    public function testAGuestBookEntryCannotBeSavedWithoutItsParts(): void
    {
        $uuid = $this->fixtures()['guestbook'];
        $valid = ['name' => 'Ann', 'email' => 'ann@example.com', 'message' => 'Hi', 'status' => 'work'];

        foreach (['name', 'email', 'message'] as $field) {
            $response = $this->cup('POST', "/cup/guestbook/{$uuid}/edit", ['form_params' => [$field => ''] + $valid]);

            $this->assertSame(200, $response->getStatusCode(), $field . ' ' . $this->why($response));
            $this->assertStringContainsString('has-error', (string) $response->getBody(), $field);
        }

        $bad = $this->cup('POST', "/cup/guestbook/{$uuid}/edit", ['form_params' => ['email' => 'not-an-address'] + $valid]);
        $this->assertStringContainsString('has-error', (string) $bad->getBody());

        $kept = GuestBook::find($uuid);
        $this->assertSame('Ann', $kept->name);
        $this->assertSame('ann@example.com', $kept->email);
        $this->assertSame('Hi', $kept->message);
    }

    public function testAGuestBookEntryCanBeDeleted(): void
    {
        $uuid = $this->fixtures()['guestbook'];

        $this->assertRedirectsTo($this->cup('POST', "/cup/guestbook/{$uuid}/delete"), '/cup/guestbook');
        $this->assertNull(GuestBook::find($uuid));

        $this->assertRedirectsTo($this->cup('POST', '/cup/guestbook/not-a-uuid/delete'), '/cup/guestbook');
    }

    public function testTheGuestBookListShowsTheEntries(): void
    {
        $this->fixtures();

        $this->assertStringContainsString('Ann', (string) $this->cup('GET', '/cup/guestbook')->getBody());
    }

    // ----------------------------------------------------------------- references

    public function testAReferenceDeleteGoesBackToTheListItWasClickedFrom(): void
    {
        $uuid = $this->fixtures()['reference'];

        $response = $this->cup('POST', "/cup/reference/{$uuid}/delete", ['headers' => ['Referer' => 'http://127.0.0.1/cup/reference/currencies']]);

        $this->assertRedirectsTo($response, '/cup/reference/currencies');
    }

    public function testAReferenceDeleteNeverFollowsAForeignReferer(): void
    {
        $uuid = $this->fixtures()['reference'];

        foreach (['https://evil.example/cup/reference/x', 'https://evil.example/', '//evil.example', 'javascript:alert(1)'] as $referer) {
            $response = $this->cup('POST', "/cup/reference/{$uuid}/delete", ['headers' => ['Referer' => $referer]]);

            $this->assertStringStartsWith('/cup', $response->getHeaderLine('Location'), $referer);
        }
    }

    public function testAReferenceDeleteWithoutARefererStillWorks(): void
    {
        $uuid = $this->fixtures()['reference'];

        $response = $this->cup('POST', "/cup/reference/{$uuid}/delete");

        $this->assertSame(302, $response->getStatusCode(), $this->why($response));
        $this->assertNull(Reference::find($uuid));
    }

    public function testNothingIsDeletedByAGetRequest(): void
    {
        $this->admin();
        $f = $this->fixtures();
        $paths = [
            "/cup/reference/{$f['reference']}/delete", "/cup/page/{$f['page']}/delete", "/cup/publication/{$f['publication']}/delete",
            "/cup/publication/category/{$f['publication_category']}/delete", "/cup/form/{$f['form']}/delete", "/cup/api-key/{$f['apikey']}/delete",
            "/cup/user/group/{$f['group']}/delete", "/cup/user/{$f['user']}/delete", "/cup/user/subscriber/{$f['subscriber']}/delete",
            "/cup/guestbook/{$f['guestbook']}/delete", "/cup/file/{$f['file']}/delete", "/cup/catalog/category/{$f['catalog_category']}/delete",
            "/cup/catalog/product/{$f['product']}/delete", "/cup/catalog/attribute/{$f['attribute']}/delete", "/cup/catalog/order/{$f['order']}/delete",
            "/cup/publication/review/{$f['review_publication']}/delete", "/cup/catalog/review/{$f['review_catalog']}/delete",
            "/cup/catalog/question/{$f['question_catalog']}/delete",
            "/cup/form/{$f['form']}/view/{$f['form_data']}/delete",
        ];
        $before = [Reference::count(), Page::count(), Publication::count(), Form::count(), ApiKey::count(), UserGroup::count(), GuestBook::count(), File::count(),
            \App\Domain\Models\CatalogProduct::count(), \App\Domain\Models\CatalogOrder::count(), \App\Domain\Models\Review::count(), FormData::count(),
            \App\Domain\Models\UserSubscriber::count(), \App\Domain\Models\User::where('status', 'work')->count()];

        foreach ($paths as $path) {
            $response = $this->cup('GET', $path);

            $this->assertNotContains($response->getStatusCode(), [200, 301, 302], $path . ': a link must not change anything');
        }

        $this->assertSame($before, [Reference::count(), Page::count(), Publication::count(), Form::count(), ApiKey::count(), UserGroup::count(), GuestBook::count(), File::count(),
            \App\Domain\Models\CatalogProduct::count(), \App\Domain\Models\CatalogOrder::count(), \App\Domain\Models\Review::count(), FormData::count(),
            \App\Domain\Models\UserSubscriber::count(), \App\Domain\Models\User::where('status', 'work')->count()]);
    }

    public function testEveryFileOfTheRecordsSurvivesTheirRemoval(): void
    {
        // a file that was attached to a page is a file of the library, deleting the page does not delete it
        $file = File::first();
        $this->assertNull($file);
        $uuid = $this->fixtures()['file'];
        $pageUuid = $this->fixtures()['page'];
        Page::find($pageUuid)->files()->sync([$uuid => ['comment' => '', 'order' => 1]]);

        $this->cup('POST', "/cup/page/{$pageUuid}/delete");

        $this->assertNotNull(File::find($uuid));
    }
}
