<?php declare(strict_types=1);

namespace tests\Domain\Service\Catalog;

use App\Domain\Entities\Catalog\Order;
use App\Domain\Entities\Catalog\OrderStatus;
use App\Domain\Repository\Catalog\OrderRepository;
use App\Domain\Service\Catalog\Exception\OrderNotFoundException;
use App\Domain\Service\Catalog\OrderService;
use App\Domain\Service\Catalog\OrderStatusService;
use Illuminate\Support\Collection;
use tests\TestCase;

/**
 * @internal
 * @coversNothing
 */
class OrderServiceTest extends TestCase
{
    protected OrderService $service;

    public function setUp(): void
    {
        parent::setUp();

        $this->service = $this->getService(OrderService::class);
    }

    protected function getRandomStatus(): OrderStatus
    {
        return $this->getService(OrderStatusService::class)->create([
            'title' => $this->getFaker()->word,
            'order' => $this->getFaker()->numberBetween(0, 1000),
        ]);
    }

    public function testCreateSuccess(): void
    {
        $data = [
            'delivery' => [
                'client' => $this->getFaker()->word,
                'address' => $this->getFaker()->text,
            ],
            'phone' => $this->getFaker()->e164PhoneNumber,
            'email' => $this->getFaker()->email,
            'status' => $this->getRandomStatus(),
            'comment' => $this->getFaker()->text,
            'shipping' => $this->getFaker()->dateTime,
            'date' => $this->getFaker()->dateTime,
            'external_id' => $this->getFaker()->word,
            'export' => $this->getFaker()->word,
            'system' => $this->getFaker()->text,
        ];

        $order = $this->service->create($data);
        $this->assertInstanceOf(Order::class, $order);
        $this->assertEquals($data['delivery'], $order->getDelivery());
        $this->assertSame($data['phone'], $order->getPhone());
        $this->assertSame($data['email'], $order->getEmail());
        $this->assertSame($data['status'], $order->getStatus());
        $this->assertSame($data['comment'], $order->getComment());
        $this->assertEquals($data['shipping'], $order->getShipping());
        $this->assertEquals($data['date'], $order->getDate());
        $this->assertSame($data['external_id'], $order->getExternalId());
        $this->assertSame($data['export'], $order->getExport());
        $this->assertSame($data['system'], $order->getSystem());

        /** @var OrderRepository $orderRepo */
        $orderRepo = $this->em->getRepository(Order::class);
        $o = $orderRepo->findOneByUuid($order->getUuid());
        $this->assertInstanceOf(Order::class, $o);
        $this->assertEquals($data['delivery'], $o->getDelivery());
        $this->assertSame($data['phone'], $o->getPhone());
        $this->assertSame($data['email'], $o->getEmail());
        $this->assertSame($data['status'], $o->getStatus());
        $this->assertSame($data['comment'], $o->getComment());
        $this->assertEquals($data['shipping'], $o->getShipping());
        $this->assertEquals($data['date'], $o->getDate());
        $this->assertSame($data['external_id'], $o->getExternalId());
        $this->assertSame($data['export'], $o->getExport());
        $this->assertSame($data['system'], $o->getSystem());
    }

    public function testReadSuccess1(): void
    {
        $data = [
            'delivery' => [
                'client' => $this->getFaker()->word,
                'address' => $this->getFaker()->text,
            ],
            'phone' => $this->getFaker()->e164PhoneNumber,
            'email' => $this->getFaker()->email,
            'status' => $this->getRandomStatus(),
            'comment' => $this->getFaker()->text,
            'shipping' => $this->getFaker()->dateTime,
            'date' => $this->getFaker()->dateTime,
            'external_id' => $this->getFaker()->word,
            'export' => $this->getFaker()->word,
        ];

        $this->service->create($data);

        $order = $this->service->read(['external_id' => $data['external_id']]);
        $this->assertInstanceOf(Order::class, $order);
        $this->assertSame($data['external_id'], $order->getExternalId());
    }

    public function testReadSuccess2(): void
    {
        $data = [
            'delivery' => [
                'client' => $this->getFaker()->word,
                'address' => $this->getFaker()->text,
            ],
            'phone' => $this->getFaker()->e164PhoneNumber,
            'email' => $this->getFaker()->email,
            'status' => $this->getRandomStatus(),
            'comment' => $this->getFaker()->text,
            'shipping' => $this->getFaker()->dateTime,
            'date' => $this->getFaker()->dateTime,
            'external_id' => $this->getFaker()->word,
            'export' => $this->getFaker()->word,
        ];

        $this->service->create($data);

        $order = $this->service->read(['status' => $data['status']]);
        $this->assertInstanceOf(Collection::class, $order);
    }

    public function testReadWithOrderNotFound(): void
    {
        $this->expectException(OrderNotFoundException::class);

        $this->service->read(['uuid' => $this->getFaker()->uuid]);
    }

    public function testUpdate(): void
    {
        $order = $this->service->create([
            'delivery' => [
                'client' => $this->getFaker()->word,
                'address' => $this->getFaker()->text,
            ],
            'phone' => $this->getFaker()->e164PhoneNumber,
            'email' => $this->getFaker()->email,
            'status' => $this->getRandomStatus(),
            'comment' => $this->getFaker()->text,
            'shipping' => $this->getFaker()->dateTime,
            'date' => $this->getFaker()->dateTime,
            'external_id' => $this->getFaker()->word,
            'export' => $this->getFaker()->word,
            'system' => $this->getFaker()->text,
        ]);

        $data = [
            'delivery' => [
                'client' => $this->getFaker()->word,
                'address' => $this->getFaker()->text,
            ],
            'phone' => $this->getFaker()->e164PhoneNumber,
            'email' => $this->getFaker()->email,
            'status' => $this->getRandomStatus(),
            'comment' => $this->getFaker()->text,
            'shipping' => $this->getFaker()->dateTime,
            'date' => $this->getFaker()->dateTime,
            'external_id' => $this->getFaker()->word,
            'export' => $this->getFaker()->word,
            'system' => $this->getFaker()->text,
        ];

        $order = $this->service->update($order, $data);
        $this->assertInstanceOf(Order::class, $order);
        $this->assertEquals($data['delivery'], $order->getDelivery());
        $this->assertSame($data['phone'], $order->getPhone());
        $this->assertSame($data['email'], $order->getEmail());
        $this->assertSame($data['status'], $order->getStatus());
        $this->assertSame($data['comment'], $order->getComment());
        $this->assertEquals($data['shipping'], $order->getShipping());
        $this->assertEquals($data['date'], $order->getDate());
        $this->assertSame($data['external_id'], $order->getExternalId());
        $this->assertSame($data['export'], $order->getExport());
        $this->assertSame($data['system'], $order->getSystem());
    }

    public function testUpdateWithOrderNotFound(): void
    {
        $this->expectException(OrderNotFoundException::class);

        $this->service->update(null);
    }

    public function testDeleteSuccess(): void
    {
        $order = $this->service->create([
            'title' => $this->getFaker()->title,
            'address' => 'some-custom-address',
            'status' => $this->getRandomStatus(),
        ]);

        $result = $this->service->delete($order);

        $this->assertTrue($result);
    }

    public function testDeleteWithProductNotFound(): void
    {
        $this->expectException(OrderNotFoundException::class);

        $this->service->delete(null);
    }
}
