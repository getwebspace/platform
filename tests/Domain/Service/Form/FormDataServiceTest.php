<?php declare(strict_types=1);

namespace tests\Domain\Service\Form;

use App\Domain\Models\FormData;
use App\Domain\Repository\Form\DataRepository as FormDataRepository;
use App\Domain\Service\Form\DataService;
use App\Domain\Service\Form\Exception\FormDataNotFoundException;
use App\Domain\Service\Form\Exception\MissingMessageValueException;
use tests\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
class FormDataServiceTest extends TestCase
{
    protected DataService $service;

    public function setUp(): void
    {
        parent::setUp();

        $this->service = $this->getService(DataService::class);
    }

    public function testCreateSuccess(): void
    {
        $data = [
            'form_uuid' => $this->getFaker()->uuid,
            'data' => [
                'streetAddress' => $this->getFaker()->streetAddress,
                'secondaryAddress' => $this->getFaker()->secondaryAddress,
            ],
            'message' => $this->getFaker()->text(1000),
        ];

        $formData = $this->service->create($data);
        $this->assertInstanceOf(FormData::class, $formData);
        $this->assertEquals($data['data'], $formData->data);
        $this->assertEquals($data['message'], $formData->message);
    }

    public function testCreateWithMissingMessageValue(): void
    {
        $this->expectException(MissingMessageValueException::class);

        $this->service->create();
    }

    public function testReadSuccess(): void
    {
        $data = [
            'form_uuid' => $this->getFaker()->uuid,
            'data' => [
                'streetAddress' => $this->getFaker()->streetAddress,
                'secondaryAddress' => $this->getFaker()->secondaryAddress,
            ],
            'message' => $this->getFaker()->text(1000),
        ];

        $fd = $this->service->create($data);

        $formData = $this->service->read(['uuid' => $fd->uuid]);
        $this->assertInstanceOf(FormData::class, $formData);
        $this->assertEquals($data['data'], $formData->data);
        $this->assertEquals($data['message'], $formData->message);
    }

    public function testReadWithFormDataNotFound(): void
    {
        $this->expectException(FormDataNotFoundException::class);

        $this->service->read(['uuid' => $this->getFaker()->uuid]);
    }

    public function testUpdate(): void
    {
        $formData = $this->service->create([
            'form_uuid' => $this->getFaker()->uuid,
            'data' => [
                'streetAddress' => $this->getFaker()->streetAddress,
                'secondaryAddress' => $this->getFaker()->secondaryAddress,
            ],
            'message' => $this->getFaker()->text(1000),
        ]);

        $data = [
            'form_uuid' => $this->getFaker()->uuid,
            'data' => [
                'streetAddress' => $this->getFaker()->streetAddress,
                'secondaryAddress' => $this->getFaker()->secondaryAddress,
            ],
            'message' => $this->getFaker()->text(1000),
        ];

        $formData = $this->service->update($formData, $data);
        $this->assertInstanceOf(FormData::class, $formData);
        $this->assertEquals($data['data'], $formData->data);
        $this->assertEquals($data['message'], $formData->message);
    }

    public function testUpdateWithFormDataNotFound(): void
    {
        $this->expectException(FormDataNotFoundException::class);

        $this->service->update(null);
    }

    public function testDeleteSuccess(): void
    {
        $formData = $this->service->create([
            'form_uuid' => $this->getFaker()->uuid,
            'message' => $this->getFaker()->text(1000),
        ]);

        $result = $this->service->delete($formData);

        $this->assertTrue($result);
    }

    public function testDeleteWithFormDataNotFound(): void
    {
        $this->expectException(FormDataNotFoundException::class);

        $this->service->delete(null);
    }
}
