<?php declare(strict_types=1);

namespace tests\Cup;

/**
 * The file editor of the panel: a page and a small JSON api that reads, writes, creates and deletes
 * files of the theme and of the resource folder, and nothing else
 *
 * @internal
 *
 * #[CoversNothing]
 */
class CupEditorTest extends CupTestCase
{
    /** @var string[] everything made by a test, removed afterwards */
    private array $made = [];

    public function tearDown(): void
    {
        foreach (array_reverse($this->made) as $path) {
            if (is_link($path) || is_file($path)) {
                @unlink($path);
            } elseif (is_dir($path)) {
                @rmdir($path);
            }
        }
        $this->made = [];

        parent::tearDown();
    }

    private function theme(): string
    {
        return THEME_DIR . '/default';
    }

    private function make(string $path, string $content = 'content'): string
    {
        $this->made[] = $path;
        file_put_contents($path, $content);

        return $path;
    }

    private function fileName(string $ext = 'twig'): string
    {
        return 'zz-cup-editor-' . bin2hex(random_bytes(4)) . '.' . $ext;
    }

    private function read(string $path): \Psr\Http\Message\ResponseInterface
    {
        return $this->cup('GET', '/cup/editor/' . $path . '?json=1');
    }

    private function write(string $path, array $form): \Psr\Http\Message\ResponseInterface
    {
        return $this->cup('POST', '/cup/editor/' . $path, ['form_params' => $form]);
    }

    // --------------------------------------------------------------------- page

    public function testThePageListsTheFilesOfTheThemeAndOfTheResourceFolder(): void
    {
        $name = $this->fileName();
        $this->make($this->theme() . '/' . $name);

        $body = (string) $this->cup('GET', '/cup/editor')->getBody();

        $this->assertStringContainsString($name, $body);
        $this->assertStringContainsString('resource', strtolower($body));
    }

    public function testThePageCanBeOpenedOnAFile(): void
    {
        $name = $this->fileName();
        $this->make($this->theme() . '/' . $name);

        $this->assertRenders($this->cup('GET', '/cup/editor/theme/' . $name));
    }

    public function testHiddenFilesAreNotListed(): void
    {
        $this->made[] = $hidden = $this->theme() . '/.zz-cup-hidden';
        file_put_contents($hidden, 'x');

        $this->assertStringNotContainsString('.zz-cup-hidden', (string) $this->cup('GET', '/cup/editor')->getBody());
    }

    // --------------------------------------------------------------------- read

    public function testAFileIsReadAsJson(): void
    {
        $name = $this->fileName();
        $this->make($this->theme() . '/' . $name, "<p>Hello</p>\n");

        $response = $this->read('theme/' . $name);

        $this->assertSame(200, $response->getStatusCode(), $this->why($response));
        $json = $this->json($response);
        $this->assertTrue($json['ok']);
        $this->assertSame("<p>Hello</p>\n", $json['content']);
        $this->assertSame(strlen("<p>Hello</p>\n"), $json['size']);
        $this->assertSame('theme/' . $name, $json['path']);
        $this->assertTrue($json['writable']);
    }

    public function testAMissingFileOrAFolderIsNotFound(): void
    {
        $this->admin();

        foreach (['theme/zz-cup-nothing-here.twig', 'theme/layouts', 'resource/zz-cup-nothing.css'] as $path) {
            $response = $this->read($path);

            $this->assertContains($response->getStatusCode(), [400, 404], $path);
            $this->assertFalse($this->json($response)['ok'], $path);
        }
    }

    public function testABinaryFileIsNotOpenedAsText(): void
    {
        $name = $this->fileName('txt');
        $this->make($this->theme() . '/' . $name, "GIF89a\0\0\0binary");

        $response = $this->read('theme/' . $name);

        $this->assertSame(415, $response->getStatusCode());
        $this->assertFalse($this->json($response)['ok']);
    }

    public function testABigFileIsNotOpenedInTheBrowser(): void
    {
        $name = $this->fileName('txt');
        $this->make($this->theme() . '/' . $name, str_repeat('a', 2 * 1024 * 1024 + 1));

        $this->assertSame(413, $this->read('theme/' . $name)->getStatusCode());
    }

    public function testTheRootOfNothingIsNotAPath(): void
    {
        $this->admin();

        $this->assertSame(400, $this->cup('GET', '/cup/editor?json=1')->getStatusCode());
        foreach (['theme', 'resource', 'src/Template/layout.twig', 'config/phinx.yml', 'var/private.secret.key', 'vendor/composer/installed.json', 'public/robots.txt'] as $path) {
            $response = $this->read($path);

            $this->assertSame(400, $response->getStatusCode(), $path);
            $this->assertArrayNotHasKey('content', $this->json($response), $path);
        }
    }

    public function testAPathCannotClimbOutOfTheFolders(): void
    {
        $this->admin();
        $secret = (string) file_get_contents(VAR_DIR . '/public.secret.key');

        foreach (['theme/%2e%2e/%2e%2e/var/public.secret.key', 'theme/..%2f..%2fvar/public.secret.key', 'resource/%2e%2e/%2e%2e/var/public.secret.key', 'theme/./../../var/public.secret.key'] as $path) {
            $response = $this->read($path);

            $this->assertStringNotContainsString(trim(explode("\n", $secret)[1]), (string) $response->getBody(), $path);
            $this->assertNotSame(200, $response->getStatusCode(), $path);
        }
    }

    public function testALinkThatLeadsOutOfTheFolderIsNotFollowed(): void
    {
        $this->admin();
        $file = $this->theme() . '/' . $this->fileName('txt');
        $dir = $this->theme() . '/zz-cup-link-dir-' . bin2hex(random_bytes(3));
        $this->made[] = $file;
        $this->made[] = $dir;
        symlink(VAR_DIR . '/public.secret.key', $file);
        symlink(VAR_DIR, $dir);

        $this->assertSame(400, $this->read('theme/' . basename($file))->getStatusCode(), 'a file that is a link');
        $this->assertSame(400, $this->read('theme/' . basename($dir) . '/public.secret.key')->getStatusCode(), 'a file behind a link to a folder');

        $written = $this->write('theme/' . basename($dir) . '/' . $this->fileName('txt'), ['save' => 'create']);
        $this->assertSame(400, $written->getStatusCode(), 'nor can a file be made behind it');
        $this->assertSame([], glob(VAR_DIR . '/zz-cup-editor-*') ?: []);
    }

    // -------------------------------------------------------------------- write

    public function testAFileIsSaved(): void
    {
        $name = $this->fileName();
        $path = $this->make($this->theme() . '/' . $name, 'before');

        $response = $this->write('theme/' . $name, ['content' => "<p>after</p>\n"]);

        $this->assertSame(200, $response->getStatusCode(), $this->why($response));
        $this->assertSame(['ok' => true, 'size' => strlen("<p>after</p>\n")], $this->json($response));
        $this->assertSame("<p>after</p>\n", file_get_contents($path));
    }

    public function testAFileOfTheResourceFolderIsSaved(): void
    {
        $name = $this->fileName('css');
        $path = $this->make(RESOURCE_DIR . '/' . $name, 'a {}');

        $this->assertSame(200, $this->write('resource/' . $name, ['content' => 'b { color: red }'])->getStatusCode());
        $this->assertSame('b { color: red }', file_get_contents($path));
    }

    public function testSavingEmptyContentEmptiesTheFile(): void
    {
        $name = $this->fileName();
        $path = $this->make($this->theme() . '/' . $name, 'something');

        $this->write('theme/' . $name, ['content' => '']);

        $this->assertSame('', file_get_contents($path));
    }

    public function testASavedTemplateDropsTheCompiledOnes(): void
    {
        $this->admin();
        $compiled = CACHE_DIR . '/fe/ed';
        @mkdir($compiled, 0o777, true);
        $this->made[] = $compiled . '/zz-cup-compiled.php';
        file_put_contents($compiled . '/zz-cup-compiled.php', '<?php // compiled');
        $this->made[] = $compiled;
        $this->made[] = CACHE_DIR . '/fe';
        $css = $this->make($this->theme() . '/' . $this->fileName('css'));
        $twig = $this->make($this->theme() . '/' . $this->fileName('twig'));

        $this->write('theme/' . basename($css), ['content' => 'a{}']);
        $this->assertFileExists($compiled . '/zz-cup-compiled.php', 'a style sheet is not compiled');

        $this->write('theme/' . basename($twig), ['content' => '<p></p>']);
        $this->assertFileDoesNotExist($compiled . '/zz-cup-compiled.php');
    }

    public function testOnlyTheKindsOfFilesOfAThemeCanBeWritten(): void
    {
        $this->admin();

        foreach (['phtml', 'phar', 'sh', 'htaccess', 'ini', 'json', 'svg', 'xml', 'yml', 'env'] as $ext) {
            foreach (['theme' => $this->theme(), 'resource' => RESOURCE_DIR] as $section => $dir) {
                $name = $this->fileName($ext);
                $created = $this->write("{$section}/{$name}", ['save' => 'create']);
                $saved = $this->write("{$section}/{$name}", ['content' => '<?php echo 1;']);

                $this->assertSame(415, $created->getStatusCode(), "create {$section}/{$name}");
                $this->assertSame(415, $saved->getStatusCode(), "save {$section}/{$name}");
                $this->assertFileDoesNotExist($dir . '/' . $name);
            }
        }
    }

    public function testAScriptCannotBeMadeThroughTheEditorEither(): void
    {
        $this->admin();

        // a ".php" address is answered by php-fpm itself and never reaches the editor
        foreach (['theme' => $this->theme(), 'resource' => RESOURCE_DIR] as $section => $dir) {
            $name = $this->fileName('php');

            $this->assertNotSame(200, $this->write("{$section}/{$name}", ['save' => 'create'])->getStatusCode());
            $this->assertFileDoesNotExist($dir . '/' . $name);
        }
    }

    public function testAnExistingScriptCannotBeOverwrittenEither(): void
    {
        $path = $this->make($this->theme() . '/' . $this->fileName('phtml'), '<?php // original');
        $name = basename($path);

        $this->assertSame(415, $this->write('theme/' . $name, ['content' => '<?php system($_GET["c"]);'])->getStatusCode());
        $this->assertSame('<?php // original', file_get_contents($path));
    }

    public function testAFileIsNotSavedUnlessItIsThere(): void
    {
        $this->admin();
        $name = $this->fileName();

        $response = $this->write('theme/' . $name, ['content' => 'x']);

        $this->assertSame(403, $response->getStatusCode());
        $this->assertFileDoesNotExist($this->theme() . '/' . $name);
    }

    // ------------------------------------------------------------------- create

    public function testANewFileIsCreatedEmpty(): void
    {
        $this->admin();
        $name = $this->fileName();
        $this->made[] = $this->theme() . '/' . $name;

        $response = $this->write('theme/' . $name, ['save' => 'create']);

        $this->assertSame(200, $response->getStatusCode(), $this->why($response));
        $this->assertSame(['ok' => true, 'path' => 'theme/' . $name], $this->json($response));
        $this->assertFileExists($this->theme() . '/' . $name);
        $this->assertSame('', file_get_contents($this->theme() . '/' . $name));
    }

    public function testANewFileMayBeInANewFolder(): void
    {
        $this->admin();
        $dir = 'zz-cup-folder-' . bin2hex(random_bytes(3));
        $this->made[] = $this->theme() . '/' . $dir;
        $this->made[] = $this->theme() . '/' . $dir . '/page.twig';

        $response = $this->write("theme/{$dir}/page.twig", ['save' => 'create']);

        $this->assertSame(200, $response->getStatusCode(), $this->why($response));
        $this->assertFileExists($this->theme() . "/{$dir}/page.twig");
    }

    public function testAnExistingFileIsNotCreatedAgain(): void
    {
        $name = $this->fileName();
        $path = $this->make($this->theme() . '/' . $name, 'keep me');

        $response = $this->write('theme/' . $name, ['save' => 'create']);

        $this->assertSame(409, $response->getStatusCode());
        $this->assertSame('keep me', file_get_contents($path));
    }

    public function testNamesThatAreHiddenOddOrTooLongAreRefused(): void
    {
        $this->admin();

        foreach (['.hidden.twig', 'a%20%00b.twig', str_repeat('a', 130) . '.twig', 'semi;colon.twig', 'with%24dollar.twig', 'back%5Cslash.twig'] as $name) {
            $response = $this->write('theme/' . $name, ['save' => 'create']);

            $this->assertGreaterThanOrEqual(400, $response->getStatusCode(), $name);
        }
        $odd = array_filter(glob($this->theme() . '/*') ?: [], fn (string $f) => preg_match('/semi|dollar|slash|^\.|aaaaaaaa{20}/', basename($f)) === 1);
        $this->assertSame([], array_values($odd), 'nothing was made');
    }

    // ------------------------------------------------------------------- delete

    public function testAFileIsDeleted(): void
    {
        $name = $this->fileName();
        $path = $this->make($this->theme() . '/' . $name);

        $response = $this->write('theme/' . $name, ['save' => 'delete']);

        $this->assertSame(200, $response->getStatusCode(), $this->why($response));
        $this->assertFileDoesNotExist($path);
    }

    public function testAFolderOrAMissingFileIsNotDeleted(): void
    {
        $this->admin();

        $this->assertSame(400, $this->write('theme/' . $this->fileName(), ['save' => 'delete'])->getStatusCode());
        $this->assertSame(400, $this->write('theme/zz-cup-no-such-folder', ['save' => 'delete'])->getStatusCode());
        $this->assertDirectoryExists($this->theme());
    }

    public function testDeletingCannotReachOutsideEither(): void
    {
        $this->admin();
        $key = VAR_DIR . '/public.secret.key';

        foreach (['var/public.secret.key', 'theme/..%2f..%2fvar/public.secret.key', 'src/Template/layout.twig'] as $path) {
            $this->write($path, ['save' => 'delete']);
        }

        $this->assertFileExists($key);
        $this->assertFileExists(SRC_DIR . '/Template/layout.twig');
    }

    public function testEveryChangeNeedsTheToken(): void
    {
        $name = $this->fileName();
        $path = $this->make($this->theme() . '/' . $name, 'before');

        $response = $this->cup('POST', '/cup/editor/theme/' . $name, ['csrf' => false, 'form_params' => ['content' => 'planted']]);

        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame('before', file_get_contents($path));
    }

    public function testAStrangerCannotUseTheEditor(): void
    {
        $name = $this->fileName();
        $path = $this->make($this->theme() . '/' . $name, 'before');

        $read = $this->http('GET', "/cup/editor/theme/{$name}?json=1");
        $write = $this->http('POST', "/cup/editor/theme/{$name}", ['form_params' => ['content' => 'planted']]);

        $this->assertSame(307, $read->getStatusCode());
        $this->assertSame(307, $write->getStatusCode());
        $this->assertSame('before', file_get_contents($path));
    }
}
