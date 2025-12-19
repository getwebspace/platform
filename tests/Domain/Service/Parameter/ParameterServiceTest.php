<?php declare(strict_types=1);

namespace tests\Domain\Service\Parameter;

use App\Domain\Models\Parameter;
use App\Domain\Service\Parameter\Exception\ParameterAlreadyExistsException;
use App\Domain\Service\Parameter\Exception\ParameterNotFoundException;
use App\Domain\Service\Parameter\ParameterService;
use tests\TestCase;

/**
 * @internal
 *
 * #[CoversNothing]
 */
class ParameterServiceTest extends TestCase
{
    protected ParameterService $service;

    public function setUp(): void
    {
        parent::setUp();

        $this->service = $this->getService(ParameterService::class);
    }

    public function testCreateSuccess(): void
    {
        $data = [
            'name' => implode('_', $this->getFaker()->words(2)),
            'value' => $this->getFaker()->text(10),
        ];

        $parameter = $this->service->create($data);
        $this->assertInstanceOf(Parameter::class, $parameter);
        $this->assertEquals($data['name'], $parameter->name);
        $this->assertEquals($data['value'], $parameter->value);
    }

    public function testCreateWithParameterAlreadyExistent(): void
    {
        $this->expectException(ParameterAlreadyExistsException::class);

        $data = [
            'name' => implode('_', $this->getFaker()->words(2)),
            'value' => $this->getFaker()->text(128),
        ];

        Parameter::create($data);

        $this->service->create($data);
    }

    public function testReadSuccess(): void
    {
        $data = [
            'name' => implode('_', $this->getFaker()->words(2)),
            'value' => $this->getFaker()->text(128),
        ];

        $this->service->create($data);

        $parameter = $this->service->read(['name' => $data['name']]);
        $this->assertInstanceOf(Parameter::class, $parameter);
        $this->assertEquals($data['name'], $parameter->name);
        $this->assertEquals($data['value'], $parameter->value);
    }

    public function testReadWithDefault(): void
    {
        $data = [
            'name' => implode('_', $this->getFaker()->words(2)),
            'default' => $this->getFaker()->text(128),
        ];

        $parameter = $this->service->read(['name' => $data['name']], $data['default']);
        $this->assertInstanceOf(Parameter::class, $parameter);
        $this->assertEquals($data['name'], $parameter->name);
        $this->assertEquals($data['default'], $parameter->value);
    }

    public function testUpdateSuccess(): void
    {
        $parameter = $this->service->create([
            'name' => implode('_', $this->getFaker()->words(2)),
            'value' => $this->getFaker()->text(128),
        ]);

        $data = [
            'value' => $this->getFaker()->text(128),
        ];

        $parameter = $this->service->update($parameter, $data);
        $this->assertEquals($data['value'], $parameter->value);
    }

    public function testUpdateWithPageNotFound(): void
    {
        $this->expectException(ParameterNotFoundException::class);

        $this->service->update(null);
    }

    public function testDeleteSuccess(): void
    {
        $parameter = $this->service->create([
            'name' => implode('_', $this->getFaker()->words(2)),
            'value' => $this->getFaker()->text(128),
        ]);

        $result = $this->service->delete($parameter);

        $this->assertTrue($result);
    }

    public function testDeleteWithParameterNotFound(): void
    {
        $this->expectException(ParameterNotFoundException::class);

        $this->service->delete(null);
    }
}
