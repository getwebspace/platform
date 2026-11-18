<?php declare(strict_types=1);

namespace tests\Cup;

use App\Domain\Models\File;
use App\Domain\Service\File\FileService;
use App\Domain\Service\Page\PageService;
use App\Domain\Service\Parameter\ParameterService;

/**
 * The library of files and the picture manager of the text editor
 *
 * @internal
 *
 * #[CoversNothing]
 */
class CupFilesTest extends CupTestCase
{
    /** @var string[] things made outside the library, removed afterwards */
    private array $leftovers = [];

    public function tearDown(): void
    {
        foreach ($this->leftovers as $path) {
            @unlink($path);
        }
        $this->leftovers = [];

        parent::tearDown();
    }

    private function text(string $name = 'notes.txt'): File
    {
        $this->admin();
        $path = CACHE_DIR . '/' . md5(uniqid('', true)) . '.txt';
        file_put_contents($path, 'words ' . uniqid());

        return $this->cupFiles[] = $this->getService(FileService::class)->createFromPath($path, $name);
    }

    private function picture(string $name = 'photo.jpg'): File
    {
        $this->admin();
        $path = CACHE_DIR . '/' . md5(uniqid('', true)) . '.jpg';
        $image = imagecreatetruecolor(40, 30);
        imagefill($image, 0, 0, imagecolorallocate($image, random_int(0, 255), random_int(0, 255), random_int(0, 255)));
        imagestring($image, 2, 2, 2, uniqid(), imagecolorallocate($image, 255, 255, 255));
        imagejpeg($image, $path);

        return $this->cupFiles[] = $this->getService(FileService::class)->createFromPath($path, $name);
    }

    // ---------------------------------------------------------------------- list

    public function testTheLibraryListsTheFiles(): void
    {
        $file = $this->text('Quarterly report.txt');

        $response = $this->cup('GET', '/cup/file');

        $this->assertRenders($response);
        $this->assertStringContainsString($file->filename(), (string) $response->getBody());
        $this->assertStringContainsString($file->public_path(), (string) $response->getBody(), 'with the link to it');
    }

    public function testAnEmptyLibraryOpens(): void
    {
        $this->admin();

        $this->assertRenders($this->cup('GET', '/cup/file'));
    }

    // -------------------------------------------------------------------- delete

    public function testDeletingAFileRemovesTheRowTheFolderAndTheCopies(): void
    {
        $file = $this->picture();
        @mkdir($file->dir('small'), 0o777, true);
        file_put_contents($file->dir('small') . '/' . $file->filename(), 'thumbnail');
        $other = $this->text();

        $response = $this->cup('POST', "/cup/file/{$file->uuid}/delete");

        $this->assertRedirectsTo($response, '/cup/file');
        $this->assertNull(File::find($file->uuid));
        $this->assertDirectoryDoesNotExist($file->dir());
        $this->assertFileExists($other->internal_path(), 'the other files stay');
        $this->assertNotNull(File::find($other->uuid));
    }

    public function testDeletingAFileDetachesItFromWhatUsesIt(): void
    {
        $file = $this->text();
        $page = $this->getService(PageService::class)->create(['title' => 'Uses it', 'address' => 'uses-it', 'content' => 'x']);
        $page->files()->sync([$file->uuid => ['comment' => '', 'order' => 1]]);
        $this->assertCount(1, $page->files()->get());

        $this->cup('POST', "/cup/file/{$file->uuid}/delete");

        $this->assertCount(0, \App\Domain\Models\Page::find($page->uuid)->files()->get());
        $this->assertSame(0, $this->getService(\Illuminate\Database\Connection::class)->table('file_related')->where('file_uuid', $file->uuid)->count());
    }

    public function testDeletingAnUnknownFileChangesNothing(): void
    {
        $file = $this->text();

        foreach ([\Ramsey\Uuid\Uuid::uuid4()->toString(), 'not-a-uuid'] as $uuid) {
            $this->assertRedirectsTo($this->cup('POST', "/cup/file/{$uuid}/delete"), '/cup/file', $uuid);
        }

        $this->assertFileExists($file->internal_path());
    }

    // ------------------------------------------------- a row that is not a real file

    public function testARowWithoutAFolderNameDoesNotTakeTheWholeLibraryWithIt(): void
    {
        $keep = $this->text();
        $row = File::create(['name' => 'ghost', 'ext' => 'txt', 'type' => 'text/plain', 'size' => 1, 'salt' => '', 'hash' => sha1(uniqid())]);

        $this->cup('POST', "/cup/file/{$row->uuid}/delete");

        $this->assertNull(File::find($row->uuid));
        $this->assertFileExists($keep->internal_path(), 'the folder of an empty name is the folder of every upload');
    }

    public function testAFolderNameThatIsNotATokenIsNeverUsedAsAPath(): void
    {
        $keep = $this->text();
        $marker = sys_get_temp_dir() . '/zz-injected-' . bin2hex(random_bytes(4));
        $this->leftovers[] = $marker;

        foreach (['x; touch ' . $marker, 'x && touch ' . $marker, '$(touch ' . $marker . ')', '`touch ' . $marker . '`', '../' . basename(UPLOAD_DIR), '..'] as $salt) {
            $row = File::create(['name' => 'ghost', 'ext' => 'txt', 'type' => 'text/plain', 'size' => 1, 'salt' => $salt, 'hash' => sha1(uniqid())]);

            $this->cup('POST', "/cup/file/{$row->uuid}/delete");

            $this->assertNull(File::find($row->uuid), $salt);
        }

        $this->assertFileDoesNotExist($marker, 'a salt is not a command');
        $this->assertFileExists($keep->internal_path());
    }

    public function testTheApiCannotMakeADeleteRunACommand(): void
    {
        $this->getService(ParameterService::class)->create(['name' => 'entity_access', 'value' => 'key']);
        $key = $this->createApiKeyToken();
        $marker = sys_get_temp_dir() . '/zz-api-injected-' . bin2hex(random_bytes(4));
        $this->leftovers[] = $marker;
        $keep = $this->text();

        $created = $this->http('POST', '/api/v1/file', ['headers' => ['key' => $key], 'json' => [
            'name' => 'ghost', 'ext' => 'txt', 'type' => 'text/plain', 'size' => 1, 'salt' => 'x; touch ' . $marker, 'hash' => sha1(uniqid()),
        ]]);
        $this->assertSame(201, $created->getStatusCode(), $this->why($created));
        $uuid = $this->json($created)['data']['uuid'];

        $deleted = $this->http('DELETE', '/api/v1/file?uuid=' . $uuid, ['headers' => ['key' => $key]]);

        $this->assertSame(410, $deleted->getStatusCode(), $this->why($deleted));
        $this->assertFileDoesNotExist($marker);
        $this->assertFileExists($keep->internal_path());
    }

    // --------------------------------------------------------------- picture api

    public function testThePictureManagerListsPicturesOnly(): void
    {
        $picture = $this->picture();
        $this->text();

        $response = $this->cup('GET', '/cup/file/image');

        $this->assertSame(200, $response->getStatusCode());
        $list = $this->json($response);
        $this->assertCount(1, $list);
        $this->assertSame($picture->public_path(), $list[0]['url']);
        $this->assertArrayHasKey('thumb', $list[0]);
    }

    public function testAPictureIsDeletedByItsAddress(): void
    {
        $picture = $this->picture();
        $other = $this->picture('other.jpg');

        $response = $this->cup('POST', '/cup/file/image/delete', ['form_params' => ['src' => $picture->public_path()]]);

        $this->assertSame(['status' => 'ok'], $this->json($response));
        $this->assertNull(File::find($picture->uuid));
        $this->assertFileDoesNotExist($picture->internal_path());
        $this->assertNotNull(File::find($other->uuid));
    }

    public function testTwoPicturesWithTheSameNameAreToldApartByTheirFolder(): void
    {
        $first = $this->picture('same.jpg');
        $second = $this->picture('same.jpg');
        $this->assertNotSame($first->salt, $second->salt);

        $this->cup('POST', '/cup/file/image/delete', ['form_params' => ['src' => $second->public_path()]]);

        $this->assertNotNull(File::find($first->uuid), 'the other picture of that name is not the one that was meant');
        $this->assertNull(File::find($second->uuid));
        $this->assertFileExists($first->internal_path());
    }

    public function testAnAddressOfACopyDeletesThePictureItBelongsTo(): void
    {
        $picture = $this->picture();
        @mkdir($picture->dir() . '/small', 0o777, true);
        file_put_contents($picture->dir() . '/small/' . $picture->filename(), 'thumbnail');

        $response = $this->cup('POST', '/cup/file/image/delete', ['form_params' => ['src' => '/uploads/' . $picture->salt . '/small/' . $picture->filename()]]);

        $this->assertSame(['status' => 'ok'], $this->json($response));
        $this->assertNull(File::find($picture->uuid));
    }

    public function testThePictureManagerDoesNotDeleteDocuments(): void
    {
        $document = $this->text('contract.txt');

        $response = $this->cup('POST', '/cup/file/image/delete', ['form_params' => ['src' => $document->public_path()]]);

        $this->assertSame(['status' => 'not found'], $this->json($response));
        $this->assertNotNull(File::find($document->uuid));
        $this->assertFileExists($document->internal_path());
    }

    public function testAddressesThatAreNotAPictureOfTheLibraryDeleteNothing(): void
    {
        $picture = $this->picture();

        foreach (['', 'photo', '/uploads/', '/uploads/zzzz/nothing.jpg', '/etc/passwd', '../../etc/passwd', 'http://evil.example/uploads/x/y.jpg', '/uploads/' . $picture->salt . '/../other/' . $picture->filename(), "/uploads/{$picture->salt}/"] as $src) {
            $response = $this->cup('POST', '/cup/file/image/delete', ['form_params' => ['src' => $src]]);

            $this->assertSame(200, $response->getStatusCode(), $src . ' ' . $this->why($response));
            $this->assertSame(['status' => 'not found'], $this->json($response), $src);
        }

        $this->assertNotNull(File::find($picture->uuid));
        $this->assertFileExists('/etc/passwd');
    }

    public function testThePictureManagerNeedsTheToken(): void
    {
        $picture = $this->picture();

        $response = $this->cup('POST', '/cup/file/image/delete', ['csrf' => false, 'form_params' => ['src' => $picture->public_path()]]);

        $this->assertSame(403, $response->getStatusCode());
        $this->assertNotNull(File::find($picture->uuid));
    }
}
