<?php declare(strict_types=1);

namespace tests\Domain\Service\File;

use App\Domain\Entities\File;
use App\Domain\Service\File\Exception\FileAlreadyExistsException;
use App\Domain\Service\File\Exception\FileNotFoundException;
use App\Domain\Service\File\FileService;
use Doctrine\ORM\EntityManager;
use tests\TestCase;

class FileServiceTest extends TestCase
{
    /**
     * @var EntityManager
     */
    protected $em;

    /**
     * @var FileService
     */
    protected $service;

    public function setUp(): void
    {
        parent::setUp();
        $this->em = $this->getEntityManager();
        $this->service = new FileService(null, $this->em, null);
    }

    protected function getTestFileUrl()
    {
        return 'https://loremflickr.com/1024/768?t=' . time();
    }

    protected function getTestFile()
    {
        $from = $this->getTestFileUrl();
        $to = CACHE_DIR . '/' . md5('tmp' . time()) . '.jpg';

        file_put_contents($to, file_get_contents($from));

        return $to;
    }

    public function testCreateSuccessFromLocal(): void
    {
        $path = $this->getTestFile();
        $info = File::info($path);

        $file = $this->service->createFromPath($path);
        $this->assertInstanceOf(File::class, $file);
        $this->assertSame($info['name'], $file->getName());
        $this->assertSame($info['ext'], $file->getExt());
        $this->assertSame($info['type'], $file->getType());
        $this->assertSame($info['size'], $file->getSize());
        $this->assertTrue(file_exists($file->getInternalPath()));
        $this->assertTrue(!file_exists($path));
    }

    public function testCreateSuccessFromRemote(): void
    {
        $path = $this->getTestFileUrl();

        $file = $this->service->createFromPath($path);
        $this->assertInstanceOf(File::class, $file);
        $this->assertTrue(file_exists($file->getInternalPath()));
    }

    public function testCreateWithFileAlreadyExistent(): void
    {
        $this->expectException(FileAlreadyExistsException::class);

        $file = $this->service->createFromPath($this->getTestFile());

        // create new file obj from previously processed file
        $this->service->createFromPath($file->getInternalPath());
    }

    public function testReadSuccess(): void
    {
        $file = $this->service->createFromPath($this->getTestFile());

        $f1 = $this->service->read(['uuid' => $file->getUuid()]);
        $this->assertInstanceOf(File::class, $f1);
        $this->assertSame($file->getFileName(), $f1->getFileName());

        $f2 = $this->service->read(['hash' => $file->getHash()]);
        $this->assertInstanceOf(File::class, $f2);
        $this->assertSame($file->getFileName(), $f2->getFileName());
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
        $this->assertSame($data['name'], $page->getName());
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
