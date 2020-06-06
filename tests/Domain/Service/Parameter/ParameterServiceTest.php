<?php declare(strict_types=1);

namespace tests\Domain\Service\Parameter;

use App\Domain\Entities\Parameter;
use App\Domain\Repository\ParameterRepository;
use App\Domain\Service\Parameter\Exception\ParameterAlreadyExistsException;
use App\Domain\Service\Parameter\Exception\ParameterNotFoundException;
use App\Domain\Service\Parameter\ParameterService;
use Doctrine\ORM\EntityManager;
use tests\TestCase;

class ParameterServiceTest extends TestCase
{
    /**
     * @var EntityManager
     */
    protected $em;

    /**
     * @var ParameterService
     */
    protected $service;

    public function setUp(): void
    {
        parent::setUp();
        $this->em = $this->getEntityManager();
        $this->service = new ParameterService(null, $this->em, null);
    }

    public function testCreateSuccess(): void
    {
        $data = [
            'key' => $this->getFaker()->userName,
            'value' => $this->getFaker()->text,
        ];

        $parameter = $this->service->create($data);
        $this->assertInstanceOf(Parameter::class, $parameter);
        $this->assertSame($data['key'], $parameter->getKey());
        $this->assertSame($data['value'], $parameter->getValue());

        /** @var ParameterRepository $parameterRepo */
        $parameterRepo = $this->em->getRepository(Parameter::class);
        $p = $parameterRepo->findOneByKey($data['key']);
        $this->assertInstanceOf(Parameter::class, $p);
        $this->assertSame($data['key'], $p->getKey());
        $this->assertSame($data['value'], $p->getValue());
    }

    public function testCreateWithParameterAlreadyExistent(): void
    {
        $this->expectException(ParameterAlreadyExistsException::class);

        $data = [
            'key' => $this->getFaker()->userName,
            'value' => $this->getFaker()->text,
        ];

        $parameter = (new Parameter)
            ->setKey($data['key'])
            ->setValue($data['value']);

        $this->em->persist($parameter);
        $this->em->flush();

        $this->service->create($data);
    }

    public function testReadSuccess(): void
    {
        $data = [
            'key' => $this->getFaker()->userName,
            'value' => $this->getFaker()->text,
        ];

        $this->service->create($data);

        $parameter = $this->service->read(['key' => $data['key']]);
        $this->assertInstanceOf(Parameter::class, $parameter);
        $this->assertSame($data['key'], $parameter->getKey());
        $this->assertSame($data['value'], $parameter->getValue());
    }

    public function testReadWithDefault(): void
    {
        $data = [
            'key' => $this->getFaker()->userName,
            'default' => $this->getFaker()->text,
        ];

        $parameter = $this->service->read(['key' => $data['key']], $data['default']);
        $this->assertInstanceOf(Parameter::class, $parameter);
        $this->assertSame($data['key'], $parameter->getKey());
        $this->assertSame($data['default'], $parameter->getValue());
    }

    public function testUpdateSuccess(): void
    {
        $parameter = $this->service->create([
            'key' => $this->getFaker()->userName,
            'value' => $this->getFaker()->text,
        ]);

        $data = [
            'value' => $this->getFaker()->text,
        ];

        $parameter = $this->service->update($parameter, $data);
        $this->assertSame($data['value'], $parameter->getValue());
    }

    public function testUpdateWithPageNotFound(): void
    {
        $this->expectException(ParameterNotFoundException::class);

        $this->service->update(null);
    }

    public function testDeleteSuccess(): void
    {
        $parameter = $this->service->create([
            'key' => $this->getFaker()->userName,
            'value' => $this->getFaker()->text,
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
