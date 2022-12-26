<?php declare(strict_types=1);

namespace tests\Domain\Service\Catalog;

use App\Domain\Entities\Catalog\Measure;
use App\Domain\Repository\Catalog\MeasureRepository;
use App\Domain\Service\Catalog\Exception\MeasureNotFoundException;
use App\Domain\Service\Catalog\Exception\MissingTitleValueException;
use App\Domain\Service\Catalog\Exception\TitleAlreadyExistsException;
use App\Domain\Service\Catalog\MeasureService;
use tests\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
class MeasureServiceTest extends TestCase
{
    protected MeasureService $service;

    public function setUp(): void
    {
        parent::setUp();

        $this->service = $this->getService(MeasureService::class);
    }

    public function testCreateSuccess(): void
    {
        $data = [
            'title' => $this->getFaker()->title,
            'contraction' => $this->getFaker()->word,
            'value' => $this->getFaker()->randomFloat(),
        ];

        $measure = $this->service->create($data);
        $this->assertInstanceOf(Measure::class, $measure);
        $this->assertSame($data['title'], $measure->getTitle());
        $this->assertSame($data['contraction'], $measure->getContraction());
        $this->assertSame($data['value'], $measure->getValue());

        /** @var MeasureRepository $measureRepo */
        $measureRepo = $this->em->getRepository(Measure::class);
        $m = $measureRepo->findOneByTitle($data['title']);
        $this->assertInstanceOf(Measure::class, $m);
        $this->assertSame($data['title'], $m->getTitle());
        $this->assertSame($data['contraction'], $m->getContraction());
        $this->assertSame($data['value'], $m->getValue());
    }

    public function testCreateWithMissingTitleValue(): void
    {
        $this->expectException(MissingTitleValueException::class);

        $this->service->create();
    }

    public function testCreateWithTitleExistent(): void
    {
        $this->expectException(TitleAlreadyExistsException::class);

        $data = [
            'title' => $this->getFaker()->title,
        ];

        $measure = (new Measure())->setTitle($data['title']);

        $this->em->persist($measure);
        $this->em->flush();

        $this->service->create($data);
    }

    public function testReadSuccess(): void
    {
        $data = [
            'title' => $this->getFaker()->title,
            'contraction' => $this->getFaker()->word,
            'value' => $this->getFaker()->randomFloat(),
        ];

        $this->service->create($data);

        $measure = $this->service->read(['title' => $data['title']]);
        $this->assertInstanceOf(Measure::class, $measure);
        $this->assertSame($data['title'], $measure->getTitle());
        $this->assertSame($data['contraction'], $measure->getContraction());
        $this->assertSame($data['value'], $measure->getValue());
    }

    public function testReadWithProductNotFound(): void
    {
        $this->expectException(MeasureNotFoundException::class);

        $this->service->read(['title' => $this->getFaker()->title]);
    }

    public function testUpdate(): void
    {
        $measure = $this->service->create([
            'title' => $this->getFaker()->title,
            'contraction' => $this->getFaker()->word,
            'value' => $this->getFaker()->randomFloat(),
        ]);

        $data = [
            'title' => $this->getFaker()->title,
            'contraction' => $this->getFaker()->word,
            'value' => $this->getFaker()->randomFloat(),
        ];

        $measure = $this->service->update($measure, $data);
        $this->assertInstanceOf(Measure::class, $measure);
        $this->assertSame($data['title'], $measure->getTitle());
        $this->assertSame($data['contraction'], $measure->getContraction());
        $this->assertSame($data['value'], $measure->getValue());
    }

    public function testUpdateWithProductNotFound(): void
    {
        $this->expectException(MeasureNotFoundException::class);

        $this->service->update(null);
    }

    public function testDeleteSuccess(): void
    {
        $measure = $this->service->create([
            'title' => $this->getFaker()->title,
            'contraction' => $this->getFaker()->word,
            'value' => $this->getFaker()->randomFloat(),
        ]);

        $result = $this->service->delete($measure);

        $this->assertTrue($result);
    }

    public function testDeleteWithProductNotFound(): void
    {
        $this->expectException(MeasureNotFoundException::class);

        $this->service->delete(null);
    }
}
