<?php declare(strict_types=1);

namespace tests\API;

use App\Domain\Models\File;
use App\Domain\Models\FormData;
use App\Domain\Models\Task;
use App\Domain\Service\File\FileService;
use App\Domain\Service\Form\FormService;
use App\Domain\Service\Page\PageService;
use App\Domain\Service\Parameter\ParameterService;
use App\Domain\Service\Publication\CategoryService;
use App\Domain\Service\Publication\PublicationService;
use tests\Concerns\MakesAuthRequests;
use tests\TestCase;

/**
 * Pages, publications, files, XML and forms of the public site
 *
 * @internal
 *
 * #[CoversNothing]
 */
class SitePagesTest extends TestCase
{
    use MakesAuthRequests;

    private const AJAX = ['X-Requested-With' => 'XMLHttpRequest'];

    /** @var File[] */
    private array $files = [];

    /** @var string[] */
    private array $xml = [];

    public function tearDown(): void
    {
        foreach ($this->files as $file) {
            try {
                $this->getService(FileService::class)->delete($file);
            } catch (\Throwable) {
                // gone already
            }
        }
        foreach ($this->xml as $path) {
            @unlink($path);
        }
        $this->files = $this->xml = [];

        parent::tearDown();
    }

    private function setParameter(string $name, string $value): void
    {
        $this->getService(ParameterService::class)->create(['name' => $name, 'value' => $value]);
    }

    // --------------------------------------------------------------------- pages

    public function testAPageIsServedByItsAddress(): void
    {
        $this->getService(PageService::class)->create(['title' => 'About us', 'address' => 'about', 'content' => '<p>Hello</p>']);
        $this->getService(PageService::class)->create(['title' => 'Nested', 'address' => 'company/team', 'content' => 'x']);

        $about = $this->http('GET', '/about?format=json');
        $this->assertEquals(200, $about->getStatusCode());
        $this->assertSame('About us', $this->json($about)['data']['page']['title']);

        $nested = $this->http('GET', '/company/team?format=json');
        $this->assertSame('Nested', $this->json($nested)['data']['page']['title']);
    }

    public function testAnUnknownPageIsNotFoundAndNotCached(): void
    {
        $response = $this->http('GET', '/no/such/page?format=json');

        $this->assertEquals(404, $response->getStatusCode());
        $this->assertStringNotContainsString('public', $response->getHeaderLine('Cache-Control'));
    }

    public function testThePageAddressCannotEscapeTheSite(): void
    {
        foreach (['/..%2f..%2fetc%2fpasswd', '/%2e%2e/%2e%2e/etc/passwd', '/about%00'] as $path) {
            $response = $this->http('GET', $path . '?format=json');

            $this->assertContains($response->getStatusCode(), [400, 404], $path);
            $this->assertStringNotContainsString('root:', (string) $response->getBody(), $path);
        }
    }

    public function testTheFrontPage(): void
    {
        $response = $this->http('GET', '/?format=json');

        $this->assertEquals(200, $response->getStatusCode());
    }

    // ------------------------------------------------------------------ xml files

    public function testAnXmlFileIsServedAsXml(): void
    {
        $this->xml[] = $path = VAR_DIR . '/xml/test-sitemap.xml';
        file_put_contents($path, '<?xml version="1.0"?><urlset></urlset>');

        $response = $this->http('GET', '/xml/test-sitemap');

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringStartsWith('text/xml', $response->getHeaderLine('Content-Type'));
        $this->assertSame('<?xml version="1.0"?><urlset></urlset>', (string) $response->getBody());
    }

    public function testAnUnknownXmlFileIsNotFound(): void
    {
        $this->assertEquals(404, $this->http('GET', '/xml/nothing-here')->getStatusCode());
    }

    // -------------------------------------------------------------- publications

    private function blog(array $extra = []): \App\Domain\Models\PublicationCategory
    {
        return $this->getService(CategoryService::class)->create($extra + ['title' => 'Blog', 'address' => 'blog', 'pagination' => 2, 'sort' => ['by' => 'date', 'direction' => 'asc']]);
    }

    private function post(\App\Domain\Models\PublicationCategory $category, string $title, string $date = '2020-01-01 00:00:00'): \App\Domain\Models\Publication
    {
        return $this->getService(PublicationService::class)->create([
            'title' => $title,
            'address' => $category->address . '/' . strtolower(str_replace(' ', '-', $title)),
            'category_uuid' => $category->uuid,
            'content' => ['short' => 'Short ' . $title, 'full' => 'Full ' . $title],
            'date' => $date,
        ]);
    }

    private function titles(string $path): array
    {
        $response = $this->http('GET', $path . '?format=json');
        $this->assertEquals(200, $response->getStatusCode(), $path . ' ' . $this->why($response));

        return array_values(array_column($this->json($response)['data']['publications'] ?? [], 'title'));
    }

    public function testACategoryListsItsPublicationsOldestFirstWhenAsked(): void
    {
        $blog = $this->blog();
        $this->post($blog, 'Second', '2020-02-01 00:00:00');
        $this->post($blog, 'First', '2020-01-01 00:00:00');

        $this->assertSame(['First', 'Second'], $this->titles('/blog'));
    }

    public function testPublicationsFromTheFutureAreNotShownYet(): void
    {
        $blog = $this->blog();
        $this->post($blog, 'Published');
        $this->post($blog, 'Scheduled', date('Y-m-d H:i:s', time() + 86400));

        $this->assertSame(['Published'], $this->titles('/blog'));
    }

    public function testACategoryWithoutASortOfItsOwnStillWorks(): void
    {
        // made through the API or an import: the sort is empty
        $category = $this->getService(CategoryService::class)->create(['title' => 'News', 'address' => 'news']);
        $this->post($category, 'One');

        $this->assertSame(['One'], $this->titles('/news'));
    }

    public function testPublicationsArePagedByTheCategorySetting(): void
    {
        $blog = $this->blog(['pagination' => 2]);
        foreach (['A' => '2020-01-01', 'B' => '2020-01-02', 'C' => '2020-01-03', 'D' => '2020-01-04', 'E' => '2020-01-05'] as $title => $date) {
            $this->post($blog, $title, $date . ' 00:00:00');
        }

        $this->assertSame(['A', 'B'], $this->titles('/blog'));
        $this->assertSame(['A', 'B'], $this->titles('/blog/1'));
        $this->assertSame(['C', 'D'], $this->titles('/blog/2'));
        $this->assertSame(['E'], $this->titles('/blog/3'));
    }

    public function testThePaginationCountsEveryPublicationOfTheCategory(): void
    {
        $blog = $this->blog(['pagination' => 2]);
        foreach (['A', 'B', 'C'] as $title) {
            $this->post($blog, $title);
        }

        $pagination = $this->json($this->http('GET', '/blog?format=json'))['data']['pagination'];

        $this->assertEquals(3, $pagination['count']);
        $this->assertEquals(2, $pagination['page']);
    }

    public function testNestedCategoriesFeedTheParent(): void
    {
        $blog = $this->blog(['is_allow_nested' => true, 'pagination' => 10]);
        $sub = $this->getService(CategoryService::class)->create(['title' => 'Sub', 'address' => 'blog-sub', 'parent_uuid' => $blog->uuid]);
        $this->post($blog, 'Parent post', '2020-01-01 00:00:00');
        $this->post($sub, 'Child post', '2020-01-02 00:00:00');

        $this->assertSame(['Parent post', 'Child post'], $this->titles('/blog'));
        $this->assertSame(['Child post'], $this->titles('/blog-sub'));
    }

    public function testAPublicationIsServedByItsAddress(): void
    {
        $blog = $this->blog();
        $this->post($blog, 'Hello world');

        $response = $this->http('GET', '/blog/hello-world?format=json');

        $this->assertEquals(200, $response->getStatusCode(), $this->why($response));
        $data = $this->json($response)['data'];
        $this->assertSame('Hello world', $data['publication']['title']);
        $this->assertSame('Blog', $data['category']['title']);
    }

    public function testAnUnknownPublicationIsNotFound(): void
    {
        $this->blog();

        $this->assertEquals(404, $this->http('GET', '/blog/never-written?format=json')->getStatusCode());
    }

    public function testAPublicationFromTheFutureCannotBeOpenedByItsAddress(): void
    {
        $blog = $this->blog();
        $this->post($blog, 'Embargoed', date('Y-m-d H:i:s', time() + 86400));

        $this->assertEquals(404, $this->http('GET', '/blog/embargoed?format=json')->getStatusCode());
    }

    // --------------------------------------------------------------------- files

    private function file(string $content = 'file content', string $ext = 'txt'): File
    {
        $path = CACHE_DIR . '/' . md5(uniqid('', true)) . '.' . $ext;
        file_put_contents($path, $content . ' ' . uniqid());

        return $this->files[] = $this->getService(FileService::class)->createFromPath($path, 'report.' . $ext);
    }

    public function testAFileCanBeViewedInTheBrowser(): void
    {
        $file = $this->file('plain words');

        $response = $this->http('GET', "/file/view/{$file->salt}/{$file->hash}");

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringStartsWith('text/plain', $response->getHeaderLine('Content-Type'));
        $this->assertStringStartsWith('plain words', (string) $response->getBody());
    }

    public function testAFileCanBeDownloaded(): void
    {
        $file = $this->file('to download');

        $response = $this->http('GET', "/file/get/{$file->salt}/{$file->hash}");

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('attachment; filename="report.txt"', $response->getHeaderLine('Content-Disposition'));
        $this->assertStringStartsWith('to download', (string) $response->getBody());
    }

    public function testAFileIsFoundOnlyByTheSaltAndHashTogether(): void
    {
        $file = $this->file();
        $other = $this->file('another');

        foreach ([
            "/file/view/{$file->salt}/{$other->hash}",
            "/file/view/{$other->salt}/{$file->hash}",
            "/file/view/{$file->salt}/" . str_repeat('0', 32),
            '/file/view/nosalt/nohash',
        ] as $path) {
            $response = $this->http("GET", $path); $this->assertEquals(404, $response->getStatusCode(), $path . " " . $this->why($response));
        }
    }

    public function testAFileCannotBeFetchedWithATraversalInTheName(): void
    {
        $response = $this->http('GET', '/file/view/..%2f..%2f/..%2f..%2fetc%2fpasswd');

        $this->assertNotEquals(200, $response->getStatusCode());
        $this->assertStringNotContainsString('root:', (string) $response->getBody());
    }

    public function testFilesCanBeUploaded(): void
    {
        $response = $this->http('POST', '/file/upload', [
            'headers' => self::AJAX,
            'multipart' => [['name' => 'file', 'contents' => 'uploaded ' . uniqid(), 'filename' => 'note.txt']],
        ]);

        $this->assertEquals(200, $response->getStatusCode(), $this->why($response));
        $this->assertNotEmpty($this->json($response));
        $this->assertSame(1, File::count());

        $file = File::first();
        $this->files[] = $file;
        $this->assertSame('note', $file->name);
    }

    public function testUploadCanAnswerWithTheLinkOnly(): void
    {
        $response = $this->http('POST', '/file/upload', [
            'headers' => self::AJAX,
            'multipart' => [
                ['name' => 'path_only', 'contents' => '1'],
                ['name' => 'file', 'contents' => 'uploaded ' . uniqid(), 'filename' => 'note.txt'],
            ],
        ]);

        $this->assertEquals(200, $response->getStatusCode(), $this->why($response));
        $link = $this->json($response)['link'] ?? '';
        $this->assertStringContainsString('note', $link);
        $this->files = array_merge($this->files, File::all()->all());
    }

    public function testUploadCanBeSwitchedOff(): void
    {
        $this->setParameter('file_is_enabled', 'no');

        $response = $this->http('POST', '/file/upload', [
            'multipart' => [['name' => 'file', 'contents' => 'x', 'filename' => 'note.txt']],
        ]);

        $this->assertEquals(307, $response->getStatusCode());
        $this->assertSame(0, File::count());
    }

    // --------------------------------------------------------------------- forms

    private function form(array $extra = []): \App\Domain\Models\Form
    {
        return $this->getService(FormService::class)->create($extra + [
            'title' => 'Contact us',
            'address' => 'contact',
            'mailto' => "owner@example.com\nSales:sales@example.com",
        ]);
    }

    private function submit(array $fields, array $headers = []): \Psr\Http\Message\ResponseInterface
    {
        return $this->http('POST', '/form/contact', ['headers' => self::AJAX + $headers, 'form_params' => $fields]);
    }

    public function testASubmissionIsStoredAndMailed(): void
    {
        $this->form();

        $response = $this->submit(['email' => 'visitor@example.com', 'name' => 'Ann', 'body' => 'Please call me']);

        $this->assertEquals(200, $response->getStatusCode(), $this->why($response));
        $this->assertSame(['status' => 'ok'], $this->json($response));

        $stored = FormData::first();
        $this->assertSame('visitor@example.com', $stored->data['email']);
        $this->assertSame('Please call me', $stored->message);
        $this->assertFalse((bool) $stored->is_spam);

        $mail = Task::where('action', \App\Domain\Tasks\SendMailTask::class)->first();
        $this->assertNotNull($mail);
        $this->assertSame('Contact us', $mail->params['subject']);
        $this->assertSame('Please call me', $mail->params['body']);
        $this->assertContains('owner@example.com', $mail->params['to']);
        $this->assertSame('sales@example.com', $mail->params['to']['Sales'], 'a "Name:address" line keeps the name');
    }

    public function testWithoutABodyTheFieldsAreSentThroughTheTemplate(): void
    {
        $this->form(['template' => '<p>{{ name }} wrote</p>']);

        $this->submit(['email' => 'visitor@example.com', 'name' => 'Ann']);

        $mail = Task::where('action', \App\Domain\Tasks\SendMailTask::class)->first();
        $this->assertSame('<p>{{ name }} wrote</p>', $mail->params['template']);
        $this->assertSame('Ann', $mail->params['data']['name']);
    }

    public function testTheAuthorCanGetACopy(): void
    {
        $this->form(['authorSend' => true]);

        $this->submit(['email' => 'visitor@example.com', 'name' => 'Ann', 'body' => 'x']);

        $this->assertSame('visitor@example.com', Task::where('action', \App\Domain\Tasks\SendMailTask::class)->first()->params['cc']);
    }

    public function testASubmissionCanBeDuplicatedToAnotherAddress(): void
    {
        $this->form(['duplicate' => 'https://crm.example/hook']);

        $this->submit(['email' => 'visitor@example.com', 'body' => 'x']);

        $json = Task::where('action', \App\Domain\Tasks\SendJSONTask::class)->first();
        $this->assertNotNull($json);
        $this->assertSame('https://crm.example/hook', $json->params['url']);
        $this->assertSame('visitor@example.com', $json->params['data']['email']);
    }

    public function testSpamIsStoredButNeverMailed(): void
    {
        $this->form();

        $response = $this->submit([
            'name' => '4k USD is available in your balance. GRAB => https://www.google.com/share.google?q=C4S2dACcIVWZY4S7S',
            'phone' => '592617199607',
            'email' => '8whlgwvg8rqsx0@fldubai.com',
            'comment' => '6rgctv',
        ]);

        $this->assertSame(['status' => 'ok'], $this->json($response), 'the answer is the same as for a real one');
        $this->assertTrue((bool) FormData::first()->is_spam);
        $this->assertNotSame('', FormData::first()->spam_reason);
        $this->assertSame(0, Task::where('action', \App\Domain\Tasks\SendMailTask::class)->count());
    }

    public function testTheGuardCanBeSwitchedOff(): void
    {
        $this->setParameter('form_guard_mode', 'off');
        $this->form();

        $this->submit(['name' => 'GRAB => https://www.google.com/share.google?q=C4S2dACcIVWZY4S7S', 'comment' => 'x', 'body' => 'x']);

        $this->assertFalse((bool) FormData::first()->is_spam);
        $this->assertSame(1, Task::where('action', \App\Domain\Tasks\SendMailTask::class)->count());
    }

    public function testTooManySubmissionsFromOnePlaceAreTreatedAsSpam(): void
    {
        $this->setParameter('form_guard_limit', '2');
        $this->form();

        foreach (range(1, 4) as $i) {
            $this->submit(['email' => "visitor{$i}@example.com", 'body' => 'message ' . $i]);
        }

        $this->assertSame(4, FormData::count(), 'every one is kept');
        $this->assertSame(2, FormData::where('is_spam', true)->count());
        $this->assertSame(2, Task::where('action', \App\Domain\Tasks\SendMailTask::class)->count());
    }

    public function testAnUnknownFormIsNotFound(): void
    {
        $this->assertEquals(404, $this->http('POST', '/form/no-such-form', ['headers' => self::AJAX, 'form_params' => ['a' => 'b']])->getStatusCode());
        $this->assertSame(0, FormData::count());
    }

    public function testAFormIsSubmittedOnlyByPost(): void
    {
        $this->form();

        // there is no such page: a GET never reaches the form
        $this->assertEquals(404, $this->http('GET', '/form/contact')->getStatusCode());
        $this->assertSame(0, FormData::count());
    }

    public function testAPlainFormPostSendsTheVisitorBack(): void
    {
        $this->form();

        $response = $this->http('POST', '/form/contact', [
            'headers' => ['Referer' => 'https://shop.example/contacts'],
            'form_params' => ['email' => 'visitor@example.com', 'body' => 'hello'],
        ]);

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertSame('https://shop.example/contacts', $response->getHeaderLine('Location'));
        $this->assertSame(1, FormData::count());
    }

    public function testAFormCanBeSwitchedOff(): void
    {
        $this->form();
        $this->setParameter('form_is_enabled', 'no');

        $response = $this->submit(['email' => 'visitor@example.com']);

        $this->assertEquals(307, $response->getStatusCode());
        $this->assertSame(0, FormData::count());
    }
}
