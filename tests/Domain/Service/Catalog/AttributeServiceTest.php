<?php declare(strict_types=1);

namespace tests\Domain\Service\Catalog;

use App\Domain\Entities\Catalog\Attribute;
use App\Domain\Repository\Catalog\AttributeRepository;
use App\Domain\Service\Catalog\AttributeService;
use App\Domain\Service\Catalog\Exception\AddressAlreadyExistsException;
use App\Domain\Service\Catalog\Exception\AttributeNotFoundException;
use App\Domain\Service\Catalog\Exception\MissingTitleValueException;
use App\Domain\Service\Catalog\Exception\TitleAlreadyExistsException;
use tests\TestCase;

/**
 * @internal
 * @coversNothing
 */
class AttributeServiceTest extends TestCase
{
    protected AttributeService $service;

    public function setUp(): void
    {
        parent::setUp();

        $this->service = $this->getService(AttributeService::class);
    }

    public function testCreateSuccess(): void
    {
        $data = [
            'title' => $this->getFaker()->title,
            'address' => $this->getFaker()->word,
            'type' => $this->getFaker()->randomElement(\App\Domain\Types\Catalog\AttributeTypeType::LIST),
        ];

        $attribute = $this->service->create($data);
        $this->assertInstanceOf(Attribute::class, $attribute);
        $this->assertSame($data['title'], $attribute->getTitle());
        $this->assertSame($data['address'], $attribute->getAddress());
        $this->assertSame($data['type'], $attribute->getType());

        $attributeRepo = $this->em->getRepository(Attribute::class);
        $a = $attributeRepo->findOneByTitle($data['title']);
        $this->assertInstanceOf(Attribute::class, $a);
        $this->assertSame($data['title'], $a->getTitle());
        $this->assertSame($data['address'], $a->getAddress());
        $this->assertSame($data['type'], $a->getType());
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
            'address' => 'some-custom-address',
        ];

        $attribute = (new Attribute())
            ->setTitle($data['title'])
            ->setAddress($data['address']);

        $this->em->persist($attribute);
        $this->em->flush();

        $this->service->create($data);
    }

    public function testCreateWithAddressExistent(): void
    {
        $this->expectException(AddressAlreadyExistsException::class);

        $data = [
            'title' => $this->getFaker()->title,
            'address' => 'some-custom-address',
        ];

        $attribute = (new Attribute())
            ->setTitle($data['title'] . '-miss')
            ->setAddress($data['address']);

        $this->em->persist($attribute);
        $this->em->flush();

        $this->service->create($data);
    }

    public function testReadSuccess1(): void
    {
        $data = [
            'title' => $this->getFaker()->title,
            'address' => $this->getFaker()->word,
            'type' => $this->getFaker()->randomElement(\App\Domain\Types\Catalog\AttributeTypeType::LIST),
        ];

        $this->service->create($data);

        $attribute = $this->service->read(['title' => $data['title']]);
        $this->assertInstanceOf(Attribute::class, $attribute);
        $this->assertSame($data['title'], $attribute->getTitle());
        $this->assertSame($data['address'], $attribute->getAddress());
        $this->assertSame($data['type'], $attribute->getType());
    }

    public function testReadSuccess2(): void
    {
        $data = [
            'title' => $this->getFaker()->title,
            'address' => 'some-custom-address',
            'type' => $this->getFaker()->randomElement(\App\Domain\Types\Catalog\AttributeTypeType::LIST),
        ];

        $this->service->create($data);

        $attribute = $this->service->read(['address' => $data['address']]);
        $this->assertInstanceOf(Attribute::class, $attribute);
        $this->assertSame($data['title'], $attribute->getTitle());
        $this->assertSame($data['address'], $attribute->getAddress());
        $this->assertSame($data['type'], $attribute->getType());
    }

    public function testReadWithCategoryNotFound(): void
    {
        $this->expectException(AttributeNotFoundException::class);

        $this->service->read(['title' => $this->getFaker()->title]);
    }

    public function testUpdate(): void
    {
        $attribute = $this->service->create([
            'title' => $this->getFaker()->title,
            'address' => $this->getFaker()->word,
            'type' => $this->getFaker()->randomElement(\App\Domain\Types\Catalog\AttributeTypeType::LIST),
        ]);

        $data = [
            'title' => $this->getFaker()->title,
            'address' => $this->getFaker()->word,
            'type' => $this->getFaker()->randomElement(\App\Domain\Types\Catalog\AttributeTypeType::LIST),
        ];

        $attribute = $this->service->update($attribute, $data);
        $this->assertInstanceOf(Attribute::class, $attribute);
        $this->assertSame($data['title'], $attribute->getTitle());
        $this->assertSame($data['address'], $attribute->getAddress());
        $this->assertSame($data['type'], $attribute->getType());
    }

    public function testUpdateWithCategoryNotFound(): void
    {
        $this->expectException(AttributeNotFoundException::class);

        $this->service->update(null);
    }

    public function testDeleteSuccess(): void
    {
        $attribute = $this->service->create([
            'title' => $this->getFaker()->title,
            'address' => $this->getFaker()->word,
            'type' => $this->getFaker()->randomElement(\App\Domain\Types\Catalog\AttributeTypeType::LIST),
        ]);

        $result = $this->service->delete($attribute);

        $this->assertTrue($result);
    }

    public function testDeleteWithCategoryNotFound(): void
    {
        $this->expectException(AttributeNotFoundException::class);

        $this->service->delete(null);
    }
}
