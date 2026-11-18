<?php declare(strict_types=1);

namespace tests\API\Mcp;

use App\Domain\Casts\Review\Status as ReviewStatus;
use App\Domain\Casts\Reference\Type as ReferenceType;
use App\Domain\Service\Catalog\CategoryService as CatalogCategoryService;
use App\Domain\Service\Catalog\OrderService as CatalogOrderService;
use App\Domain\Service\Catalog\ProductService as CatalogProductService;
use App\Domain\Casts\Task\Status as TaskStatus;
use App\Domain\Service\Form\DataService as FormDataService;
use App\Domain\Service\Form\FormService;
use App\Domain\Service\GuestBook\GuestBookService;
use App\Domain\Service\Catalog\AttributeService;
use App\Domain\Service\File\FileService;
use App\Domain\Service\Page\PageService;
use App\Domain\Service\Publication\CategoryService as PublicationCategoryService;
use App\Domain\Service\Parameter\ParameterService;
use App\Domain\Service\Reference\ReferenceService;
use App\Domain\Service\Review\ReviewService;
use App\Domain\Service\Task\TaskService;
use App\Domain\Service\User\GroupService as UserGroupService;
use App\Domain\Service\User\SubscriberService;
use App\Domain\Service\User\UserService;
use tests\TestCase;

/**
 * Reading and changing the theme files
 *
 * @internal
 *
 * #[CoversNothing]
 */
class McpThemeTest extends McpTestCase
{
    public function testThemeToolsAreForFullAccessKeysOnly(): void
    {
        $names = ['theme_file_list', 'theme_file_read', 'theme_file_write', 'theme_file_history', 'theme_file_restore'];

        $limited = $this->tools($this->createApiKeyToken(['read' => ['page', 'publication', 'catalog/product'], 'write' => ['page', 'catalog/product']], false));
        foreach ($names as $name) {
            $this->assertNotContains($name, $limited, $name);
        }
        $this->assertArrayNotHasKey('edit_template', $this->prompts($this->createApiKeyToken(['read' => ['page']], false)));

        $full = $this->tools($this->createApiKeyToken());
        foreach ($names as $name) {
            $this->assertContains($name, $full, $name);
        }
        $this->assertArrayHasKey('edit_template', $this->prompts($this->createApiKeyToken()));

        $definitions = array_column($this->call($this->createApiKeyToken(), 'tools/list')['result']['tools'], null, 'name');
        $this->assertArrayHasKey('dry_run', $definitions['theme_file_write']['inputSchema']['properties'], 'the file tools do the dry run themselves');
        $this->assertArrayNotHasKey('dry_run', $definitions['theme_file_read']['inputSchema']['properties']);
    }

    public function testThemeFilesAreListedAndRead(): void
    {
        $name = 'zz-mcp-' . uniqid();
        $path = $this->themeFile("{$name}.twig", "{% extends 'page.twig' %}\nпривет");
        $this->themeFile("{$name}.bin", "\0\1\2");
        $token = $this->createApiKeyToken();

        [, $result] = $this->tool($token, 'theme_file_list', ['search' => $name]);
        $this->assertEquals([$path], array_column($result['items'], 'path'), 'only what can be edited by default');
        $this->assertEquals(1, $result['total']);
        $this->assertTrue($result['items'][0]['editable']);

        [, $result] = $this->tool($token, 'theme_file_list', ['search' => $name, 'editable_only' => false]);
        $this->assertCount(2, $result['items']);
        $this->assertEquals([false], array_values(array_unique(array_column(array_filter($result['items'], fn ($item) => str_ends_with($item['path'], '.bin')), 'editable'))));

        [, $result] = $this->tool($token, 'theme_file_list', ['section' => 'resource', 'search' => $name]);
        $this->assertCount(0, $result['items']);
        [, $result] = $this->tool($token, 'theme_file_list', ['limit' => 1]);
        $this->assertCount(1, $result['items']);
        $this->assertGreaterThan(1, $result['total'], 'the templates of the theme are there');
        [$error] = $this->tool($token, 'theme_file_list', ['section' => 'src']);
        $this->assertTrue($error);

        [$error, $file] = $this->tool($token, 'theme_file_read', ['path' => $path]);
        $this->assertFalse($error, json_encode($file));
        $this->assertEquals("{% extends 'page.twig' %}\nпривет", $file['content']);
        $this->assertEquals(hash('sha256', $file['content']), $file['version']);
        $this->assertTrue($file['editable']);

        [$error] = $this->tool($token, 'theme_file_read', ['path' => "theme/{$name}-missing.twig"]);
        $this->assertTrue($error);
        [$error, $text] = $this->tool($token, 'theme_file_read', ['path' => "theme/{$name}.bin"]);
        $this->assertTrue($error);
        $this->assertStringContainsString('not a text file', $text);
    }

    public function testThemePathsCannotLeaveTheFolders(): void
    {
        $token = $this->createApiKeyToken();
        $this->allowTemplateEditing();

        // a link in the theme that leads to a file out of it
        $link = THEME_DIR . '/default/zz-mcp-link-' . uniqid() . '.twig';
        symlink('/etc/hostname', $link);
        $this->themeFixtures[] = $link;

        foreach ([
            '../etc/passwd', 'theme/../src/settings.php', 'theme/../../etc/passwd', 'theme/a/../../x.twig', '/etc/passwd', 'src/settings.php',
            'theme/.env', 'theme/.hidden/x.twig', 'theme//x.twig', 'theme/', 'theme', '', 'theme\\..\\x.twig', "theme/x.twig\0.png", 'theme/' . str_repeat('a', 121) . '.twig',
            'theme/ё.twig', 'theme/a;b.twig', 'resource/../../src/settings.php', 'theme/' . basename($link),
        ] as $path) {
            foreach ([['theme_file_read', ['path' => $path]], ['theme_file_write', ['path' => $path, 'content' => 'x']], ['theme_file_history', ['path' => $path]]] as [$tool, $args]) {
                [$error] = $this->tool($token, $tool, $args);
                $this->assertTrue($error, "{$tool}: " . json_encode($path));
            }
        }

        // a new file in a folder that does not exist is not created
        [$error, $text] = $this->tool($token, 'theme_file_write', ['path' => 'theme/zz-no-such-folder/x.twig', 'content' => 'x']);
        $this->assertTrue($error);
        $this->assertStringContainsString('does not exist', $text);
        $this->assertDirectoryDoesNotExist(THEME_DIR . '/default/zz-no-such-folder');
    }

    public function testThemeEditingIsOffUntilTheAdministratorTurnsItOn(): void
    {
        $path = $this->themeFile('zz-mcp-' . uniqid() . '.twig');
        $token = $this->createApiKeyToken();
        $version = hash('sha256', '<p>start</p>');

        foreach ([['theme_file_write', ['path' => $path, 'content' => '<p>changed</p>', 'expected_version' => $version]], ['theme_file_restore', ['path' => $path, 'version' => '20260101-000000-abcdef12']]] as [$tool, $args]) {
            [$error, $text] = $this->tool($token, $tool, $args);
            $this->assertTrue($error, $tool);
            $this->assertStringContainsString('turned off', $text);
        }
        $this->assertEquals('<p>start</p>', file_get_contents(THEME_DIR . '/default/' . basename($path)));

        // reading does not need it
        [$error] = $this->tool($token, 'theme_file_read', ['path' => $path]);
        $this->assertFalse($error);
    }

    public function testThemeFileIsChangedSafely(): void
    {
        $this->allowTemplateEditing();
        $name = 'zz-mcp-' . uniqid() . '.twig';
        $path = $this->themeFile($name, "<h1>Title</h1>\n<p>text</p>\n");
        $file = THEME_DIR . '/default/' . $name;
        $token = $this->createApiKeyToken();

        [, $read] = $this->tool($token, 'theme_file_read', ['path' => $path]);
        $new = "<h1>Title</h1>\n<p>new text</p>\n<p>one more</p>\n";

        // no version: refused
        [$error, $text] = $this->tool($token, 'theme_file_write', ['path' => $path, 'content' => $new]);
        $this->assertTrue($error);
        $this->assertStringContainsString('expected_version', $text);

        // a wrong version, as if the file was changed meanwhile
        [$error, $text] = $this->tool($token, 'theme_file_write', ['path' => $path, 'content' => $new, 'expected_version' => hash('sha256', 'something else')]);
        $this->assertTrue($error);
        $this->assertStringContainsString('changed since', $text);

        // dry run: tells how big the change is, writes nothing, keeps nothing
        [$error, $dry] = $this->tool($token, 'theme_file_write', ['path' => $path, 'content' => $new, 'expected_version' => $read['version'], 'dry_run' => true]);
        $this->assertFalse($error, json_encode($dry));
        $this->assertTrue($dry['dry_run']);
        $this->assertEquals(2, $dry['added']);
        $this->assertEquals(1, $dry['removed']);
        $this->assertEquals("<h1>Title</h1>\n<p>text</p>\n", file_get_contents($file));
        $this->assertSame([], $this->tool($token, 'theme_file_history', ['path' => $path])[1]['versions']);

        // for real
        [$error, $done] = $this->tool($token, 'theme_file_write', ['path' => $path, 'content' => $new, 'expected_version' => $read['version']]);
        $this->assertFalse($error, json_encode($done));
        $this->assertFalse($done['dry_run']);
        $this->assertTrue($done['changed']);
        $this->assertNotNull($done['backup']);
        $this->assertEquals($new, file_get_contents($file));
        $this->assertEquals(hash('sha256', $new), $done['version']);

        // the old version is kept, the same call cannot be repeated on the old version
        [, $history] = $this->tool($token, 'theme_file_history', ['path' => $path]);
        $this->assertEquals([$done['backup']], array_column($history['versions'], 'id'));
        [$error] = $this->tool($token, 'theme_file_write', ['path' => $path, 'content' => 'again', 'expected_version' => $read['version']]);
        $this->assertTrue($error);

        // the same text: nothing to do
        [, $same] = $this->tool($token, 'theme_file_write', ['path' => $path, 'content' => $new, 'expected_version' => $done['version']]);
        $this->assertFalse($same['changed']);
        $this->assertNull($same['backup']);

        // back
        [$error, $back] = $this->tool($token, 'theme_file_restore', ['path' => $path, 'version' => $done['backup'], 'dry_run' => true]);
        $this->assertFalse($error);
        $this->assertTrue($back['dry_run']);
        $this->assertEquals($new, file_get_contents($file));

        [$error, $back] = $this->tool($token, 'theme_file_restore', ['path' => $path, 'version' => $done['backup']]);
        $this->assertFalse($error, json_encode($back));
        $this->assertEquals("<h1>Title</h1>\n<p>text</p>\n", file_get_contents($file));
        $this->assertNotNull($back['backup'], 'the text that was replaced is kept too - a restore can be undone');
        [, $history] = $this->tool($token, 'theme_file_history', ['path' => $path]);
        $this->assertCount(2, $history['versions']);

        foreach ([['version' => 'nope'], ['version' => '20260101-000000-abcdef12'], ['version' => '../../x'], ['version' => '']] as $args) {
            [$error] = $this->tool($token, 'theme_file_restore', ['path' => $path] + $args);
            $this->assertTrue($error, json_encode($args));
        }

        // the file has the text of the first version now: restoring it again is refused
        $original = array_values(array_filter($history['versions'], fn (array $version) => str_ends_with($version['id'], substr(hash('sha256', "<h1>Title</h1>\n<p>text</p>\n"), 0, 8))));
        $this->assertCount(1, $original);
        [$error, $text] = $this->tool($token, 'theme_file_restore', ['path' => $path, 'version' => $original[0]['id']]);
        $this->assertTrue($error);
        $this->assertStringContainsString('already has', $text);
    }

    public function testThemeFileContentIsChecked(): void
    {
        $this->allowTemplateEditing();
        $name = 'zz-mcp-' . uniqid() . '.twig';
        $path = $this->themeFile($name, '<p>start</p>');
        $file = THEME_DIR . '/default/' . $name;
        $token = $this->createApiKeyToken();
        $version = hash('sha256', '<p>start</p>');
        $write = fn (string $path, string $content, ?string $version = null) => $this->tool($token, 'theme_file_write', ['path' => $path, 'content' => $content, 'expected_version' => $version]);

        // a template that does not compile would break the pages that use it
        foreach (['{% if x %}no end', '{{ unclosed', '{% block a %}{% endblock b %}', '{{ value|nosuchfilter }}', '{{ nosuchfunction() }}', '{% for %}{% endfor %}'] as $broken) {
            [$error, $text] = $write($path, $broken, $version);
            $this->assertTrue($error, $broken);
            $this->assertStringContainsString('syntax error', $text, $broken);
        }
        $this->assertEquals('<p>start</p>', file_get_contents($file));

        // a good template with the functions of the site
        [$error, $done] = $write($path, "{{ parameter('common_title') }} {% for i in 1..3 %}{{ i }}{% endfor %}", $version);
        $this->assertFalse($error, json_encode($done));

        // only the text files of the design
        foreach (['theme/zz-mcp-x.php', 'theme/zz-mcp-x.phtml', 'theme/zz-mcp-x.htaccess', 'theme/zz-mcp-x', 'theme/zz-mcp-x.png', 'resource/zz-mcp-x.svg', 'theme/zz-mcp-x.php.twig.exe'] as $bad) {
            [$error, $text] = $write($bad, 'x');
            $this->assertTrue($error, $bad);
            $this->assertStringContainsString('Only files of these kinds', $text, $bad);
        }

        [$error] = $write($path, str_repeat('a', 300 * 1024 + 1), $done['version']);
        $this->assertTrue($error, 'too big');
        [$error] = $write($path, "binary\0data", $done['version']);
        $this->assertTrue($error, 'a NUL byte');
        [$error] = $this->tool($token, 'theme_file_write', ['path' => $path]);
        $this->assertTrue($error, 'no content');
        [$error] = $this->tool($token, 'theme_file_write', ['path' => $path, 'content' => ['a']]);
        $this->assertTrue($error, 'content is a string');
    }

    public function testNewThemeFile(): void
    {
        $this->allowTemplateEditing();
        $name = 'zz-mcp-new-' . uniqid() . '.css';
        $file = THEME_DIR . '/default/' . $name;
        $this->themeFixtures[] = $file;
        $token = $this->createApiKeyToken();

        // the folder is written by the web server: the tests give it the right
        $mode = fileperms(THEME_DIR . '/default') & 0o777;
        chmod(THEME_DIR . '/default', 0o777);

        try {
            [$error, $created] = $this->tool($token, 'theme_file_write', ['path' => "theme/{$name}", 'content' => 'body { color: red; }']);
            $this->assertFalse($error, json_encode($created));
            $this->assertTrue($created['created']);
            $this->assertNull($created['backup']);
            $this->assertEquals('body { color: red; }', file_get_contents($file));

            // it exists now: a second "new" file is refused, and a version for a file that is not there is refused
            [$error, $text] = $this->tool($token, 'theme_file_write', ['path' => "theme/{$name}", 'content' => 'other']);
            $this->assertTrue($error);
            $this->assertStringContainsString('Read the file first', $text);
            [$error] = $this->tool($token, 'theme_file_write', ['path' => 'theme/zz-mcp-never-' . uniqid() . '.css', 'content' => 'x', 'expected_version' => hash('sha256', 'x')]);
            $this->assertTrue($error);

            // dry run does not create it
            $other = 'theme/zz-mcp-dry-' . uniqid() . '.css';
            [, $dry] = $this->tool($token, 'theme_file_write', ['path' => $other, 'content' => 'x', 'dry_run' => true]);
            $this->assertTrue($dry['created']);
            $this->assertFileDoesNotExist(THEME_DIR . '/default/' . basename($other));
        } finally {
            chmod(THEME_DIR . '/default', $mode);
        }
    }

    public function testThemeBackupsAreLimitedAndTheTemplateCacheIsCleared(): void
    {
        $container = $this->getService(\Psr\Container\ContainerInterface::class);
        $files = new \App\Application\ThemeFiles($container);

        $name = 'zz-mcp-' . uniqid() . '.css';
        $path = $this->themeFile($name, 'v0');

        $version = hash('sha256', 'v0');
        for ($i = 1; $i <= 23; ++$i) {
            // the id has seconds: the versions of one second get the end of their own text
            $result = $files->write($path, "v{$i}", $version);
            $version = $result['version'];
        }

        $this->assertLessThanOrEqual(20, count($files->backups($path)), 'the oldest versions go');
        $this->assertGreaterThan(0, count($files->backups($path)));

        // compiled templates: folders of two hex digits in the cache folder, other things stay
        mkdir(CACHE_DIR . '/ab', 0o777, true);
        file_put_contents(CACHE_DIR . '/ab/abcdef.php', '<?php');
        file_put_contents(CACHE_DIR . '/zz-stays.txt', 'x');
        mkdir(CACHE_DIR . '/zz-other.index', 0o777, true);
        file_put_contents(CACHE_DIR . '/zz-other.index/data', 'x');
        $this->themeFixtures[] = CACHE_DIR . '/zz-stays.txt';

        $files->clearTemplateCache();

        try {
            $this->assertDirectoryDoesNotExist(CACHE_DIR . '/ab');
            $this->assertFileExists(CACHE_DIR . '/zz-stays.txt');
            $this->assertFileExists(CACHE_DIR . '/zz-other.index/data', 'not a template cache (the search index for example)');
        } finally {
            @unlink(CACHE_DIR . '/zz-other.index/data');
            @rmdir(CACHE_DIR . '/zz-other.index');
        }
    }

    public function testWritingTemplatesIsJournaledAndDryRunIsMarked(): void
    {
        $this->allowTemplateEditing();
        $name = 'zz-mcp-' . uniqid() . '.twig';
        $path = $this->themeFile($name);

        $apiKey = $this->getService(\App\Domain\Service\ApiKey\ApiKeyService::class)->create(['title' => $keyTitle = 'шаблон-тест-' . uniqid(), 'is_full_access' => true]);
        $token = $this->getService(\App\Domain\Service\ApiKey\ApiKeyService::class)->issueToken($apiKey);

        $this->tool($token, 'theme_file_write', ['path' => $path, 'content' => '<p>journal</p>', 'expected_version' => hash('sha256', '<p>start</p>'), 'dry_run' => true]);
        $this->tool($token, 'theme_file_write', ['path' => $path, 'content' => '<p>journal</p>', 'expected_version' => hash('sha256', '<p>start</p>')]);

        $log = '';
        foreach (glob(LOG_DIR . '/app*.log') as $logFile) {
            $log .= (string) file_get_contents($logFile);
        }

        $mine = array_values(array_filter(explode("\n", $log), fn ($line) => str_contains($line, $keyTitle) && str_contains($line, 'theme_file_write')));
        $this->assertCount(2, $mine);
        $this->assertStringContainsString('"dry_run":true', $mine[0]);
        $this->assertStringContainsString('"dry_run":false', $mine[1]);
        $this->assertNotEmpty(array_filter(explode("\n", $log), fn ($line) => str_contains($line, 'Theme file written via MCP') && str_contains($line, $name)));
    }

    // every field of a product and of a category
}
