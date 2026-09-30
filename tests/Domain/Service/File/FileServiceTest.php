<?php declare(strict_types=1);

namespace tests\Domain\Service\File;

use App\Domain\Models\File;
use App\Domain\Service\File\Exception\FileNotFoundException;
use App\Domain\Service\File\FileService;
use tests\TestCase;

/**
 * @internal
 *
 * #[CoversNothing]
 */
class FileServiceTest extends TestCase
{
    protected FileService $service;

    public function setUp(): void
    {
        parent::setUp();

        $this->service = $this->getService(FileService::class);
    }

    /**
     * Files published to the web root for the "remote" test
     */
    private array $published = [];

    public function tearDown(): void
    {
        foreach ($this->published as $path) {
            @unlink($path);
        }

        parent::tearDown();
    }

    /**
     * The image is served by the app itself (same host as API tests use),
     * so the remote download path is tested without depending on the internet
     */
    protected function getTestFileUrl()
    {
        $name = 'test-' . uniqid() . '.jpg';

        $this->published[] = $path = PUBLIC_DIR . '/' . $name;
        $this->createTestImage($path);

        return 'http://127.0.0.1:80/' . $name . '?t=' . time();
    }

    protected function getTestFile()
    {
        $path = CACHE_DIR . '/' . md5('tmp' . uniqid()) . '.jpg';

        $this->createTestImage($path);

        return $path;
    }

    /**
     * Unique JPEG, so files never collide by hash
     */
    private function createTestImage(string $path): void
    {
        $image = imagecreatetruecolor(300, 400);
        imagefill($image, 0, 0, imagecolorallocate($image, random_int(0, 255), random_int(0, 255), random_int(0, 255)));
        imagestring($image, 5, 10, 10, uniqid('', true), imagecolorallocate($image, 255, 255, 255));
        imagejpeg($image, $path);
    }

    public function testCreateSuccessFromLocal(): void
    {
        $path = $this->getTestFile();
        $info = File::info($path);

        $file = $this->service->createFromPath($path);
        $this->assertInstanceOf(File::class, $file);
        $this->assertEquals($info['name'], $file->name);
        $this->assertEquals($info['ext'], $file->ext);
        $this->assertEquals($info['type'], $file->type);
        $this->assertEquals($info['size'], $file->size);
        $this->assertTrue(file_exists($file->internal_path()));
        $this->assertTrue(!file_exists($path));
    }

    public function testCreateSuccessFromRemote(): void
    {
        $path = $this->getTestFileUrl();

        $file = $this->service->createFromPath($path);
        $this->assertInstanceOf(File::class, $file);
        $this->assertTrue(file_exists($file->internal_path()));
    }

    public function testCreateWithFileAlreadyExistent(): void
    {
        $file1 = $this->service->createFromPath($this->getTestFile());

        // create new file obj from previously processed file
        $file2 = $this->service->createFromPath($file1->internal_path());

        $this->assertInstanceOf(File::class, $file2);
        $this->assertEquals($file1->attributesToArray(), $file2->attributesToArray());
    }

    public function testReadSuccess(): void
    {
        $file = $this->service->createFromPath($this->getTestFile());

        $f1 = $this->service->read(['uuid' => $file->uuid]);
        $this->assertInstanceOf(File::class, $f1);
        $this->assertEquals($file->filename(), $f1->filename());

        $f2 = $this->service->read(['hash' => $file->hash]);
        $this->assertInstanceOf(File::class, $f2);
        $this->assertEquals($file->filename(), $f2->filename());
    }

    public function testReadWithFileNotFound(): void
    {
        $this->expectException(FileNotFoundException::class);

        $this->service->read(['hash' => md5('test' . time())]);
    }

    public function testUpdateSuccess(): void
    {
        $file = $this->service->createFromPath($this->getTestFile());

        $data = [
            'name' => $this->getFaker()->userName,
        ];

        $page = $this->service->update($file, $data);
        $this->assertEquals($data['name'], $page->name);
    }

    public function testUpdateWithFileNotFound(): void
    {
        $this->expectException(FileNotFoundException::class);

        $this->service->update(null);
    }

    public function testDeleteSuccess(): void
    {
        $file = $this->service->createFromPath($this->getTestFile());

        $result = $this->service->delete($file);

        $this->assertTrue($result);
    }

    public function testDeleteWithFileNotFound(): void
    {
        $this->expectException(FileNotFoundException::class);

        $this->service->delete(null);
    }
}
