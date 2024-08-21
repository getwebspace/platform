<?php declare(strict_types=1);

namespace tests\Domain\Service\Catalog;

use App\Domain\Models\CatalogAttribute;
use App\Domain\Service\Catalog\AttributeService;
use App\Domain\Service\Catalog\Exception\AddressAlreadyExistsException;
use App\Domain\Service\Catalog\Exception\AttributeNotFoundException;
use App\Domain\Service\Catalog\Exception\MissingTitleValueException;
use App\Domain\Service\Catalog\Exception\TitleAlreadyExistsException;
use tests\TestCase;

/**
 * @internal
 *
 * #[CoversNothing]
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
            'title' => implode(' ', $this->getFaker()->words(3)),
            'address' => implode('-', $this->getFaker()->words(4)),
            'type' => $this->getFaker()->randomElement(\App\Domain\Casts\Catalog\Attribute\Type::LIST),
            'group' => $this->getFaker()->word,
            'is_filter' => $this->getFaker()->boolean,
        ];

        $attribute = $this->service->create($data);
        $this->assertInstanceOf(CatalogAttribute::class, $attribute);
        $this->assertEquals($data['title'], $attribute->title);
        $this->assertEquals($data['address'], $attribute->address);
        $this->assertEquals($data['type'], $attribute->type);
        $this->assertEquals($data['group'], $attribute->group);
        $this->assertEquals($data['is_filter'], $attribute->is_filter);
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
            'title' => implode(' ', $this->getFaker()->words(5)),
            'address' => implode('-', $this->getFaker()->words(4)),
        ];

        $this->service->create($data);
        $this->service->create($data);
    }

    public function testCreateWithAddressExistent(): void
    {
        $this->expectException(AddressAlreadyExistsException::class);

        $address = implode('-', $this->getFaker()->words(4));

        $this->service->create([
            'title' => implode(' ', $this->getFaker()->words(3)),
            'address' => $address,
        ]);
        $this->service->create([
            'title' => implode(' ', $this->getFaker()->words(3)),
            'address' => $address,
        ]);
    }

    public function testReadSuccess1(): void
    {
        $data = [
            'title' => implode(' ', $this->getFaker()->words(3)),
            'address' => implode('-', $this->getFaker()->words(4)),
            'type' => $this->getFaker()->randomElement(\App\Domain\Casts\Catalog\Attribute\Type::LIST),
        ];

        $this->service->create($data);

        $attribute = $this->service->read(['title' => $data['title']]);
        $this->assertInstanceOf(CatalogAttribute::class, $attribute);
        $this->assertEquals($data['title'], $attribute->title);
        $this->assertEquals($data['address'], $attribute->address);
        $this->assertEquals($data['type'], $attribute->type);
    }

    public function testReadSuccess2(): void
    {
        $data = [
            'title' => implode(' ', $this->getFaker()->words(3)),
            'address' => implode('-', $this->getFaker()->words(4)),
            'type' => $this->getFaker()->randomElement(\App\Domain\Casts\Catalog\Attribute\Type::LIST),
        ];

        $this->service->create($data);

        $attribute = $this->service->read(['address' => $data['address']]);
        $this->assertInstanceOf(CatalogAttribute::class, $attribute);
        $this->assertEquals($data['title'], $attribute->title);
        $this->assertEquals($data['address'], $attribute->address);
        $this->assertEquals($data['type'], $attribute->type);
    }

    public function testReadWithCategoryNotFound(): void
    {
        $this->expectException(AttributeNotFoundException::class);

        $this->service->read(['title' => $this->getFaker()->word]);
    }

    public function testUpdate(): void
    {
        $attribute = $this->service->create([
            'title' => implode(' ', $this->getFaker()->words(3)),
            'address' => implode('-', $this->getFaker()->words(4)),
            'type' => $this->getFaker()->randomElement(\App\Domain\Casts\Catalog\Attribute\Type::LIST),
            'group' => $this->getFaker()->word,
            'is_filter' => $this->getFaker()->boolean,
        ]);

        $data = [
            'title' => implode(' ', $this->getFaker()->words(3)),
            'address' => implode('-', $this->getFaker()->words(4)),
            'type' => $this->getFaker()->randomElement(\App\Domain\Casts\Catalog\Attribute\Type::LIST),
            'group' => $this->getFaker()->word,
            'is_filter' => $this->getFaker()->boolean,
        ];

        $attribute = $this->service->update($attribute, $data);
        $this->assertInstanceOf(CatalogAttribute::class, $attribute);
        $this->assertEquals($data['title'], $attribute->title);
        $this->assertEquals($data['address'], $attribute->address);
        $this->assertEquals($data['type'], $attribute->type);
        $this->assertEquals($data['group'], $attribute->group);
        $this->assertEquals($data['is_filter'], $attribute->is_filter);
    }

    public function testUpdateWithCategoryNotFound(): void
    {
        $this->expectException(AttributeNotFoundException::class);

        $this->service->update(null);
    }

    public function testDeleteSuccess(): void
    {
        $attribute = $this->service->create([
            'title' => implode(' ', $this->getFaker()->words(3)),
            'address' => implode('-', $this->getFaker()->words(4)),
            'type' => $this->getFaker()->randomElement(\App\Domain\Casts\Catalog\Attribute\Type::LIST),
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
