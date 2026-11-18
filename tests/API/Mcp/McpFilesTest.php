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
 * Files of the records: attaching, arranging, limits
 *
 * @internal
 *
 * #[CoversNothing]
 */
class McpFilesTest extends McpTestCase
{
    public function testFilesOfEveryKindOfRecord(): void
    {
        $category = $this->getService(CatalogCategoryService::class)->create(['title' => 'Goods', 'address' => 'goods']);
        $product = $this->makeProduct('Thing', (string) $category->uuid);
        $publicationCategory = $this->getService(PublicationCategoryService::class)->create(['title' => 'News', 'address' => 'news']);
        $publication = $this->getService(\App\Domain\Service\Publication\PublicationService::class)->create(['title' => 'First', 'address' => 'news/first', 'category_uuid' => $publicationCategory->uuid, 'content' => ['short' => '', 'full' => ''], 'date' => '2026-01-01 10:00:00']);
        $page = $this->getService(PageService::class)->create(['title' => 'About', 'address' => 'about', 'content' => 'x']);
        $group = $this->getService(\App\Domain\Service\User\GroupService::class)->create(['title' => 'Customers']);
        $user = $this->getService(\App\Domain\Service\User\UserService::class)->create(['username' => 'ivan', 'email' => 'ivan@example.com', 'group_uuid' => $group->uuid]);
        $form = $this->getService(FormService::class)->create(['title' => 'Feedback', 'address' => 'feedback']);
        $submission = $this->getService(FormDataService::class)->create(['form_uuid' => $form->uuid, 'data' => ['name' => 'Ivan'], 'message' => 'x']);
        $productForReview = $this->makeProduct('Reviewed', (string) $category->uuid);
        $review = $this->getService(ReviewService::class)->create(['type' => 'review', 'entity_type' => 'catalog_product', 'entity_uuid' => $productForReview->uuid, 'rating' => 5, 'message' => 'Good', 'status' => 'work']);

        $records = [
            'catalog_product' => $product, 'catalog_category' => $category, 'publication' => $publication, 'publication_category' => $publicationCategory,
            'page' => $page, 'user' => $user, 'user_group' => $group, 'review' => $review, 'form_data' => $submission,
        ];
        $this->assertEqualsCanonicalizing(array_keys(\App\Application\EntityFiles::ENTITIES), array_keys($records), 'every kind of records that has files');

        $token = $this->fileKey();

        foreach ($records as $entity => $record) {
            [, $before] = $this->tool($token, 'entity_file_list', ['entity' => $entity, 'uuid' => (string) $record->getKey()]);
            $this->assertSame([], $before['files'], $entity);

            [$error, $result] = $this->tool($token, 'entity_file_attach', ['entity' => $entity, 'uuid' => (string) $record->getKey(), 'content_base64' => base64_encode($this->picture()), 'name' => 'photo.png', 'comment' => "of {$entity}"]);
            $this->assertFalse($error, $entity . ': ' . json_encode($result));
            $this->assertTrue($result['created_file']);
            $this->assertEquals(1, $result['files_of_record']);

            [, $after] = $this->tool($token, 'entity_file_list', ['entity' => $entity, 'uuid' => (string) $record->getKey()]);
            $this->assertCount(1, $after['files'], $entity);
            $this->assertEquals(["of {$entity}", 1, 'image/png'], [$after['files'][0]['comment'], $after['files'][0]['order'], $after['files'][0]['type']], $entity);
            $this->assertMatchesRegularExpression('#^(https?://[^/]+)?/uploads/[0-9a-f]+/photo\.png$#', $after['files'][0]['url'], $entity);
            $this->assertFileExists($this->urlToPath($after['files'][0]['url']));
        }

        // the pictures are in the file list of the site, one each
        [, $all] = $this->tool($token, 'file_list', ['kind' => 'image', 'limit' => 50]);
        $this->assertCount(9, $all['items']);
    }

    public function testFileByAnAddress(): void
    {
        $category = $this->getService(CatalogCategoryService::class)->create(['title' => 'Goods', 'address' => 'goods']);
        $product = $this->makeProduct('Thing', (string) $category->uuid);
        $token = $this->fileKey();
        $attach = fn (array $args) => $this->tool($token, 'entity_file_attach', ['entity' => 'catalog_product', 'uuid' => (string) $product->uuid] + $args);

        $name = 'zz-mcp-' . uniqid();
        $first = $this->serve($this->picture('jpg'), "{$name}-a.jpg");
        $second = $this->serve($this->picture('png'), "{$name}-b.png");

        [$error, $result] = $attach(['url' => $first, 'comment' => 'front']);
        $this->assertFalse($error, json_encode($result));
        $this->assertEquals([true, false, 1, 'front', "{$name}-a.jpg"], [$result['created_file'], $result['already_attached'], $result['file']['order'], $result['file']['comment'], $result['file']['name']]);

        // the second one is the last one, the address may have a query and the name may be told
        [, $result] = $attach(['url' => $second . '?v=2']);
        $this->assertEquals([2, "{$name}-b.png"], [$result['file']['order'], $result['file']['name']]);

        // the same address again changes nothing but the caption; the same file for another record is the same stored file
        [, $again] = $attach(['url' => $first, 'comment' => 'main picture']);
        $this->assertTrue($again['already_attached']);
        $this->assertFalse($again['created_file']);
        $this->assertEquals([1, 'main picture', 2], [$again['file']['order'], $again['file']['comment'], $again['files_of_record']]);
        $this->assertCount(2, $this->tool($token, 'file_list', ['kind' => 'image'])[1]['items'], 'stored once');

        [, $other] = $this->tool($token, 'entity_file_attach', ['entity' => 'catalog_category', 'uuid' => (string) $category->uuid, 'url' => $first]);
        $this->assertFalse($other['created_file']);
        $this->assertEquals($again['file']['uuid'], $other['file']['uuid']);
        $this->assertCount(2, $this->tool($token, 'file_list', ['kind' => 'image'])[1]['items']);

        // an address without an extension needs a name
        $bare = $this->serve($this->picture('jpg'), "{$name}-bare");
        [$error, $text] = $attach(['url' => $bare]);
        $this->assertTrue($error);
        $this->assertStringContainsString('"name"', $text);
        [$error, $result] = $attach(['url' => $bare, 'name' => 'back.jpg', 'order' => 7]);
        $this->assertFalse($error, json_encode($result));
        $this->assertEquals([7, 'back.jpg'], [$result['file']['order'], $result['file']['name']]);

        // what is not a picture although it is named so, what is not allowed, what is not there
        $page = $this->serve('<html><script>alert(1)</script></html>', "{$name}-page.jpg");
        [$error, $text] = $attach(['url' => $page]);
        $this->assertTrue($error);
        $this->assertMatchesRegularExpression('/not allowed|not a real/', $text);
        $this->assertCount(3, $this->tool($token, 'entity_file_list', ['entity' => 'catalog_product', 'uuid' => (string) $product->uuid])[1]['files'], 'nothing was added');

        foreach ([
            ['url' => 'http://127.0.0.1:80/' . $name . '-missing.jpg'],
            ['url' => 'ftp://example.com/a.jpg'], ['url' => 'file:///etc/passwd'], ['url' => '/a.jpg'], ['url' => 'javascript:alert(1)'],
            ['url' => 'http://127.0.0.1:80/script.php'], ['url' => 'http://127.0.0.1:80/a.svg'], ['url' => 'http://127.0.0.1:80/a.html'], ['url' => 'http://127.0.0.1:80/a.exe'],
            [], ['url' => $first, 'content_base64' => 'AAAA', 'name' => 'a.png'],
        ] as $args) {
            [$error] = $attach($args);
            $this->assertTrue($error, json_encode($args));
        }

        // not for the public internet addresses only: an address of the server's own network is refused when it is not a test
        $container = $this->getService(\Psr\Container\ContainerInterface::class);
        $record = \App\Domain\Models\CatalogProduct::query()->where('uuid', (string) $product->uuid)->first();
        $test = $_ENV['TEST'] ?? null;
        unset($_ENV['TEST']);

        try {
            foreach (['http://127.0.0.1:80/' . "{$name}-a.jpg", 'http://localhost/a.jpg', 'http://169.254.169.254/latest/meta-data/a.jpg', 'http://10.0.0.5/a.jpg'] as $internal) {
                try {
                    (new \App\Application\EntityFiles($container))->attach($record, ['url' => $internal], null, null, true);
                    $this->fail("{$internal} was taken");
                } catch (\DomainException $e) {
                    $this->assertStringContainsString('could not be downloaded', $e->getMessage());
                }
            }
        } finally {
            $_ENV['TEST'] = $test;
        }
    }

    public function testFileByContent(): void
    {
        $category = $this->getService(CatalogCategoryService::class)->create(['title' => 'Goods', 'address' => 'goods']);
        $product = $this->makeProduct('Thing', (string) $category->uuid);
        $token = $this->fileKey();
        $attach = fn (array $args) => $this->tool($token, 'entity_file_attach', ['entity' => 'catalog_product', 'uuid' => (string) $product->uuid] + $args);

        $docx = tempnam(sys_get_temp_dir(), 'zz');
        $zip = new \ZipArchive();
        $zip->open($docx, \ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>');
        $zip->addFromString('word/document.xml', '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body>' . uniqid() . '</w:body></w:document>');
        $zip->close();

        foreach ([
            ['photo.png', $this->picture('png'), 'image/png'],
            ['PHOTO.JPG', $this->picture('jpg'), 'image/jpeg'],
            ['manual.pdf', "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\n% " . uniqid() . "\ntrailer << /Root 1 0 R >>\n%%EOF\n", 'application/pdf'],
            ['notes.txt', 'Plain notes ' . uniqid(), 'text/plain'],
            ['letter.docx', (string) file_get_contents($docx), null],
        ] as [$name, $content, $type]) {
            [$error, $result] = $attach(['content_base64' => base64_encode($content), 'name' => $name]);
            $this->assertFalse($error, "{$name}: " . json_encode($result));
            $this->assertTrue($result['created_file'], $name);
            $type === null || $this->assertEquals($type, $result['file']['type'], $name);
        }
        unlink($docx);
        $this->assertCount(5, $this->tool($token, 'entity_file_list', ['entity' => 'catalog_product', 'uuid' => (string) $product->uuid])[1]['files']);

        // what the file really is decides, not its name
        $big = random_bytes(5 * 1024 * 1024 + 10);
        foreach ([
            'html named jpg' => ['content_base64' => base64_encode('<html><script>alert(1)</script></html>'), 'name' => 'a.jpg'],
            'php named pdf' => ['content_base64' => base64_encode("<?php system(\$_GET['c']);"), 'name' => 'a.pdf'],
            'text named png' => ['content_base64' => base64_encode('just text'), 'name' => 'a.png'],
            'a picture named pdf' => ['content_base64' => base64_encode($this->picture('png')), 'name' => 'a.pdf'],
            'svg' => ['content_base64' => base64_encode('<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"/>'), 'name' => 'a.svg'],
            'a program' => ['content_base64' => base64_encode("MZ\x90\x00\x03\x00\x00\x00\x04\x00\x00\x00\xff\xff"), 'name' => 'a.exe'],
            'a program named doc' => ['content_base64' => base64_encode("\x7fELF\x02\x01\x01\x00" . str_repeat("\x00", 64)), 'name' => 'a.pdf'],
            'php' => ['content_base64' => base64_encode('<?php echo 1;'), 'name' => 'a.php'],
            'phtml' => ['content_base64' => base64_encode('<?php echo 1;'), 'name' => 'a.phtml'],
            'a double extension' => ['content_base64' => base64_encode('<?php echo 1;'), 'name' => 'a.php.png'],
            'no extension' => ['content_base64' => base64_encode($this->picture()), 'name' => 'photo'],
            'a hidden name' => ['content_base64' => base64_encode($this->picture()), 'name' => '.htaccess'],
            'no name' => ['content_base64' => base64_encode($this->picture())],
            'not base64' => ['content_base64' => '!!! not base64 !!!', 'name' => 'a.png'],
            'empty' => ['content_base64' => base64_encode(''), 'name' => 'a.png'],
            'too big' => ['content_base64' => base64_encode($big), 'name' => 'a.pdf'],
            'a long name' => ['content_base64' => base64_encode($this->picture()), 'name' => str_repeat('a', 130) . '.png'],
        ] as $what => $args) {
            [$error, $text] = $attach($args);
            $this->assertTrue($error, $what);
            // the reason is told in words, and for a kind that is not allowed it is the first thing that is checked
            $reason = match (true) {
                in_array($what, ['svg', 'a program', 'php', 'phtml'], true) => 'are not allowed',
                in_array($what, ['html named jpg', 'php named pdf', 'text named png', 'a picture named pdf', 'a program named doc'], true) => 'not a real',
                default => null,
            };
            $reason === null || $this->assertStringContainsString($reason, $text, $what);
        }

        // nothing of the refused ones is on the site
        $this->assertCount(5, $this->tool($token, 'file_list', ['limit' => 100, 'editable_only' => false])[1]['items']);
        $this->assertCount(5, $this->tool($token, 'entity_file_list', ['entity' => 'catalog_product', 'uuid' => (string) $product->uuid])[1]['files']);
    }

    public function testFilesAreArrangedAndTakenAway(): void
    {
        $category = $this->getService(CatalogCategoryService::class)->create(['title' => 'Goods', 'address' => 'goods']);
        $product = $this->makeProduct('Thing', (string) $category->uuid);
        $token = $this->fileKey();
        $uuid = (string) $product->uuid;

        $files = [];
        foreach (['a', 'b', 'c'] as $name) {
            [, $result] = $this->tool($token, 'entity_file_attach', ['entity' => 'catalog_product', 'uuid' => $uuid, 'content_base64' => base64_encode($this->picture()), 'name' => "{$name}.png"]);
            $files[$name] = $result['file']['uuid'];
        }
        $names = fn (array $result) => array_column($result['files'], 'name');
        $list = fn () => $this->tool($token, 'entity_file_list', ['entity' => 'catalog_product', 'uuid' => $uuid])[1];

        $this->assertEquals(['a.png', 'b.png', 'c.png'], $names($list()));

        // order: the ones that are not listed go after
        [$error, $result] = $this->tool($token, 'entity_file_arrange', ['entity' => 'catalog_product', 'uuid' => $uuid, 'order' => [$files['c']], 'comments' => [$files['a'] => 'first one', $files['b'] => str_repeat('я', 600)]]);
        $this->assertFalse($error, json_encode($result));
        $this->assertEquals(['c.png', 'a.png', 'b.png'], $names($result));
        $this->assertEquals([1, 2, 3], array_column($result['files'], 'order'));
        $byName = array_column($result['files'], null, 'name');
        $this->assertEquals('first one', $byName['a.png']['comment']);
        $this->assertEquals(500, mb_strlen($byName['b.png']['comment']), 'a caption is cut to 500');

        [, $result] = $this->tool($token, 'entity_file_arrange', ['entity' => 'catalog_product', 'uuid' => $uuid, 'order' => [$files['b'], $files['c'], $files['a']], 'comments' => [$files['a'] => '']]);
        $this->assertEquals(['b.png', 'c.png', 'a.png'], $names($result));
        $this->assertEquals('', array_column($result['files'], null, 'name')['a.png']['comment']);

        foreach ([
            [], ['order' => 'a'], ['comments' => ['a']], ['comments' => 'x'],
            ['order' => [\Ramsey\Uuid\Uuid::uuid4()->toString()]], ['order' => [$files['a'], $files['a']]], ['order' => ['x' => $files['a']]],
            ['comments' => [\Ramsey\Uuid\Uuid::uuid4()->toString() => 'x']],
        ] as $args) {
            [$error] = $this->tool($token, 'entity_file_arrange', ['entity' => 'catalog_product', 'uuid' => $uuid] + $args);
            $this->assertTrue($error, json_encode($args));
        }
        $this->assertEquals(['b.png', 'c.png', 'a.png'], $names($list()), 'refused calls changed nothing');

        // dry run of an arrangement and of a detach: nothing changes
        $this->tool($token, 'entity_file_arrange', ['entity' => 'catalog_product', 'uuid' => $uuid, 'order' => [$files['a']], 'dry_run' => true]);
        $this->assertEquals(['b.png', 'c.png', 'a.png'], $names($list()));
        [, $dry] = $this->tool($token, 'entity_file_detach', ['entity' => 'catalog_product', 'uuid' => $uuid, 'file' => $files['a'], 'dry_run' => true]);
        $this->assertTrue($dry['dry_run']);
        $this->assertCount(3, $list()['files']);

        // taking away: the file stays in the store, and it is said if somebody else uses it
        $this->tool($token, 'entity_file_attach', ['entity' => 'catalog_category', 'uuid' => (string) $category->uuid, 'content_base64' => base64_encode((string) file_get_contents($this->urlToPath($byName['a.png']['url']))), 'name' => 'a.png']);
        [$error, $gone] = $this->tool($token, 'entity_file_detach', ['entity' => 'catalog_product', 'uuid' => $uuid, 'file' => $files['a']]);
        $this->assertFalse($error, json_encode($gone));
        $this->assertEquals([[$files['a']], [$files['a']], 2], [$gone['detached'], $gone['used_elsewhere'], $gone['files_of_record']]);
        $this->assertEquals(['b.png', 'c.png'], $names($list()));

        [, $gone] = $this->tool($token, 'entity_file_detach', ['entity' => 'catalog_product', 'uuid' => $uuid, 'file' => $files['b']]);
        $this->assertSame([], $gone['used_elsewhere'], 'nobody uses it');
        $this->assertCount(3, $this->tool($token, 'file_list', ['kind' => 'image'])[1]['items'], 'the files are still in the store');

        foreach ([['file' => $files['b']], ['file' => 'nope'], ['file' => \Ramsey\Uuid\Uuid::uuid4()->toString()], []] as $args) {
            [$error] = $this->tool($token, 'entity_file_detach', ['entity' => 'catalog_product', 'uuid' => $uuid] + $args);
            $this->assertTrue($error, json_encode($args));
        }
    }

    public function testFileToolsFollowKeyRights(): void
    {
        $category = $this->getService(CatalogCategoryService::class)->create(['title' => 'Goods', 'address' => 'goods']);
        $product = $this->makeProduct('Thing', (string) $category->uuid);
        $page = $this->getService(PageService::class)->create(['title' => 'About', 'address' => 'about', 'content' => 'x']);
        $groups = $this->getService(\App\Domain\Service\User\GroupService::class);
        $staff = $this->getService(\App\Domain\Service\User\UserService::class)->create(['username' => 'boss', 'email' => 'boss@example.com', 'group_uuid' => $groups->create(['title' => 'Admins', 'access' => ['cup:main']])->uuid]);
        $form = $this->getService(FormService::class)->create(['title' => 'Feedback', 'address' => 'feedback']);
        $submission = $this->getService(FormDataService::class)->create(['form_uuid' => $form->uuid, 'data' => ['name' => 'Ivan'], 'message' => 'x']);

        $fileTools = ['entity_file_list', 'entity_file_attach', 'entity_file_arrange', 'entity_file_detach'];
        $picture = base64_encode($this->picture());

        $none = $this->tools($this->createApiKeyToken(['read' => ['user/group']], false));
        $this->assertContains('entity_file_list', $none, 'a right to read some records is enough to see the list');
        $this->assertNotContains('entity_file_attach', $none);

        $writer = $this->createApiKeyToken(['read' => ['catalog/product'], 'write' => ['catalog/product']], false);
        foreach ($fileTools as $name) {
            $this->assertContains($name, $this->tools($writer), $name);
        }

        // the kind of records is checked by the right for it
        [$error, $result] = $this->tool($writer, 'entity_file_attach', ['entity' => 'catalog_product', 'uuid' => (string) $product->uuid, 'content_base64' => $picture, 'name' => 'a.png']);
        $this->assertFalse($error, json_encode($result));
        [$error, $text] = $this->tool($writer, 'entity_file_attach', ['entity' => 'page', 'uuid' => (string) $page->uuid, 'content_base64' => $picture, 'name' => 'b.png']);
        $this->assertTrue($error);
        $this->assertStringContainsString('no write access to page', $text);
        [$error] = $this->tool($writer, 'entity_file_list', ['entity' => 'page', 'uuid' => (string) $page->uuid]);
        $this->assertTrue($error);

        // reading is not writing
        $reader = $this->createApiKeyToken(['read' => ['catalog/product']], false);
        [$error] = $this->tool($reader, 'entity_file_list', ['entity' => 'catalog_product', 'uuid' => (string) $product->uuid]);
        $this->assertFalse($error);
        $this->assertNotContains('entity_file_attach', $this->tools($reader));
        $this->assertEquals(-32602, $this->call($reader, 'tools/call', ['name' => 'entity_file_attach', 'arguments' => ['entity' => 'catalog_product']])['error']['code']);

        // the files of a form submission are for full access keys only; a staff account is not changed
        [$error] = $this->tool($writer, 'entity_file_list', ['entity' => 'form_data', 'uuid' => (string) $submission->uuid]);
        $this->assertTrue($error);
        $full = $this->fileKey();
        [$error, $result] = $this->tool($full, 'entity_file_attach', ['entity' => 'form_data', 'uuid' => (string) $submission->uuid, 'content_base64' => $picture, 'name' => 'c.png']);
        $this->assertFalse($error, json_encode($result));
        [$error, $text] = $this->tool($full, 'entity_file_attach', ['entity' => 'user', 'uuid' => (string) $staff->uuid, 'content_base64' => $picture, 'name' => 'd.png']);
        $this->assertTrue($error);
        $this->assertStringContainsString('staff', $text);

        foreach ([
            ['entity' => 'cars', 'uuid' => (string) $product->uuid], ['entity' => 'catalog_product', 'uuid' => 'nope'], ['entity' => 'catalog_product', 'uuid' => \Ramsey\Uuid\Uuid::uuid4()->toString()],
            ['uuid' => (string) $product->uuid], ['entity' => 'catalog_product'], [],
        ] as $args) {
            [$error] = $this->tool($full, 'entity_file_attach', $args + ['content_base64' => $picture, 'name' => 'e.png']);
            $this->assertTrue($error, json_encode($args));
        }

        // without a key there is nothing
        $this->getService(ParameterService::class)->update($this->getService(ParameterService::class)->read(['name' => 'entity_access']), ['value' => 'all']);
        foreach ($fileTools as $name) {
            $this->assertNotContains($name, $this->tools(null), $name);
        }
    }

    public function testDryRunOfAttachStoresNothing(): void
    {
        $category = $this->getService(CatalogCategoryService::class)->create(['title' => 'Goods', 'address' => 'goods']);
        $product = $this->makeProduct('Thing', (string) $category->uuid);
        $token = $this->fileKey();
        $uploads = fn () => count(glob(UPLOAD_DIR . '/*/*') ?: []);
        $before = $uploads();

        $definitions = array_column($this->call($token, 'tools/list')['result']['tools'], null, 'name');
        $this->assertArrayHasKey('dry_run', $definitions['entity_file_attach']['inputSchema']['properties']);

        $content = base64_encode($this->picture());
        [$error, $dry] = $this->tool($token, 'entity_file_attach', ['entity' => 'catalog_product', 'uuid' => (string) $product->uuid, 'content_base64' => $content, 'name' => 'dry.png', 'dry_run' => true]);
        $this->assertFalse($error, json_encode($dry));
        $this->assertTrue($dry['dry_run']);
        $this->assertEquals([false, true, 'image/png'], [$dry['file_exists_on_site'], $dry['would_be_attached'], $dry['type']]);
        $this->assertEquals($before, $uploads(), 'the file is not stored');
        $this->assertSame([], $this->tool($token, 'entity_file_list', ['entity' => 'catalog_product', 'uuid' => (string) $product->uuid])[1]['files']);
        $this->assertSame([], $this->tool($token, 'file_list', [])[1]['items']);

        // the check is the real one: a bad file fails the rehearsal too
        [$error] = $this->tool($token, 'entity_file_attach', ['entity' => 'catalog_product', 'uuid' => (string) $product->uuid, 'content_base64' => base64_encode('<html>'), 'name' => 'dry.jpg', 'dry_run' => true]);
        $this->assertTrue($error);

        // after the real call the rehearsal knows the file is there
        $this->tool($token, 'entity_file_attach', ['entity' => 'catalog_product', 'uuid' => (string) $product->uuid, 'content_base64' => $content, 'name' => 'dry.png']);
        [, $dry] = $this->tool($token, 'entity_file_attach', ['entity' => 'catalog_product', 'uuid' => (string) $product->uuid, 'content_base64' => $content, 'name' => 'dry.png', 'dry_run' => true]);
        $this->assertEquals([true, true, false], [$dry['file_exists_on_site'], $dry['already_attached'], $dry['would_be_attached']]);
    }

    public function testNumberOfFilesOfARecordIsLimited(): void
    {
        $category = $this->getService(CatalogCategoryService::class)->create(['title' => 'Goods', 'address' => 'goods']);
        $product = $this->makeProduct('Thing', (string) $category->uuid);
        $token = $this->fileKey();

        // the files are made by the store directly: 100 calls of the tool are not needed to reach the limit
        $files = $this->getService(\App\Domain\Service\File\FileService::class);
        for ($i = 0; $i < 100; ++$i) {
            $file = $files->create(['name' => "f{$i}", 'ext' => 'png', 'type' => 'image/png', 'size' => 1, 'salt' => "s{$i}", 'hash' => md5("f{$i}")]);
            \App\Domain\Models\CatalogProduct::query()->where('uuid', $product->uuid)->first()->files()->attach($file->uuid, ['order' => $i + 1, 'comment' => '']);
        }

        [$error, $text] = $this->tool($token, 'entity_file_attach', ['entity' => 'catalog_product', 'uuid' => (string) $product->uuid, 'content_base64' => base64_encode($this->picture()), 'name' => 'one-more.png']);
        $this->assertTrue($error);
        $this->assertStringContainsString('up to 100', $text);
    }
}
