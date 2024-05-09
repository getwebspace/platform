<?php declare(strict_types=1);

namespace tests\Domain\Service\Reference;

use App\Domain\Models\Reference;
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
            'type' => $this->getFaker()->randomElement(\App\Domain\Casts\Reference\Type::LIST),
            'title' => implode(' ', $this->getFaker()->words(3)),
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
        $this->assertEquals($data['type'], $ref->type);
        $this->assertEquals($data['title'], $ref->title);
        $this->assertEquals($data['value'], $ref->value);
        $this->assertEquals($data['order'], $ref->order);
        $this->assertEquals($data['status'], $ref->status);
    }

    public function testCreateWithMissingTypeValue(): void
    {
        $this->expectException(MissingTypeValueException::class);

        $this->service->create([
            'title' => implode(' ', $this->getFaker()->words(3)),
        ]);
    }

    public function testCreateWithMissingTitleValue(): void
    {
        $this->expectException(MissingTitleValueException::class);

        $this->service->create([
            'type' => $this->getFaker()->randomElement(\App\Domain\Casts\Reference\Type::LIST),
        ]);
    }

    public function testCreateWithTitleAlreadyExists(): void
    {
        $this->expectException(TitleAlreadyExistsException::class);

        $random = [
            'type' => $this->getFaker()->randomElement(\App\Domain\Casts\Reference\Type::LIST),
            'title' => implode(' ', $this->getFaker()->words(3)),
        ];

        $this->service->create($random);
        $this->service->create($random);
    }

    public function testReadSuccess(): void
    {
        $data = [
            'type' => $this->getFaker()->randomElement(\App\Domain\Casts\Reference\Type::LIST),
            'title' => implode(' ', $this->getFaker()->words(3)),
            'value' => [
                'value-0' => $this->getFaker()->word,
                'value-1' => $this->getFaker()->word,
                'value-2' => $this->getFaker()->word,
            ],
        ];

        $ref = $this->service->create($data);

        $ref = $this->service->read(['uuid' => $ref->uuid]);
        $this->assertInstanceOf(Reference::class, $ref);
        $this->assertEquals($data['type'], $ref->type);
        $this->assertEquals($data['title'], $ref->title);
        $this->assertEquals($data['value'], $ref->value);
    }

    public function testReadWithEntryNotFound(): void
    {
        $this->expectException(ReferenceNotFoundException::class);

        $this->service->read(['uuid' => $this->getFaker()->uuid]);
    }

    public function testUpdateSuccess(): void
    {
        $ref = $this->service->create([
            'type' => $this->getFaker()->randomElement(\App\Domain\Casts\Reference\Type::LIST),
            'title' => implode(' ', $this->getFaker()->words(3)),
            'value' => [
                'value-0' => $this->getFaker()->word,
                'value-1' => $this->getFaker()->word,
                'value-2' => $this->getFaker()->word,
            ],
            'order' => $this->getFaker()->randomDigit(),
            'status' => $this->getFaker()->boolean,
        ]);

        $data = [
            'type' => $this->getFaker()->randomElement(\App\Domain\Casts\Reference\Type::LIST),
            'title' => implode(' ', $this->getFaker()->words(3)),
            'value' => [
                'value-0' => $this->getFaker()->word,
                'value-1' => $this->getFaker()->word,
                'value-2' => $this->getFaker()->word,
            ],
            'order' => $this->getFaker()->randomDigit(),
            'status' => $this->getFaker()->boolean,
        ];

        $ref = $this->service->update($ref, $data);
        $this->assertEquals($data['type'], $ref->type);
        $this->assertEquals($data['title'], $ref->title);
        $this->assertEquals($data['value'], $ref->value);
        $this->assertEquals($data['order'], $ref->order);
        $this->assertEquals($data['status'], $ref->status);
    }

    public function testUpdateWithTaskNotFound(): void
    {
        $this->expectException(ReferenceNotFoundException::class);

        $this->service->update(null);
    }

    public function testDeleteSuccess(): void
    {
        $ref = $this->service->create([
            'type' => $this->getFaker()->randomElement(\App\Domain\Casts\Reference\Type::LIST),
            'title' => implode(' ', $this->getFaker()->words(3)),
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
