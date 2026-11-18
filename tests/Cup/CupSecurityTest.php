<?php declare(strict_types=1);

namespace tests\Cup;

use App\Domain\Models\FormData;
use App\Domain\Models\Page;
use App\Domain\Models\Task;
use App\Domain\Service\Form\DataService as FormDataService;
use App\Domain\Service\Form\FormService;

/**
 * What stops a page of somebody else's from using the panel through an administrator's browser:
 * the security token, deletes that are not links, redirects that stay on the site, tasks that are tasks
 *
 * @internal
 *
 * #[CoversNothing]
 */
class CupSecurityTest extends CupTestCase
{
    // ---------------------------------------------------------------- the token

    public function testEveryPageOfThePanelCarriesTheToken(): void
    {
        $this->fixtures();

        foreach (['/cup', '/cup/page', '/cup/user', '/cup/catalog/product', '/cup/parameters'] as $path) {
            $body = (string) $this->cup('GET', $path)->getBody();

            $this->assertMatchesRegularExpression('/<meta name="csrf-token" content="[0-9a-f]{64}"/', $body, $path);
            $this->assertStringContainsString('/assets/js/cup/csrf.js', $body, $path);
        }
    }

    public function testThePublicPagesAndTheLoginPageCarryNone(): void
    {
        $this->assertDoesNotMatchRegularExpression('/content="[0-9a-f]{64}"/', (string) $this->http('GET', '/cup/login')->getBody());
        $this->assertStringNotContainsString('csrf-token" content="' . $this->cupToken(), (string) $this->http('GET', '/cup/login')->getBody());
    }

    public function testTheTokenIsTheSameOnEveryPageOfOneAccountAndDiffersBetweenAccounts(): void
    {
        $first = $this->cupToken();
        preg_match('/csrf-token" content="([0-9a-f]{64})"/', (string) $this->cup('GET', '/cup/page')->getBody(), $again);
        $this->assertSame($first, $again[1]);

        $other = $this->makeUser(['username' => 'second', 'email' => 'second@example.com']);
        $group = $this->getService(\App\Domain\Service\User\GroupService::class)->create(['title' => 'Second', 'access' => $this->cupRouteNames()]);
        $this->getService(\App\Domain\Service\User\UserService::class)->update($other, ['group_uuid' => $group->uuid]);
        $cookie = $this->cookie($this->loginAs($other)['access_token']);
        preg_match('/csrf-token" content="([0-9a-f]{64})"/', (string) $this->http('GET', '/cup', ['headers' => $cookie])->getBody(), $theirs);

        $this->assertNotSame($first, $theirs[1]);
    }

    public function testAChangeWithoutTheTokenIsRefusedAndDoesNothing(): void
    {
        $f = $this->fixtures();

        $deleted = $this->cup('POST', "/cup/page/{$f['page']}/delete", ['csrf' => false]);
        $created = $this->cup('POST', '/cup/page/add', ['csrf' => false, 'form_params' => ['title' => 'Planted', 'address' => 'planted', 'content' => 'x']]);
        $edited = $this->cup('POST', "/cup/page/{$f['page']}/edit", ['csrf' => false, 'form_params' => ['title' => 'Hijacked', 'address' => 'about', 'content' => 'x']]);

        foreach ([$deleted, $created, $edited] as $response) {
            $this->assertSame(403, $response->getStatusCode(), $this->why($response));
        }
        $this->assertSame(1, Page::count());
        $this->assertSame('About', Page::find($f['page'])->title);
    }

    public function testAWrongTokenIsRefused(): void
    {
        $f = $this->fixtures();

        foreach (['', 'abc', str_repeat('0', 64), strtoupper($this->cupToken()), $this->cupToken() . 'x'] as $token) {
            $response = $this->cup('POST', "/cup/page/{$f['page']}/delete", ['csrf' => false, 'headers' => ['X-CSRF-Token' => $token]]);

            $this->assertSame(403, $response->getStatusCode(), $token);
        }

        $this->assertNotNull(Page::find($f['page']));
    }

    public function testTheTokenOfAnotherAccountIsRefused(): void
    {
        $f = $this->fixtures();
        $other = $this->makeUser(['username' => 'second', 'email' => 'second@example.com']);
        $group = $this->getService(\App\Domain\Service\User\GroupService::class)->create(['title' => 'Second', 'access' => $this->cupRouteNames()]);
        $this->getService(\App\Domain\Service\User\UserService::class)->update($other, ['group_uuid' => $group->uuid]);
        $cookie = $this->cookie($this->loginAs($other)['access_token']);
        preg_match('/csrf-token" content="([0-9a-f]{64})"/', (string) $this->http('GET', '/cup', ['headers' => $cookie])->getBody(), $theirs);

        // the token of the second administrator, the cookie of the first
        $response = $this->cup('POST', "/cup/page/{$f['page']}/delete", ['csrf' => false, 'headers' => ['X-CSRF-Token' => $theirs[1]]]);

        $this->assertSame(403, $response->getStatusCode());
        $this->assertNotNull(Page::find($f['page']));
    }

    public function testTheTokenInTheFormFieldIsEnough(): void
    {
        $f = $this->fixtures();

        $response = $this->cup('POST', "/cup/page/{$f['page']}/delete", ['csrf' => false, 'form_params' => ['_csrf' => $this->cupToken()]]);

        $this->assertSame(302, $response->getStatusCode(), $this->why($response));
        $this->assertNull(Page::find($f['page']));
    }

    public function testTheTokenInTheHeaderIsEnough(): void
    {
        $f = $this->fixtures();

        $response = $this->cup('POST', "/cup/page/{$f['page']}/delete", ['csrf' => false, 'headers' => ['X-CSRF-Token' => $this->cupToken()]]);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertNull(Page::find($f['page']));
    }

    public function testTheApiOfThePanelNeedsTheTokenToWrite(): void
    {
        $this->fixtures();
        $json = ['title' => 'Over the api', 'address' => 'over-the-api', 'content' => 'x'];

        $refused = $this->cup('POST', '/cup/api/v1/page', ['csrf' => false, 'json' => $json]);
        $this->assertSame(403, $refused->getStatusCode());

        $allowed = $this->cup('POST', '/cup/api/v1/page', ['json' => $json]);
        $this->assertSame(201, $allowed->getStatusCode(), $this->why($allowed));

        // reading needs nothing
        $this->assertSame(200, $this->cup('GET', '/cup/api/v1/page')->getStatusCode());
    }

    public function testTheTokenStopsWorkingWhenThePasswordChanges(): void
    {
        $f = $this->fixtures();
        $token = $this->cupToken();
        $admin = \App\Domain\Models\User::where('username', 'admin')->firstOrFail();
        $this->getService(\App\Domain\Service\User\UserService::class)->update($admin, ['password' => 'a different password']);

        $response = $this->cup('POST', "/cup/page/{$f['page']}/delete", ['csrf' => false, 'headers' => ['X-CSRF-Token' => $token]]);

        $this->assertContains($response->getStatusCode(), [403, 307]);
        $this->assertNotNull(Page::find($f['page']));
    }

    public function testTheLoginPageNeedsNoToken(): void
    {
        $user = $this->makeUser();

        $response = $this->http('POST', '/cup/login', ['form_params' => ['identifier' => $user->username, 'password' => self::PASSWORD]]);

        $this->assertNotSame(403, $response->getStatusCode());
    }

    public function testAStrangerGetsTheLoginPageNotARefusalOfTheToken(): void
    {
        $f = $this->fixtures();

        $response = $this->http('POST', "/cup/page/{$f['page']}/delete");

        $this->assertSame(307, $response->getStatusCode());
        $this->assertStringStartsWith('/cup/login', $response->getHeaderLine('Location'));
    }

    // ------------------------------------------------------- the script of the page

    public function testTheScriptSignsFormsAndRequests(): void
    {
        $script = (string) file_get_contents(PUBLIC_DIR . '/assets/js/cup/csrf.js');

        foreach (['meta[name="csrf-token"]', 'X-CSRF-Token', '_csrf', 'window.fetch', 'ajaxPrefilter', "addEventListener('submit'"] as $part) {
            $this->assertStringContainsString($part, $script, $part);
        }
        $this->assertSame(200, $this->http('GET', '/assets/js/cup/csrf.js')->getStatusCode());
    }

    // ------------------------------------------------- redirects stay on the site

    public function testTheTaskRunnerOnlyRunsTasks(): void
    {
        $this->admin();

        foreach (['ArrayObject', \App\Domain\Models\Task::class, \stdClass::class, 'No\\Such\\Class', \App\Domain\AbstractTask::class, ''] as $name) {
            $this->cup('POST', '/cup/task/run', ['form_params' => ['task' => $name]]);
        }

        $this->assertSame(0, Task::count(), 'nothing that is not a task was built');
    }

    public function testATaskIsQueuedAndTheAdministratorIsSentBackToThePageHeCameFrom(): void
    {
        $this->admin();

        $response = $this->cup('POST', '/cup/task/run', [
            'headers' => ['Referer' => 'http://127.0.0.1/cup/logs?x=1'],
            'form_params' => ['task' => \App\Domain\Tasks\SearchIndexTask::class],
        ]);

        $this->assertSame(1, Task::where('action', \App\Domain\Tasks\SearchIndexTask::class)->count());
        $this->assertRedirectsTo($response, '/cup/logs?x=1');
    }

    public function testTheRedirectsOfTheToolsNeverLeaveTheSite(): void
    {
        $this->admin();
        $foreign = ['https://evil.example/cup', 'http://127.0.0.1.evil.example/cup', '//evil.example', 'javascript:alert(1)'];

        foreach ($foreign as $referer) {
            $run = $this->cup('POST', '/cup/task/run', ['headers' => ['Referer' => $referer], 'form_params' => ['task' => \App\Domain\Tasks\SearchIndexTask::class]]);
            $this->assertRedirectsTo($run, '/cup', 'task: ' . $referer);

            $import = $this->cup('POST', '/cup/catalog/product/import', ['headers' => ['Referer' => $referer]]);
            $this->assertRedirectsTo($import, '/cup/catalog/product', 'import: ' . $referer);

            $export = $this->cup('GET', '/cup/catalog/order/export', ['headers' => ['Referer' => $referer]]);
            $this->assertRedirectsTo($export, '/cup/catalog/order', 'export: ' . $referer);
        }
    }

    public function testAReferenceOfTheSiteIsFollowedWithItsQuery(): void
    {
        $this->admin();

        $response = $this->cup('GET', '/cup/catalog/order/export', ['headers' => ['Referer' => 'http://127.0.0.1/cup/catalog/order?state=new']]);

        $this->assertRedirectsTo($response, '/cup/catalog/order?state=new');
    }

    // --------------------------------------------------- what a visitor wrote

    public function testTheMessageOfAFormIsShownAsTextInThePreview(): void
    {
        $this->admin();
        $form = $this->getService(FormService::class)->create(['title' => 'Contact', 'address' => 'contact', 'mailto' => 'boss@example.com']);
        $data = $this->getService(FormDataService::class)->create([
            'form_uuid' => $form->uuid, 'data' => ['email' => 'v@example.com'], 'message' => "<script>alert('x')</script>\nsecond line",
        ]);

        $response = $this->cup('GET', "/cup/form/{$form->uuid}/view/{$data->uuid}/preview");

        $body = (string) $response->getBody();
        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('text/html', $response->getHeaderLine('Content-Type'));
        $this->assertStringNotContainsString('<script>', $body);
        $this->assertStringContainsString('&lt;script&gt;', $body);
        $this->assertStringContainsString('<br', $body, 'line breaks are kept');
    }

    public function testTheFieldsOfAFormAreShownAsTextInThePreview(): void
    {
        $this->admin();
        $form = $this->getService(FormService::class)->create(['title' => 'Contact', 'address' => 'contact', 'mailto' => 'boss@example.com']);
        $data = $this->getService(FormDataService::class)->create(['form_uuid' => $form->uuid, 'data' => ['name' => '<img src=x onerror=alert(1)>'], 'message' => '']);

        $body = (string) $this->cup('GET', "/cup/form/{$form->uuid}/view/{$data->uuid}/preview")->getBody();

        $this->assertStringNotContainsString('<img', $body);
        $this->assertStringContainsString('&lt;img', $body);
    }

    public function testTheTemplateOfAFormDoesNotLetTheValuesThroughAsMarkup(): void
    {
        $this->admin();
        $form = $this->getService(FormService::class)->create([
            'title' => 'Contact', 'address' => 'contact', 'mailto' => 'boss@example.com', 'template' => '<p>From {{ name }}</p>',
        ]);
        $data = $this->getService(FormDataService::class)->create(['form_uuid' => $form->uuid, 'data' => ['name' => '<b onmouseover=alert(1)>x</b>'], 'message' => '']);

        $body = (string) $this->cup('GET', "/cup/form/{$form->uuid}/view/{$data->uuid}/preview")->getBody();

        $this->assertStringContainsString('<p>From', $body, 'the template of the administrator is his own markup');
        $this->assertStringNotContainsString('<b onmouseover', $body);
    }

    public function testAPreviewOfSomethingThatIsNotThereIs404(): void
    {
        $f = $this->fixtures();

        foreach ([\Ramsey\Uuid\Uuid::uuid4()->toString(), 'not-a-uuid'] as $uuid) {
            $this->assertSame(404, $this->cup('GET', "/cup/form/{$f['form']}/view/{$uuid}/preview")->getStatusCode(), $uuid);
        }
        $this->assertSame(404, $this->cup('GET', '/cup/form/' . \Ramsey\Uuid\Uuid::uuid4() . "/view/{$f['form_data']}/preview")->getStatusCode());
        $this->assertSame(1, FormData::count());
    }
}
