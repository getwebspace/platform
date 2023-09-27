<?php declare(strict_types=1);

namespace tests\Domain\Service\Reference;

use App\Domain\Entities\Reference;
use App\Domain\Repository\ReferenceRepository;
use App\Domain\Service\Reference\Exception\MissingTitleValueException;
use App\Domain\Service\Reference\Exception\MissingTypeValueException;
use App\Domain\Service\Reference\Exception\ReferenceNotFoundException;
use App\Domain\Service\Reference\Exception\TitleAlreadyExistsException;
use App\Domain\Service\Reference\ReferenceService;
use tests\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
class ReferenceServiceTest extends TestCase
{
    protected ReferenceService $service;

    public function setUp(): void
    {
        parent::setUp();

        $this->service = $this->getService(ReferenceService::class);
    }

    public function testCreateSuccess(): void
    {
        $data = [
            'type' => $this->getFaker()->randomElement(\App\Domain\Types\ReferenceTypeType::LIST),
            'title' => $this->getFaker()->word,
            'value' => [
                'value-0' => $this->getFaker()->word,
                'value-1' => $this->getFaker()->word,
                'value-2' => $this->getFaker()->word,
            ],
            'order' => $this->getFaker()->randomDigit(),
            'status' => $this->getFaker()->boolean,
        ];

        $ref = $this->service->create($data);
        $this->assertInstanceOf(Reference::class, $ref);
        $this->assertEquals($data['type'], $ref->getType());
        $this->assertEquals($data['title'], $ref->getTitle());
        $this->assertEquals($data['value'], $ref->getValue());
        $this->assertEquals($data['order'], $ref->getOrder());
        $this->assertEquals($data['status'], $ref->getStatus());

        /** @var ReferenceRepository $taskRepo */
        $taskRepo = $this->em->getRepository(Reference::class);
        $ref = $taskRepo->findOneByUuid($ref->getUuid());
        $this->assertInstanceOf(Reference::class, $ref);
        $this->assertEquals($data['title'], $ref->getTitle());
    }

    public function testCreateWithMissingTypeValue(): void
    {
        $this->expectException(MissingTypeValueException::class);

        $this->service->create([]);
    }

    public function testCreateWithMissingTitleValue(): void
    {
        $this->expectException(MissingTitleValueException::class);

        $this->service->create([
            'type' => $this->getFaker()->randomElement(\App\Domain\Types\ReferenceTypeType::LIST),
        ]);
    }

    public function testCreateWithTitleAlreadyExists(): void
    {
        $this->expectException(TitleAlreadyExistsException::class);

        $random = [
            'type' => $this->getFaker()->randomElement(\App\Domain\Types\ReferenceTypeType::LIST),
            'title' => $this->getFaker()->word,
        ];

        $this->service->create($random);
        $this->service->create($random);
    }

    public function testReadSuccess(): void
    {
        $data = [
            'type' => $this->getFaker()->randomElement(\App\Domain\Types\ReferenceTypeType::LIST),
            'title' => $this->getFaker()->word,
            'value' => [
                'value-0' => $this->getFaker()->word,
                'value-1' => $this->getFaker()->word,
                'value-2' => $this->getFaker()->word,
            ],
        ];

        $ref = $this->service->create($data);

        $ref = $this->service->read(['uuid' => $ref->getUuid()]);
        $this->assertInstanceOf(Reference::class, $ref);
        $this->assertEquals($data['type'], $ref->getType());
        $this->assertEquals($data['title'], $ref->getTitle());
        $this->assertEquals($data['value'], $ref->getValue());
    }

    public function testReadWithEntryNotFound(): void
    {
        $this->expectException(ReferenceNotFoundException::class);

        $this->service->read(['uuid' => $this->getFaker()->uuid]);
    }

    public function testUpdateSuccess(): void
    {
        $ref = $this->service->create([
            'type' => $this->getFaker()->randomElement(\App\Domain\Types\ReferenceTypeType::LIST),
            'title' => $this->getFaker()->word,
            'value' => [
                'value-0' => $this->getFaker()->word,
                'value-1' => $this->getFaker()->word,
                'value-2' => $this->getFaker()->word,
            ],
            'order' => $this->getFaker()->randomDigit(),
            'status' => $this->getFaker()->boolean,
        ]);

        $data = [
            'type' => $this->getFaker()->randomElement(\App\Domain\Types\ReferenceTypeType::LIST),
            'title' => $this->getFaker()->word,
            'value' => [
                'value-0' => $this->getFaker()->word,
                'value-1' => $this->getFaker()->word,
                'value-2' => $this->getFaker()->word,
            ],
            'order' => $this->getFaker()->randomDigit(),
            'status' => $this->getFaker()->boolean,
        ];

        $ref = $this->service->update($ref, $data);
        $this->assertEquals($data['type'], $ref->getType());
        $this->assertEquals($data['title'], $ref->getTitle());
        $this->assertEquals($data['value'], $ref->getValue());
        $this->assertEquals($data['order'], $ref->getOrder());
        $this->assertEquals($data['status'], $ref->getStatus());
    }

    public function testUpdateWithTaskNotFound(): void
    {
        $this->expectException(ReferenceNotFoundException::class);

        $this->service->update(null);
    }

    public function testDeleteSuccess(): void
    {
        $ref = $this->service->create([
            'type' => $this->getFaker()->randomElement(\App\Domain\Types\ReferenceTypeType::LIST),
            'title' => $this->getFaker()->word,
        ]);

        $result = $this->service->delete($ref);

        $this->assertTrue($result);
    }

    public function testDeleteWithTaskNotFound(): void
    {
        $this->expectException(ReferenceNotFoundException::class);

        $this->service->delete(null);
    }
}
