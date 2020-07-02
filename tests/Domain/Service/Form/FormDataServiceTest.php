<?php declare(strict_types=1);

namespace tests\Domain\Service\Form;

use App\Domain\Entities\Form\Data as FromData;
use App\Domain\Repository\Form\DataRepository as FromDataRepository;
use App\Domain\Service\Form\Exception\FormDataNotFoundException;
use App\Domain\Service\Form\Exception\MissingMessageValueException;
use App\Domain\Service\Form\DataService;
use Doctrine\ORM\EntityManager;
use tests\TestCase;

class FormDataServiceTest extends TestCase
{
    /**
     * @var EntityManager
     */
    protected $em;

    /**
     * @var DataService
     */
    protected $service;

    public function setUp(): void
    {
        parent::setUp();
        $this->em = $this->getEntityManager();
        $this->service = DataService::getWithEntityManager($this->em);
    }

    public function testCreateSuccess(): void
    {
        $data = [
            'form_uuid' => $this->getFaker()->uuid,
            'message' => $this->getFaker()->text(1000),
        ];

        $formData = $this->service->create($data);
        $this->assertInstanceOf(FromData::class, $formData);
        $this->assertSame($data['form_uuid'], $formData->getFormUuid());
        $this->assertSame($data['message'], $formData->getMessage());

        /** @var FromDataRepository $formDataRepo */
        $formDataRepo = $this->em->getRepository(FromData::class);
        $fd = $formDataRepo->findByFormUuid($data['form_uuid']);
        $this->assertInstanceOf(FromData::class, $fd);
        $this->assertSame($data['form_uuid'], $fd->getFormUuid());
        $this->assertSame($data['message'], $fd->getMessage());
    }

    public function testCreateWithMissingTitleValue(): void
    {
        $this->expectException(MissingMessageValueException::class);

        $this->service->create();
    }

    public function testReadSuccess(): void
    {
        $data = [
            'form_uuid' => $this->getFaker()->uuid,
            'message' => $this->getFaker()->text(1000),
        ];

        $this->service->create($data);

        $formData = $this->service->read(['form_uuid' => $data['form_uuid']]);
        $this->assertInstanceOf(FromData::class, $formData);
        $this->assertSame($data['title'], $formData->getFormUuid());
        $this->assertSame($data['message'], $formData->getMessage());
    }

    public function testReadWithFormNotFound(): void
    {
        $this->expectException(FormDataNotFoundException::class);

        $this->service->read(['form_uuid' => $this->getFaker()->uuid]);
    }

    public function testUpdate(): void
    {
        $formData = $this->service->create([
            'form_uuid' => $this->getFaker()->uuid,
            'message' => $this->getFaker()->text(1000),
        ]);

        $data = [
            'form_uuid' => $this->getFaker()->uuid,
            'message' => $this->getFaker()->text(1000),
        ];

        $formData = $this->service->update($formData, $data);
        $this->assertInstanceOf(FromData::class, $formData);
        $this->assertSame($data['form_uuid'], $formData->getFormUuid());
        $this->assertSame($data['message'], $formData->getMessage());
    }

    public function testUpdateWithFormNotFound(): void
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

    public function testDeleteWithFormNotFound(): void
    {
        $this->expectException(FormDataNotFoundException::class);

        $this->service->delete(null);
    }
}
