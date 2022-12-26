<?php declare(strict_types=1);

namespace tests\Domain\Service\Catalog;

use App\Domain\Entities\Catalog\OrderStatus;
use App\Domain\Repository\Catalog\OrderRepository;
use App\Domain\Service\Catalog\Exception\OrderStatusNotFoundException;
use App\Domain\Service\Catalog\OrderStatusService;
use tests\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
class OrderStatusServiceTest extends TestCase
{
    protected OrderStatusService $service;

    public function setUp(): void
    {
        parent::setUp();

        $this->service = $this->getService(OrderStatusService::class);
    }

    public function testCreateSuccess(): void
    {
        $data = [
            'title' => $this->getFaker()->word,
            'order' => $this->getFaker()->numberBetween(0, 1000),
        ];

        $os = $this->service->create($data);
        $this->assertInstanceOf(OrderStatus::class, $os);
        $this->assertEquals($data['title'], $os->getTitle());
        $this->assertEquals($data['order'], $os->getOrder());

        /** @var OrderRepository $orderStatusRepo */
        $orderStatusRepo = $this->em->getRepository(OrderStatus::class);
        $os = $orderStatusRepo->findOneByUuid($os->getUuid());
        $this->assertInstanceOf(OrderStatus::class, $os);
        $this->assertEquals($data['title'], $os->getTitle());
        $this->assertEquals($data['order'], $os->getOrder());
    }

    public function testReadSuccess(): void
    {
        $data = [
            'title' => $this->getFaker()->word,
            'order' => $this->getFaker()->numberBetween(0, 1000),
        ];

        $this->service->create($data);

        $os = $this->service->read(['title' => $data['title']]);
        $this->assertInstanceOf(OrderStatus::class, $os);
        $this->assertEquals($data['title'], $os->getTitle());
        $this->assertEquals($data['title'], $os->getTitle());
        $this->assertEquals($data['order'], $os->getOrder());
    }

    public function testReadWithOrderStatusNotFound(): void
    {
        $this->expectException(OrderStatusNotFoundException::class);

        $this->service->read(['uuid' => $this->getFaker()->uuid]);
    }

    public function testUpdate(): void
    {
        $os = $this->service->create([
            'title' => $this->getFaker()->word,
            'order' => $this->getFaker()->numberBetween(0, 1000),
        ]);

        $data = [
            'title' => $this->getFaker()->word,
            'order' => $this->getFaker()->numberBetween(0, 1000),
        ];

        $os = $this->service->update($os, $data);
        $this->assertInstanceOf(OrderStatus::class, $os);
        $this->assertEquals($data['title'], $os->getTitle());
        $this->assertEquals($data['order'], $os->getOrder());
    }

    public function testUpdateWithOrderNotFound(): void
    {
        $this->expectException(OrderStatusNotFoundException::class);

        $this->service->update(null);
    }

    public function testDeleteSuccess(): void
    {
        $os = $this->service->create([
            'title' => $this->getFaker()->word,
            'order' => $this->getFaker()->numberBetween(0, 1000),
        ]);

        $result = $this->service->delete($os);

        $this->assertTrue($result);
    }

    public function testDeleteWithProductNotFound(): void
    {
        $this->expectException(OrderStatusNotFoundException::class);

        $this->service->delete(null);
    }
}
