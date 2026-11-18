<?php declare(strict_types=1);

namespace tests\Domain\Tasks;

use App\Application\PubSub;
use App\Domain\Casts\Task\Status;
use App\Domain\Models\File;
use App\Domain\Models\Task;
use App\Domain\Service\File\FileService;
use App\Domain\Service\Parameter\ParameterService;
use App\Domain\Tasks\ConvertImageTask;
use App\Domain\Tasks\ReConvertImageTask;
use tests\Concerns\RunsTasks;
use tests\TestCase;

/**
 * @internal
 *
 * #[CoversNothing]
 */
class ImageTasksTest extends TestCase
{
    use RunsTasks;

    /** @var File[] */
    private array $files = [];

    public function tearDown(): void
    {
        foreach ($this->files as $file) {
            try {
                $this->getService(FileService::class)->delete($file);
            } catch (\Throwable) {
                // already gone
            }
        }
        $this->files = [];

        parent::tearDown();
    }

    private function enable(array $extra = []): void
    {
        $parameters = $this->getService(ParameterService::class);

        foreach ($extra + ['image_enable' => 'yes', 'image_convert_min_size' => '1'] as $name => $value) {
            $parameters->create(['name' => $name, 'value' => $value]);
        }
    }

    private function makeImage(string $type = 'jpeg', int $width = 1200, int $height = 800): File
    {
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, imagecolorallocate($image, random_int(0, 255), random_int(0, 255), random_int(0, 255)));
        imagestring($image, 5, 10, 10, uniqid('', true), imagecolorallocate($image, 255, 255, 255));

        $path = CACHE_DIR . '/' . md5(uniqid('', true)) . '.' . ($type === 'jpeg' ? 'jpg' : $type);
        match ($type) {
            'jpeg' => imagejpeg($image, $path),
            'png' => imagepng($image, $path),
            'gif' => imagegif($image, $path),
            'webp' => imagewebp($image, $path),
            'bmp' => imagebmp($image, $path),
        };

        return $this->files[] = $this->getService(FileService::class)->createFromPath($path);
    }

    private function makeTextFile(): File
    {
        $path = CACHE_DIR . '/' . md5(uniqid('', true)) . '.txt';
        file_put_contents($path, 'just words ' . uniqid());

        return $this->files[] = $this->getService(FileService::class)->createFromPath($path);
    }

    public function testNothingIsConvertedWhileImagesAreSwitchedOff(): void
    {
        $file = $this->makeImage();

        $task = $this->runTask(ConvertImageTask::class, ['uuid' => [$file->uuid]]);

        $this->assertEquals(Status::CANCEL, $task->status);
        $this->assertStringStartsWith('image/jpeg', File::find($file->uuid)->type);
    }

    public function testAPictureIsConvertedToWebpInThreeSizes(): void
    {
        $this->enable();
        $file = $this->makeImage('jpeg', 1200, 800);

        $task = $this->runTask(ConvertImageTask::class, ['uuid' => [$file->uuid]]);

        $this->assertEquals(Status::DONE, $task->status);

        $fresh = File::find($file->uuid);
        $this->assertEquals('webp', $fresh->ext);
        $this->assertStringStartsWith('image/webp', $fresh->type);

        $dir = $fresh->dir();
        $this->assertFileExists($dir . '/' . $fresh->name . '.orig', 'the original is kept');
        $this->assertFileExists($dir . '/' . $fresh->name . '.webp');

        // the height is what is limited, the width follows the ratio
        foreach (['big' => 960, 'middle' => 450, 'small' => 200] as $folder => $height) {
            $path = $dir . '/' . $folder . '/' . $fresh->name . '.webp';
            $this->assertFileExists($path, $folder);
            $this->assertSame($height, getimagesize($path)[1], $folder);
            $this->assertSame((int) (1200 / (800 / $height)), getimagesize($path)[0], $folder);
        }

        $this->assertGreaterThan($file->size, $fresh->size, 'the size counts the original and every copy');
    }

    public function testASizeSwitchedOffIsNotMade(): void
    {
        $this->enable(['image_convert_size_middle' => '0']);
        $file = $this->makeImage();

        $this->runTask(ConvertImageTask::class, ['uuid' => [$file->uuid]]);

        $dir = File::find($file->uuid)->dir();
        $this->assertDirectoryExists($dir . '/big');
        $this->assertDirectoryExists($dir . '/small');
        $this->assertDirectoryDoesNotExist($dir . '/middle');
    }

    public function testEveryCommonFormatCanBeConverted(): void
    {
        $this->enable();
        $files = [];
        foreach (['png', 'gif', 'webp', 'bmp'] as $type) {
            $files[$type] = $this->makeImage($type, 600, 400);
        }

        $task = $this->runTask(ConvertImageTask::class, ['uuid' => array_map(fn (File $f) => $f->uuid, array_values($files))]);

        $this->assertEquals(Status::DONE, $task->status);
        foreach ($files as $type => $file) {
            $this->assertStringStartsWith('image/webp', File::find($file->uuid)->type, $type);
        }
    }

    public function testSmallPicturesAreLeftAlone(): void
    {
        $this->enable(['image_convert_min_size' => '100000000']);
        $file = $this->makeImage();

        $task = $this->runTask(ConvertImageTask::class, ['uuid' => [$file->uuid]]);

        $this->assertEquals(Status::DONE, $task->status);
        $this->assertStringStartsWith('image/jpeg', File::find($file->uuid)->type);
        $this->assertFileDoesNotExist($file->dir() . '/' . $file->name . '.orig');
    }

    public function testOtherFilesAreSkippedAndAMissingOneDoesNotStopTheRest(): void
    {
        $this->enable();
        $text = $this->makeTextFile();
        $image = $this->makeImage();

        $task = $this->runTask(ConvertImageTask::class, [
            'uuid' => [\Ramsey\Uuid\Uuid::uuid4()->toString(), $text->uuid, $image->uuid],
        ]);

        $this->assertEquals(Status::DONE, $task->status);
        $this->assertStringStartsWith('text/plain', File::find($text->uuid)->type);
        $this->assertStringStartsWith('image/webp', File::find($image->uuid)->type);
    }

    public function testConvertingAnnouncesItself(): void
    {
        $this->enable();
        $announced = 0;
        $this->getService(PubSub::class)->subscribe('task:file:convert', function () use (&$announced): void {
            ++$announced;
        });

        $this->runTask(ConvertImageTask::class, ['uuid' => [$this->makeImage()->uuid]]);

        $this->assertSame(1, $announced);
    }

    public function testReprocessingDoesNothingWhileImagesAreSwitchedOff(): void
    {
        $this->makeImage();

        $task = $this->runTask(ReConvertImageTask::class);

        $this->assertEquals(Status::CANCEL, $task->status);
        $this->assertSame(0, Task::where('action', ConvertImageTask::class)->count());
    }

    public function testReprocessingQueuesAConversionOfEveryPictureOnly(): void
    {
        $this->enable();
        $first = $this->makeImage();
        $second = $this->makeImage('png');
        $this->makeTextFile();

        $task = $this->runTask(ReConvertImageTask::class);

        $this->assertEquals(Status::DONE, $task->status);

        $queued = Task::where('action', ConvertImageTask::class)->get();
        $this->assertCount(1, $queued);
        $this->assertEquals(Status::QUEUE, $queued->first()->status);
        $this->assertEqualsCanonicalizing([$first->uuid, $second->uuid], (array) $queued->first()->params['uuid']);
    }
}
